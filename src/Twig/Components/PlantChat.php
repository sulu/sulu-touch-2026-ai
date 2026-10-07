<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Sulu\Bundle\AiPlatformBundle\Application\Agent\AgentClientInterface;
use Sulu\Bundle\AiPlatformBundle\Infrastructure\SuluAi\Exception\AgentInputRequiredException;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class PlantChat
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $message = '';

    /**
     * What the visitor sees. The component keeps its state in the page, so there is no session.
     *
     * @var list<array{type: string, text: string, url?: string}>
     */
    #[LiveProp]
    public array $log = [];

    /** The platform of sulu.ai keeps the history itself and hands out a run. OpenAI never does. */
    #[LiveProp]
    public ?string $run = null;

    /** The message of the run that waits for the answers of the visitor, and the question the agent asked. */
    #[LiveProp]
    public ?string $pending = null;

    #[LiveProp]
    public string $question = '';

    /** @var list<array{name: string, label: string, type: string, options?: list<mixed>}> */
    #[LiveProp]
    public array $fields = [];

    /** @var array<string, mixed> */
    #[LiveProp(writable: true)]
    public array $answers = [];

    public function __construct(
        #[Autowire(service: 'ai.agent.plant_finder')]
        private readonly AgentInterface $agent,
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'sulu_ai_platform.agent_client')]
        private readonly AgentClientInterface $agentClient,
    ) {
    }

    #[LiveAction]
    public function send(): void
    {
        \set_time_limit(180);

        $question = \trim($this->message);
        if ('' === $question) {
            return;
        }
        $this->message = '';

        // OpenAI knows no history, so every call sends all messages again.
        $messages = new MessageBag();
        foreach ($this->log as $entry) {
            if ('you' === $entry['type']) {
                $messages->add(Message::ofUser($entry['text']));
            } elseif ('agent' === $entry['type']) {
                $messages->add(Message::ofAssistant($entry['text']));
            }
        }
        $messages->add(Message::ofUser($question));
        $this->log[] = ['type' => 'you', 'text' => $question];

        $this->call($messages, []);
    }

    /** The visitor filled in the form the agent asked for. */
    #[LiveAction]
    public function answer(): void
    {
        \set_time_limit(180);

        $answers = $this->answers;
        $this->log[] = ['type' => 'you', 'text' => \implode(', ', \array_map(static fn (mixed $value): string => \is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value, $answers))];
        $message = $this->pending;
        $this->resetQuestion();

        $this->call(new MessageBag(Message::ofUser((string) \json_encode($answers))), ['message' => $message, 'answers' => $answers]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function call(MessageBag $messages, array $options): void
    {
        $steps = [];
        $plants = [];
        $this->eventDispatcher->addListener(ToolCallRequested::class, static function (ToolCallRequested $event) use (&$steps): void {
            $steps[] = ['type' => 'step', 'text' => 'Tool: ' . $event->getDefinition()->getName()];
        });
        $this->eventDispatcher->addListener(ToolCallSucceeded::class, static function (ToolCallSucceeded $event) use (&$plants): void {
            $result = $event->getResult()->getResult();
            if ('sulu_product_search_products_by_attributes' === $event->getDefinition()->getName() && \is_array($result)) {
                $plants = $result['results'] ?? [];
            }
        });

        // The platform of sulu.ai runs ask_user itself. The bundle cannot take a list in the model options.
        $options['server_tools'] = ['ask_user'];
        if (null !== $this->run) {
            $options['run'] = $this->run;
        }

        try {
            // The execution is lazy: the platform is called, and its errors are thrown, when the content is read.
            $result = $this->agent->call($messages, $options);
            $answer = $result->asText();
        } catch (AgentInputRequiredException $exception) {
            // The model asks the visitor. The form below the log collects the answers.
            $this->run = $exception->getRunUuid();
            $this->log = [...$this->log, ...$steps];
            $this->askQuestion($exception->getRunUuid(), $exception->getMessageUuid());

            return;
        } catch (\Exception $exception) {
            // The exceptions of the two platforms share no common type, so catch \Exception.
            $this->log[] = ['type' => 'error', 'text' => 'Error: ' . $exception->getMessage()];

            return;
        }

        $run = $result->getMetadata()->get('run_uuid');
        if (\is_string($run)) {
            $this->run = $run;
        }

        $this->log = [...$this->log, ...$steps, ['type' => 'agent', 'text' => $answer]];
        foreach ($plants as $plant) {
            $this->log[] = ['type' => 'plant', 'text' => \sprintf('%s (%s)', $plant['title'], $plant['code']), 'url' => $plant['url']];
        }
    }

    private function askQuestion(string $runUuid, string $messageUuid): void
    {
        $input = $this->agentClient->getMessage($runUuid, $messageUuid)['pending']['input'] ?? [];

        $this->pending = $messageUuid;
        $this->question = $input['question'] ?? '';
        $this->fields = $input['fields'] ?? [];

        // The form shows the first option of a select, so the answers start with it.
        $this->answers = [];
        foreach ($this->fields as $field) {
            $options = $field['options'] ?? [];
            $first = \reset($options);
            $this->answers[$field['name']] = match ($field['type']) {
                'checkbox' => false,
                'select' => \is_array($first) ? ($first['value'] ?? $first['label'] ?? '') : (string) $first,
                default => '',
            };
        }
    }

    private function resetQuestion(): void
    {
        $this->pending = null;
        $this->question = '';
        $this->fields = [];
        $this->answers = [];
    }
}
