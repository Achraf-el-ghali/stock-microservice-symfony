<?php

namespace App\Command;

use App\Service\CsvSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stock:migrate-csv',
    description: 'Perform one-time manual migration of the initial stock.csv into database lots.',
)]
class StockMigrateCsvCommand extends Command
{
    public function __construct(private CsvSyncService $csvSyncService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('filePath', InputArgument::OPTIONAL, 'Path to the initial stock.csv', 'stock.csv');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('filePath');

        if (!file_exists($filePath)) {
            $io->error("File not found: $filePath");
            return Command::FAILURE;
        }

        $io->title("Starting One-time Migration of $filePath");
        $io->note("This will treat each row as an 'initial_lot'.");

        $results = $this->csvSyncService->import($filePath, 'initial_migration');

        $io->definitionList(
            ['Imported' => $results['imported']],
            ['Skipped (Duplicate)' => $results['skipped']],
            ['Invalid/Corrupt' => $results['invalid']]
        );

        if (!empty($results['errors'])) {
             $io->error("Errors encountered during migration:");
             foreach ($results['errors'] as $error) {
                 $io->writeln("- $error");
             }
        }

        $io->success("Migration completed!");
        return Command::SUCCESS;
    }
}
