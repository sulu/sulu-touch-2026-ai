<?php

declare(strict_types=1);

namespace App\Twig\Components;

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

    public function __construct(
        #[Autowire(service: 'ai.agent.plant_finder')]
        private readonly AgentInterface $agent,
        private readonly EventDispatcherInterface $eventDispatcher,
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

        $options = null === $this->run ? [] : ['run' => $this->run];

        try {
            // The execution is lazy: the platform is called, and its errors are thrown, when the content is read.
            $result = $this->agent->call($messages, $options);
            $answer = $result->asText();
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
}
