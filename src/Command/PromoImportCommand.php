<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\Promotion;

use Doctrine\ORM\EntityManagerInterface;
use App\Repository\StockRepository;
use App\Service\CsvImportService;

#[AsCommand(
    name: 'app:promo:import',
    description: 'Import promotions from CSV'
)]
//php bin/console app:stock:import promo.csv
class PromoImportCommand extends Command
{
    public function __construct(private CsvImportService $importService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Promo CSV file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvFile = $input->getArgument('file');

        $count = $this->importService->importPromotions($csvFile);

        if ($count === 0) {
            $io->error("No promotions imported.");
            return Command::FAILURE;
        }

        $io->success(sprintf("Imported %d promotions successfully", $count));

        $io->success("Promotions imported successfully");

        return Command::SUCCESS;
    }
}
