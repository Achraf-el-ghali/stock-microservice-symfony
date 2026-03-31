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
use App\Service\StockManager;
use Jobcloud\Kafka\Message\KafkaProducerMessage;
use Jobcloud\Kafka\Producer\KafkaProducerBuilder;

#[AsCommand(
    name: 'app:kafka-consumer',
    description: 'pour la consommation de kafka msg',
)]
class KafkaConsumerCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private StockManager $stockManager;
    private $producer;

    public function __construct(EntityManagerInterface $entityManager, StockManager $stockManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->stockManager = $stockManager;
        
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
                ->withAdditionalBroker('kafka:9092')
                ->withConsumerGroup('stock-group-mvp')
                ->withSubscription('product-created')
                ->withAdditionalSubscription('product-deleted')
                ->withAdditionalSubscription('stock-added')
                ->withAdditionalSubscription('stock-decrease-requested')
                // Keeping old ones for compatibility but they will be ignored in the switch
                ->withAdditionalSubscription('product-reserved')
                ->withAdditionalSubscription('product-unreserved')
                ->withAdditionalSubscription('product-removed')
                ->withAdditionalConfig([
                    'auto.offset.reset' => 'earliest'
                ])
                ->build();

        $consumer->subscribe();
        $io->writeln('Stock Kafka consumer started (FIFO MVP)...');

        while (true) {
            try {
                $message = $consumer->consume(120000);
            } catch (\Throwable $e) {
                continue;
            }

            if ($message === null) {
                continue;
            }

            $rawBody = $message->getBody();
            $io->info("Message reçu: " . $rawBody);

            $decoded = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error("Erreur de décodage JSON: " . json_last_error_msg());
                continue;
            }

            // Support wrapped format: { "body": "..." }
            if (isset($decoded['body']) && is_string($decoded['body'])) {
                $io->writeln("Format 'wrapped' détecté, unwrapping...");
                $data = json_decode($decoded['body'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $io->error("Erreur de décodage du corps enveloppé: " . json_last_error_msg());
                    continue;
                }
            } else {
                // Flat format
                $data = $decoded;
            }

            // Validation basics
            if (!$data || !is_array($data) || !isset($data['event'])) {
                $io->warning("Payload invalide: champ 'event' manquant ou format incorrect.");
                continue;
            }

            if (!isset($data['data'])) {
                $io->warning("Payload invalide: champ 'data' manquant.");
                continue;
            }

            $event = $data['event']; 
            $payload = $data['data'];
            
            if (!isset($payload['sku'])) {
                $io->warning("Attention: l'événement reçu '{$event}' n'a pas de champ 'sku' dans sa data.");
                continue;
            }

            try {
                switch ($event) {
                    case 'product-created':
                        $this->handleProductCreated($payload, $io);
                        break;
                    
                    case 'product-deleted':
                        $this->handleProductDeleted($payload, $io);
                        break;

                    case 'stock-added':
                        $this->stockManager->addStock(
                            $payload['sku'],
                            (int)$payload['quantity'],
                            (float)($payload['purchase_price'] ?? 0),
                            (float)($payload['selling_price'] ?? 0),
                            $payload['source'] ?? 'stock_added'
                        );
                        $io->success("Stock ajouté pour SKU: {$payload['sku']} (+{$payload['quantity']})");
                        $this->notifyStockUpdate($payload['sku']);
                        break;

                    case 'stock-decrease-requested':
                        $this->stockManager->decreaseStock(
                            $payload['sku'],
                            (int)$payload['quantity'],
                            $payload['source'] ?? 'order_confirmed'
                        );
                        $io->success("Stock réduit pour SKU: {$payload['sku']} (-{$payload['quantity']})");
                        $this->notifyStockUpdate($payload['sku']);
                        break;

                    default:
                        $io->writeln("Event ignored: " . $event);
                        break;
                }
            } catch (\Throwable $e) {
                $io->error("Error processing $event for SKU {$payload['sku']}: " . $e->getMessage());
            }

            $io->writeln("Message traité sur le topic : " . $event);
        }

        return Command::SUCCESS;
    }

    private function handleProductCreated(array $payload, SymfonyStyle $io): void
    {
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

            $io->writeln("Initialisation stock pour SKU: " . $payload['sku']);
        }
    }

    private function handleProductDeleted(array $payload, SymfonyStyle $io): void
    {
        $stock = $this->entityManager
            ->getRepository(Stock::class)
            ->findOneBy(['sku' => $payload['sku']]);

        if ($stock) {
            $stock->setIsActive(false);
            $this->entityManager->flush();
            $this->sendStockUpdate($stock);
            $io->writeln("Stock désactivé pour SKU: " . $payload['sku']);
        }
    }

    /**
     * Helper to fetch fresh stock and send update
     */
    private function notifyStockUpdate(string $sku): void
    {
        $stock = $this->entityManager
            ->getRepository(Stock::class)
            ->findOneBy(['sku' => $sku]);
            
        if ($stock) {
            $this->sendStockUpdate($stock);
        }
    }

    private function sendStockUpdate(Stock $stock): void
    {
        $message = KafkaProducerMessage::create('stock-updated', 0)
            ->withBody(json_encode([
                'event' => 'stock-updated',
                'data' => [
                    'sku'                => $stock->getSku(),
                    'quantity'           => $stock->getQuantity(),
                    'available_quantity' => $stock->getQuantity(), // Same since reservation removed
                    'is_active'          => $stock->getIsActive()
                ]
            ]));

        $this->producer->produce($message);
        $this->producer->flush(10000);
    }
}
