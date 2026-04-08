<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
// la commande khassha tcommunique m3a l'base
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\StockRepository;
use App\Entity\Stock;

//on identifier la commande
#[AsCommand(
    name: 'app:stock:export-database',
    description: 'Exporte les données de la base vers le fichier stock.csv',
)]
//php bin/console app:stock:export-database /export-database est l argument passe
class StockExportDatabaseCommand extends Command // herite de commande donc symfony sait que c est une 
{
    private EntityManagerInterface $em;
    private StockRepository $stockRepository;
    private \App\Repository\StockLotRepository $stockLotRepository;

    public function __construct(
        EntityManagerInterface $em, 
        StockRepository $stockRepository,
        \App\Repository\StockLotRepository $stockLotRepository
    ) {
        parent::__construct();
        $this->em = $em;
        $this->stockRepository = $stockRepository;
        $this->stockLotRepository = $stockLotRepository;
    }

    protected function configure(): void  //pour la configue et definir l argument
    {
        $this
            
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Nom du fichier de sortie', 'stock.csv')//symfony attend un arg
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int // hna l execution de la commande 
    {
        $io = new SymfonyStyle($input, $output);
        $csvFile = $input->getArgument('arg1'); // njebdo smiya d l-fichier li mpassi f l argument

        // njebdo kolchi dakchi li 3endna f la base de données (table Stock)
        $stocks = $this->stockRepository->findAll();

        // si base de donne vide 
        if (empty($stocks)) {
            $io->warning("Base de donnee est vide");
            return Command::SUCCESS;
        }

       //ecrase le contenue existant
        $handle = fopen($csvFile, 'w');

        if ($handle === false) {
            $io->error("Ma-qdertch n-hell l-fichier: $csvFile");
            return Command::FAILURE;
        }

        
        fputcsv($handle, ['sku', 'quantity', 'price']);

        // lire le contenue de la base 
        foreach ($stocks as $stock) {
            $sku = $stock->getSku();
            $quantity = $stock->getQuantity();
            
            $latestLot = $this->stockLotRepository->findLatestLotBySku($sku);
            $price = $latestLot ? $latestLot->getSellingPrice() : 0.0;

            // verifie via terminal
            $io->text("EXPORT -> SKU: $sku | Qty: $quantity | Price: $price");

            // n-ketbou l-ligne f l-fichier CSV
            fputcsv($handle, [$sku, $quantity, $price]);
        }

        // n-seddou l-fichier
        fclose($handle);

        $io->success("L'exportation t-salat! L-fichier $csvFile rah m-update daba.");

        return Command::SUCCESS;
    }
}