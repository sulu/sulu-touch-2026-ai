<?php

declare(strict_types=1);

namespace App\Twig\Components;

use Sulu\Bundle\AiPlatformBundle\Application\Agent\PollableExecutionInterface;
use Sulu\Bundle\AiPlatformBundle\Infrastructure\Symfony\AI\Agent\PlatformRunAgent;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Execution\ExecutionStatus;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
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

    /** The message of the run the page waits for. While it is set, the page polls. */
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

        $execution = $this->agent->call(new MessageBag(Message::ofUser($question)), null === $this->run ? [] : ['run' => $this->run]);

        $this->run = $execution->getMessages()->getId()->toRfc4122();
        $this->pending = $execution->getId()->toRfc4122();
        $this->cursor = 0;
        $this->plants = [];
    }

    /** Reads the message of the run. A poll also runs the tools the platform waits for. */
    #[LiveAction]
    public function poll(): void
    {
        $run = $this->run;
        $pending = $this->pending;
        if (null === $run || null === $pending) {
            return;
        }

        $execution = $this->agent->call((new MessageBag())->withId(UuidV7::fromString($run)), ['message' => $pending]);

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

            if (PlatformRunAgent::STAGE_TOOL_CALL === $event->getStage()) {
                $this->log[] = ['type' => 'step', 'text' => 'Tool: ' . $event->getMessage()];
            } elseif (PlatformRunAgent::STAGE_TOOL_RESULT === $event->getStage() && 'sulu_product_search_products_by_attributes' === $event->getMessage()) {
                $this->plants = $this->plantsOf($event->getPayload());
            }
        }

        if (ExecutionStatus::Failed === $execution->getStatus()) {
            $this->fail($execution->getError() ?? 'The plant finder is not available right now.');

            return;
        }

        if (ExecutionStatus::Completed !== $execution->getStatus()) {
            return;
        }

        $answer = $execution->getResult()->getContent();
        $this->log[] = ['type' => 'agent', 'text' => \is_string($answer) ? $answer : ''];
        foreach ($this->plants as $plant) {
            $this->log[] = ['type' => 'plant', 'text' => \sprintf('%s (%s)', $plant['title'], $plant['code']), 'url' => $plant['url']];
        }

        $this->pending = null;
        $this->plants = [];
    }

    private function fail(string $message): void
    {
        $this->log[] = ['type' => 'error', 'text' => 'Error: ' . $message];
        $this->pending = null;
        $this->plants = [];
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
