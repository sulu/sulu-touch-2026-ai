<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Agent\Toolbox\Event\ToolCallSucceeded;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[AsCommand(name: 'app:agent', description: 'Chat with the plant finder agent')]
final class AgentCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'ai.agent.plant_finder')]
        private readonly AgentInterface $agent,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $messages = new MessageBag();

        // The agent calls its tools by itself. These listeners show what it sends in and what comes back.
        $this->eventDispatcher->addListener(ToolCallRequested::class, static function (ToolCallRequested $event) use ($io): void {
            $io->writeln(\sprintf('<comment>Tool %s</comment> %s', $event->getDefinition()->getName(), \json_encode($event->getToolCall()->getArguments(), \JSON_UNESCAPED_UNICODE)));
        });
        $this->eventDispatcher->addListener(ToolCallSucceeded::class, static function (ToolCallSucceeded $event) use ($io): void {
            $io->writeln(\sprintf('<comment>  result</comment> %s', \mb_strimwidth((string) \json_encode($event->getResult()->getResult(), \JSON_UNESCAPED_UNICODE), 0, 200, '...')));
        });

        while ($question = $io->ask('You')) {
            $messages->add(Message::ofUser($question));

            $answer = $this->agent->call($messages)->asText();
            $messages->add(Message::ofAssistant($answer));

            $io->writeln('<info>Agent:</info> ' . $answer);
        }

        return Command::SUCCESS;
    }
}
