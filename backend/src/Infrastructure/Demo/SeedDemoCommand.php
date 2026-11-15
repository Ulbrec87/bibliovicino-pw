<?php

declare(strict_types=1);

namespace App\Infrastructure\Demo;

use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:seed-demo', description: 'Create the deterministic BiblioVicino demo dataset.')]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly DemoDatasetSeeder $seeder,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Delete application data and recreate the demo dataset.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!in_array($this->environment, ['dev', 'test'], true)) {
            $io->error('The demo dataset can only be seeded in the dev or test environment.');

            return self::FAILURE;
        }

        try {
            $reset = (bool) $input->getOption('reset');
            if ($reset) {
                $io->warning('Resetting this dev/test database deletes all users, books, authors, copies and loan requests.');
            }
            $created = $this->seeder->seed($reset);
        } catch (RuntimeException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        if (!$created) {
            $io->success('The demo dataset is already present. No data was changed.');

            return self::SUCCESS;
        }

        $io->success('Demo dataset created: 10 users, 23 books, 30 copies and 12 loan requests.');
        $io->definitionList(
            ['Demo email' => DemoDatasetSeeder::PRIMARY_EMAIL],
            ['Demo password' => DemoDatasetSeeder::PASSWORD],
            ['Admin email' => DemoDatasetSeeder::ADMIN_EMAIL],
            ['Admin password' => DemoDatasetSeeder::PASSWORD],
        );
        $io->table(
            ['Search radius', 'Expected results'],
            [['1 km', '7'], ['2 km', '12'], ['5 km', '15'], ['10 km', '18']],
        );
        $io->table(
            ['User', 'Effective distance band'],
            [
                ['Anna', 'within 1 km'],
                ['Luca', 'within 1 km'],
                ['Giulia', 'greater than 1 km and within 2 km'],
                ['Marco', 'greater than 1 km and within 2 km'],
                ['Elena', 'greater than 2 km and within 5 km'],
                ['Davide', 'greater than 5 km and within 10 km'],
                ['Sara', 'greater than 10 km'],
                ['Paolo', 'no DiscoveryLocation'],
            ],
        );

        return self::SUCCESS;
    }
}
