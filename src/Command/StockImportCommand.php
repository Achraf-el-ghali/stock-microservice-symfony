<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
//la commande doit communique avec la base
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\StockRepository;
use App\Entity\Stock;
use App\Service\KafkaProducer;

//on identifier la commande
#[AsCommand(
    name: 'app:stock:import',
    description: 'Add a short description for your command',
)]
//php bin/console app:stock:import stock.csv  /stock.csv est l argument passe
class StockImportCommand extends Command //herite de commande donc symfony sait que c est une commande
{
              private KafkaProducer $producer;
              private EntityManagerInterface $em;
              private StockRepository $stockRepository;
    public function __construct(EntityManagerInterface $em, StockRepository $stockRepository,KafkaProducer $producer)
{
    parent::__construct();
    $this->em = $em;
    $this->stockRepository = $stockRepository;
    $this->producer = $producer;
}
    protected function configure(): void//pour la configue et definir l argument
    {
        $this
            ->addArgument('arg1', InputArgument::REQUIRED, 'CSV file path')//symfony attend un arg
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int //pour l execution
    {
        $io = new SymfonyStyle($input, $output);
        $csvFile  = $input->getArgument('arg1');//recuperation de l arg
        //si csv n existe pas
        if (!file_exists($csvFile)) {
    $io->error("File not found");
    return Command::FAILURE;
}

        if ($csvFile ) {//objet symfonystyle  pour l affichage des messages dans terminal
            $io->note(sprintf('You passed an argument: %s', $csvFile ));
        }
        //pour la lecture du fichier ouvrir seulement
        $handle = fopen($csvFile, 'r');
        //la prmier ligne ne represent pas une donne on doit la saute
        fgetcsv($handle);
        //lire le contenu
       while (($data = fgetcsv($handle)) !== false) // tant que on a pas termine le fichier
{
    if (count($data) < 3) {
        continue;
    }

    $sku = $data[0];
    $quantity = (int)$data[1];
    $price = (float)$data[2];

    //pour l affichage dans terminal 
    $io->text("SKU: $sku | Quantity: $quantity | Price: $price");

    $stock = $this->stockRepository->findOneBy(['sku' => $sku]);

    if (!$stock) {
        $stock = new Stock();
        $stock->setSku($sku);
    }

    $stock->setQuantity($quantity);
    $stock->setPrice($price);

    $this->em->persist($stock);
    // seft l kafka 
    $this->producer->sendProduct($sku, $price, $quantity);
}

             //fermuture du fichier
             fclose($handle);
          //sauvgarder dans la base
           $this->em->flush();



        if ($input->getOption('option1')) {
            // ...
        }

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
