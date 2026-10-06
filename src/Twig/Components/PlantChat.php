<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Sulu\Bundle\AiPlatformBundle\Application\Agent\PollableExecutionInterface;
use Sulu\Bundle\AiPlatformBundle\Infrastructure\Symfony\AI\Agent\PlatformRunAgent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionInterface;
use Symfony\AI\Agent\Execution\ExecutionStatus;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\UuidV7;
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

    /** The platform of sulu.ai keeps the history itself and hands out a run. */
    #[LiveProp]
    public ?string $run = null;

    /** The message of the run the page waits for. While it is set and no question is open, the page polls. */
    #[LiveProp]
    public ?string $pending = null;

    /** The last event of the run the page has shown. */
    #[LiveProp]
    public int $cursor = 0;

    /**
     * The plants the search found, shown when the answer is there.
     *
     * @var list<array{title: string, code: string, url: string}>
     */
    #[LiveProp]
    public array $plants = [];

    /** The question the agent asked the visitor with ask_user, the id of that tool call and the form fields. */
    #[LiveProp]
    public string $question = '';

    #[LiveProp]
    public string $questionId = '';

    /** @var list<array{name: string, label: string, type: string, options?: list<mixed>}> */
    #[LiveProp]
    public array $fields = [];

    /** @var array<string, mixed> */
    #[LiveProp(writable: true)]
    public array $answers = [];

    public function __construct(
        #[Autowire(service: 'app.plant_finder_runs')]
        private readonly AgentInterface $agent,
    ) {
    }

    /** Hands the question to the platform and returns at once. The page polls for the answer. */
    #[LiveAction]
    public function send(): void
    {
        $question = \trim($this->message);
        if ('' === $question) {
            return;
        }
        $this->message = '';
        $this->log[] = ['type' => 'you', 'text' => $question];

        // The platform of sulu.ai runs ask_user itself. The bundle cannot take a list in the model options.
        $options = ['server_tools' => ['ask_user']];
        if (null !== $this->run) {
            $options['run'] = $this->run;
        }

        $execution = $this->agent->call(new MessageBag(Message::ofUser($question)), $options);

        $this->run = $execution->getMessages()->getId()->toRfc4122();
        $this->pending = $execution->getId()->toRfc4122();
        $this->cursor = 0;
        $this->plants = [];
    }

    /** The visitor filled in the form the agent asked for. The answers go to the platform in the tool message of the question. */
    #[LiveAction]
    public function answer(): void
    {
        $run = $this->run;
        $pending = $this->pending;
        if (null === $run || null === $pending || '' === $this->questionId) {
            return;
        }

        $answers = $this->answers;
        $this->log[] = ['type' => 'you', 'text' => \implode(', ', \array_map(static fn (mixed $value): string => \is_bool($value) ? ($value ? 'yes' : 'no') : (\is_scalar($value) ? (string) $value : ''), $answers))];

        $reply = Message::ofToolCall(new ToolCall($this->questionId, PlatformRunAgent::TOOL_ASK_USER, []), \json_encode($answers, \JSON_THROW_ON_ERROR));
        $this->resetQuestion();

        $execution = $this->agent->call((new MessageBag($reply))->withId(UuidV7::fromString($run)), ['message' => $pending]);
        $this->drive($execution);
    }

    /** Reads the message of the run. A poll also runs the tools the platform waits for. */
    #[LiveAction]
    public function poll(): void
    {
        $run = $this->run;
        $pending = $this->pending;
        if (null === $run || null === $pending || '' !== $this->question) {
            return;
        }

        $execution = $this->agent->call((new MessageBag())->withId(UuidV7::fromString($run)), ['message' => $pending]);
        $this->drive($execution);
    }

    private function drive(ExecutionInterface $execution): void
    {
        try {
            $execution->getResult();
        } catch (\Exception $exception) {
            // A failed message throws, its status tells the page. Anything else is a platform that cannot be reached.
            if (ExecutionStatus::Failed !== $execution->getStatus()) {
                $this->fail($exception->getMessage());

                return;
            }
        }

        foreach ($execution instanceof PollableExecutionInterface ? $execution->getEventsSince($this->cursor) : [] as $event) {
            $this->cursor = \max($this->cursor, $event->getSequence());

            if (PlatformRunAgent::STAGE_TOOL_CALL === $event->getStage() && PlatformRunAgent::TOOL_ASK_USER !== $event->getMessage()) {
                $this->log[] = ['type' => 'step', 'text' => 'Tool: ' . $event->getMessage()];
            } elseif (PlatformRunAgent::STAGE_TOOL_RESULT === $event->getStage() && 'sulu_product_search_products_by_attributes' === $event->getMessage()) {
                $this->plants = $this->plantsOf($event->getPayload());
            }
        }

        switch ($execution->getStatus()) {
            case ExecutionStatus::Failed:
                $this->fail($execution->getError() ?? 'The plant finder is not available right now.');

                return;
            case ExecutionStatus::WaitingForInput:
                foreach ($execution->getPendingToolCalls() as $call) {
                    if (PlatformRunAgent::TOOL_ASK_USER === $call->getName()) {
                        $this->askQuestion($call);
                    }
                }

                return;
            case ExecutionStatus::Completed:
                $answer = $execution->getResult()->getContent();
                $this->log[] = ['type' => 'agent', 'text' => \is_string($answer) ? $answer : ''];
                foreach ($this->plants as $plant) {
                    $this->log[] = ['type' => 'plant', 'text' => \sprintf('%s (%s)', $plant['title'], $plant['code']), 'url' => $plant['url']];
                }

                $this->pending = null;
                $this->plants = [];

                return;
            default:
                return;
        }
    }

    private function askQuestion(ToolCall $call): void
    {
        $arguments = $call->getArguments();

        $fields = [];
        foreach (\is_array($arguments['fields'] ?? null) ? $arguments['fields'] : [] as $field) {
            if (\is_array($field) && \is_string($field['name'] ?? null) && \is_string($field['label'] ?? null) && \is_string($field['type'] ?? null)) {
                $fields[] = ['name' => $field['name'], 'label' => $field['label'], 'type' => $field['type'], 'options' => \is_array($field['options'] ?? null) ? \array_values($field['options']) : []];
            }
        }

        $this->questionId = $call->getId();
        $this->question = \is_string($arguments['question'] ?? null) ? $arguments['question'] : '';
        $this->fields = $fields;

        // The form shows the first option of a select, so the answers start with it.
        $this->answers = [];
        foreach ($fields as $field) {
            $first = $field['options'][0] ?? null;
            $this->answers[$field['name']] = match ($field['type']) {
                'checkbox' => false,
                'select' => \is_array($first) ? ($first['value'] ?? $first['label'] ?? '') : (\is_scalar($first) ? (string) $first : ''),
                default => '',
            };
        }
    }

    private function resetQuestion(): void
    {
        $this->question = '';
        $this->questionId = '';
        $this->fields = [];
        $this->answers = [];
    }

    private function fail(string $message): void
    {
        $this->log[] = ['type' => 'error', 'text' => 'Error: ' . $message];
        $this->pending = null;
        $this->plants = [];
        $this->resetQuestion();
    }

    /**
     * @return list<array{title: string, code: string, url: string}>
     */
    private function plantsOf(mixed $payload): array
    {
        $result = \is_array($payload) && \is_string($payload['result'] ?? null) ? \json_decode($payload['result'], true) : null;

        $plants = [];
        foreach (\is_array($result) && \is_array($result['results'] ?? null) ? $result['results'] : [] as $plant) {
            if (\is_array($plant) && \is_string($plant['title'] ?? null) && \is_string($plant['code'] ?? null) && \is_string($plant['url'] ?? null)) {
                $plants[] = ['title' => $plant['title'], 'code' => $plant['code'], 'url' => $plant['url']];
            }
        }

        return $plants;
    }
}
