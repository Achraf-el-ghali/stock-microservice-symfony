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
use App\Entity\ProcessedEvent;
use App\Repository\ProcessedEventRepository;

#[AsCommand(
    name: 'app:kafka-consumer',
    description: 'pour la consommation de kafka msg',
)]
class KafkaConsumerCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private StockManager $stockManager;
    private ProcessedEventRepository $processedEventRepository;
    private $producer;

    public function __construct(EntityManagerInterface $entityManager, StockManager $stockManager, ProcessedEventRepository $processedEventRepository)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->stockManager = $stockManager;
        $this->processedEventRepository = $processedEventRepository;

        $broker = $_ENV['KAFKA_BROKER'] ?? 'kafka-catalogue:9092';
        $this->producer = KafkaProducerBuilder::create()
            ->withAdditionalBroker($broker)
            ->build();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $broker = $_ENV['KAFKA_BROKER'] ?? 'kafka-catalogue:9092';
        $consumer = KafkaConsumerBuilder::create()
            ->withAdditionalBroker($broker)
            ->withConsumerGroup('stock-group-mvp')
            ->withSubscription('product-created')
            ->withAdditionalSubscription('product-deleted')
            ->withAdditionalSubscription('product-updated')
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

            // 1. Extract and Validate Event ID (Standardized Envelope)
            if (!isset($data['eventId'])) {
                $io->error("Message ignoré : 'eventId' manquant dans l'enveloppe.");
                continue;
            }
            $eventId = $data['eventId'];

            // 2. Idempotency Check
            if ($this->processedEventRepository->isProcessed($eventId, 'stock-service')) {
                $io->warning("Event déjà traité (Skipped) : [ID: $eventId] [Event: {$data['event']}]");
                continue;
            }

            $event = $data['event'] ?? 'unknown';
            $payload = $data['data'] ?? [];

            if (!isset($payload['sku'])) {
                $io->warning("Attention: l'événement reçu '{$event}' n'a pas de champ 'sku' dans sa data.");
                continue;
            }

            try {
                switch ($event) {
                    case 'product-created':
                        $this->handleProductCreated($payload, $eventId, $io);
                        break;

                    case 'product-deleted':
                        $this->handleProductDeleted($payload, $eventId, $io);
                        break;

                    case 'product-updated':
                        $this->handleProductUpdated($payload, $eventId, $io);
                        break;

                    case 'product-updated':
                        $this->handleProductUpdated($payload, $eventId, $io);
                        break;

                    case 'stock-added':
                        $this->stockManager->addStock(
                            $payload['sku'],
                            (int)$payload['quantity'],
                            (float)($payload['purchase_price'] ?? 0),
                            (float)($payload['selling_price'] ?? 0),
                            $payload['source'] ?? 'stock_added',
                            null,
                            $eventId
                        );
                        $io->success("Stock ajouté pour SKU: {$payload['sku']} (+{$payload['quantity']}) [EventID: $eventId]");
                        $this->notifyStockUpdate($payload['sku']);
                        break;

                    case 'stock-decrease-requested':
                        $this->stockManager->decreaseStock(
                            $payload['sku'],
                            (int)$payload['quantity'],
                            $payload['source'] ?? 'order_confirmed',
                            $eventId
                        );
                        $io->success("Stock réduit pour SKU: {$payload['sku']} (-{$payload['quantity']}) [EventID: $eventId]");
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

    private function handleProductCreated(array $payload, string $eventId, SymfonyStyle $io): void
    {
        $this->entityManager->beginTransaction();
        try {
            $existingStock = $this->entityManager
                ->getRepository(Stock::class)
                ->findOneBy(['sku' => $payload['sku']]);

            if (!$existingStock) {
                $stock = new Stock();
                $stock->setSku($payload['sku']);
                $stock->setQuantity(0);
                $stock->setIsActive(true);

                $this->entityManager->persist($stock);
                $this->stockManager->recordProcessedEvent($eventId);
                $this->entityManager->flush();
                $this->entityManager->commit();
                
                $this->sendStockUpdate($stock);
                $io->writeln("Initialisation stock pour SKU: " . $payload['sku'] . " [EventID: $eventId]");
            } else {
                // Even if it exists, record it as processed to satisfy idempotency check next time
                $this->stockManager->recordProcessedEvent($eventId);
                $this->entityManager->flush();
                $this->entityManager->commit();
                $io->warning("Stock existe déjà pour SKU: " . $payload['sku'] . " (Event marked as processed)");
            }
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    private function handleProductDeleted(array $payload, string $eventId, SymfonyStyle $io): void
    {
        $this->entityManager->beginTransaction();
        try {
            $stock = $this->entityManager
                ->getRepository(Stock::class)
                ->findOneBy(['sku' => $payload['sku']]);

            if ($stock) {
                $stock->setIsActive(false);
                $this->stockManager->recordProcessedEvent($eventId);
                $this->entityManager->flush();
                $this->entityManager->commit();

                $this->sendStockUpdate($stock);
                $io->writeln("Stock désactivé pour SKU: " . $payload['sku'] . " [EventID: $eventId]");
            } else {
                $this->stockManager->recordProcessedEvent($eventId);
                $this->entityManager->flush();
                $this->entityManager->commit();
                $io->warning("Stock non trouvé pour SKU: " . $payload['sku'] . " (Event marked as processed)");
            }
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    private function handleProductUpdated(array $payload, string $eventId, SymfonyStyle $io): void
    {
        $this->entityManager->beginTransaction();
        try {
            $stock = $this->entityManager
                ->getRepository(Stock::class)
                ->findOneBy(['sku' => $payload['sku']]);

            if ($stock) {
                if (isset($payload['isActive'])) {
                    $stock->setIsActive($payload['isActive']);
                }
                $this->stockManager->recordProcessedEvent($eventId);
                $this->entityManager->flush();
                $this->entityManager->commit();

                $this->sendStockUpdate($stock);
                $status = ($payload['isActive'] ?? true) ? "Active" : "Inactive";
                $io->writeln("Stock mis à jour pour SKU: " . $payload['sku'] . " (Status: $status) [EventID: $eventId]");
            } else {
                $this->stockManager->recordProcessedEvent($eventId);
                $this->entityManager->flush();
                $this->entityManager->commit();
                $io->warning("Stock non trouvé pour SKU: " . $payload['sku'] . " (Event marked as processed)");
            }
        } catch (\Throwable $e) {
            $this->entityManager->rollback();
            throw $e;
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
