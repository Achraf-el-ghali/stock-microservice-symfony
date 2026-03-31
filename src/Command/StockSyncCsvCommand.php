<?php

namespace App\Command;

use App\Service\CsvSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

#[AsCommand(
    name: 'app:stock:sync-csv',
    description: 'Regular Cron job for CSV synchronization (import/export) with locking.',
)]
class StockSyncCsvCommand extends Command
{
    public function __construct(private CsvSyncService $csvSyncService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('import', 'i', InputOption::VALUE_NONE, 'Import from stock.csv')
            ->addOption('export', 'x', InputOption::VALUE_NONE, 'Export to stock_report.csv')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'File path', 'stock.csv')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getOption('file');

        // 1. Basic Locking to prevent concurrent execution
        $store = new FlockStore();
        $factory = new LockFactory($store);
        $lock = $factory->createLock('stock-sync-csv');

        if (!$lock->acquire()) {
            $io->warning('Another synchronization process is already running.');
            return Command::FAILURE;
        }

        try {
            if ($input->getOption('import')) {
                $io->title("Initiating CSV Import from $filePath");
                $results = $this->csvSyncService->import($filePath, 'cron_sync');

                $io->table(
                    ['Status', 'Count'],
                    [
                        ['Imported', $results['imported']],
                        ['Skipped (Duplicate)', $results['skipped']],
                        ['Invalid', $results['invalid']],
                        ['Errors', count($results['errors'])]
                    ]
                );

                if (!empty($results['errors'])) {
                     $io->error("Import errors detected. Check logs for details.");
                }
            }

            if ($input->getOption('export')) {
                $exportPath = 'stock_report.csv';
                $io->title("Initiating CSV Export to $exportPath");
                $this->csvSyncService->export($exportPath);
                $io->success("Export completed: $exportPath");
            }

            if (!$input->getOption('import') && !$input->getOption('export')) {
                $io->warning("Please specify --import or --export.");
            }

        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
