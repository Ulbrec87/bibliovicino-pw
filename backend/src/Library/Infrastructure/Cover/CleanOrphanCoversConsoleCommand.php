<?php

declare(strict_types=1);

namespace App\Library\Infrastructure\Cover;

use App\Library\Application\Cover\CleanOrphanCoversCommand;
use App\Library\Application\Cover\CleanOrphanCoversHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:covers:cleanup', description: 'Inspect orphan cover variants older than 24 hours; --delete removes them.')]
final class CleanOrphanCoversConsoleCommand extends Command
{
    public function __construct(private readonly CleanOrphanCoversHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('delete', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->handler->handle(new CleanOrphanCoversCommand((bool) $input->getOption('delete')));
        $output->writeln(sprintf('%d orphan cover(s) %s.', $count, $input->getOption('delete') ? 'removed' : 'found (dry run)'));
        return Command::SUCCESS;
    }
}
