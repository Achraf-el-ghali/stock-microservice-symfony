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

#[AsCommand(
    name: 'app:promo:import',
    description: 'Import promotions from CSV'
)]
class PromoImportCommand extends Command
{
    private EntityManagerInterface $em;
    private StockRepository $stockRepository;

    public function __construct(EntityManagerInterface $em, StockRepository $stockRepository)
    {
        parent::__construct();
        $this->em = $em;
        $this->stockRepository = $stockRepository;
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'Promo CSV file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvFile = $input->getArgument('file');

        if (!file_exists($csvFile)) {
            $io->error("File not found");
            return Command::FAILURE;
        }

        $handle = fopen($csvFile, 'r');

        // skip header
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {

            $sku = $data[0];
            $description = $data[1];
            $type = $data[2];
            $value = (float)$data[3];

            $stock = $this->stockRepository->findOneBy(['sku' => $sku]);

            if (!$stock) {
                $io->warning("SKU not found: $sku");
                continue;
            }

            $promotion = new Promotion();
            $promotion->setDescription($description);
            $promotion->setType($type);
            $promotion->setValue($value);
            $stock->addPromotion($promotion);

            $this->em->persist($promotion);

            $io->text("SKU: $sku | $description | $type | $value");
        }

        fclose($handle);

        $this->em->flush();

        $io->success("Promotions imported successfully");

        return Command::SUCCESS;
    }
}
