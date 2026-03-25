<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Jobcloud\Kafka\Consumer\KafkaConsumerBuilder;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Stock;
use Jobcloud\Kafka\Producer\KafkaProducerBuilder;
use Jobcloud\Kafka\Message\KafkaProducerMessage;

#[AsCommand(
    name: 'app:kafka-consumer',
    description: 'pour la consommation de kafka msg',
)]
class KafkaConsumerCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private $producer;
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        // pour envoyer msg a kafka
        $this->producer = KafkaProducerBuilder::create()
            ->withAdditionalBroker('kafka:9092')
            ->build();
    }
    protected function configure(): void
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
       $io = new SymfonyStyle($input, $output);
        $consumer = KafkaConsumerBuilder::create()
                ->withAdditionalBroker('kafka:9092') //ou ce trouve server kafka
                ->withConsumerGroup('stock-group-2') //si on a bzaf consumer
                ->withSubscription('product-created')
                ->withSubscription('product-deleted')
                ->withSubscription('product-reserved')
                ->withSubscription('product-unreserved')
                ->withSubscription('product-removed')
              ->withAdditionalConfig([
                  'auto.offset.reset' => 'earliest'
              ])
              ->build(); //la connection with kafka                                            //               create → object فارغ
                                                                                                // broker → فين يتاصل
                                                                                                // group → كيفاش يخدم
                                                                                                // topic → شنو يسمع
                                                                                                // build → connect فعلاً

            $consumer->subscribe();//hebltni
            // $consumer->subscribe(['product-created']);//pour precise topic a ecouter
        $io->writeln('Kafka consumer started...');

        while (true) {
            $message = $consumer->consume(10000);

            if ($message === null){
                continue;
            }

            $data = json_decode($message->getBody(), true);//n9ra msg envoyer par kafka depuis body
            if (!$data) {
                    continue;
                }

            if (!isset($data['event']) || !isset($data['data'])) {//pour verifie si event et data existe
                        continue;
            }

            //$event = $data['event'];
            $event = $message->getTopicName();
            $payload = $data['data'];

            if ($event === 'product-created') {

                $existingStock = $this->entityManager
                    ->getRepository(Stock::class)
                    ->findOneBy(['sku' => $payload['sku']]);

                if (!$existingStock) {

                    $stock = new Stock();
                    $stock->setSku($payload['sku']);
                    $stock->setQuantity(0);
                    $stock->setPrice(0);
                    $stock->setIsActive(true);

                    $this->entityManager->persist($stock);
                    $this->entityManager->flush();
                    $this->sendStockUpdate($stock);

                    $io->writeln("Stock créé pour SKU: " . $payload['sku']);

                } else {
                    $io->writeln("Stock déjà existant pour SKU: " . $payload['sku']);
                }
            }
            elseif ($event === 'product-deleted') {

                $stock = $this->entityManager
                    ->getRepository(Stock::class)
                    ->findOneBy(['sku' => $payload['sku']]);

                if ($stock) {
                    $stock->setIsActive(false);
                    $this->entityManager->flush();

                    $this->sendStockUpdate($stock);
                    $io->writeln("Stock désactivé pour SKU: " . $payload['sku']);
                } else {
                    $io->writeln("Stock introuvable pour SKU: " . $payload['sku']);
                }
            }
            elseif ($event === 'product-reserved') {
                $stock = $this->entityManager
                    ->getRepository(Stock::class)
                    ->findOneBy(['sku' => $payload['sku']]);

                if ($stock) {
                    $qty = $payload['quantity'] ?? 0;
                    $stock->setReserved($stock->getReserved() + $qty);
                    $this->entityManager->flush();
                    //pour calculer il rest conbien :
                    //$available = $stock->getQuantity() - $stock->getReserved();
                    $this->sendStockUpdate($stock);
                    $io->writeln("Produit réservé: " . $payload['sku'] . " (+$qty)");
                } else {
                    $io->writeln("Stock introuvable pour SKU: " . $payload['sku']);
                }
            }
            elseif ($event === 'product-unreserved' || $event === 'product-removed') {
                $stock = $this->entityManager
                    ->getRepository(Stock::class)
                    ->findOneBy(['sku' => $payload['sku']]);

                if ($stock) {
                    $qty = $payload['productReserveRemoved'] ?? $payload['quantity'] ?? 0;
                    $newReserved = $stock->getReserved() - $qty;

                    if ($newReserved < 0) {
                        $newReserved = 0;
                    }

                    $stock->setReserved($newReserved);
                    $this->entityManager->flush();
                    $this->sendStockUpdate($stock);
                    $io->writeln("Produit libéré: " . $payload['sku'] . " (-$qty)");
                } else {
                    $io->writeln("Stock introuvable pour SKU: " . $payload['sku']);
                }
            }

            $io->writeln("Message reçu sur le topic : " . $event);
            dump($data);
            //ici traitement 3la hssab event
        }

        return Command::SUCCESS;
    }
    private function sendStockUpdate(Stock $stock): void
    {

        $available = $stock->getQuantity() - $stock->getReserved();

        $message = KafkaProducerMessage::create('stock-updated', 0)
            ->withBody(json_encode([
                'event' => 'stock-updated',
                'data' => [
                    'sku'                => $stock->getSku(),
                    'quantity'           => $stock->getQuantity(),
                    'reserved'           => $stock->getReserved(),
                    'available_quantity' => $available,
                    'is_active'          => $stock->getIsActive()
                ]
            ]));

        $this->producer->produce($message);
        $this->producer->flush(10000);
    }
}
