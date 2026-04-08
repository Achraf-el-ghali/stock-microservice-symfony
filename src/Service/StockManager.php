<?php

namespace App\Service;

use App\Entity\Stock;
use App\Entity\StockLot;
use App\Entity\StockMovement;
use App\Entity\ProcessedEvent;
use App\Repository\StockLotRepository;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;

class StockManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StockRepository $stockRepository,
        private StockLotRepository $stockLotRepository
    ) {}

    /**
     * Adds stock by creating a new lot and updating the summary.
     */
    public function addStock(string $sku, int $quantity, float $purchasePrice, float $sellingPrice, string $source = 'stock_added', ?string $importReference = null, ?string $eventId = null): void
    {
        $this->entityManager->beginTransaction();
        try {
            // 0. Idempotency Check
            if ($eventId) {
                $this->recordProcessedEvent($eventId);
            }

            // 1. Update or Create Stock Summary
            $stock = $this->stockRepository->findOneBy(['sku' => $sku]);
            if (!$stock) {
                $stock = new Stock();
                $stock->setSku($sku);
                $stock->setIsActive(true);
                $this->entityManager->persist($stock);
            }
            $stock->setQuantity($stock->getQuantity() + $quantity);
            $stock->setPrice($sellingPrice); // Update latest selling price

            // 2. Create Stock Lot
            $lot = new StockLot();
            $lot->setSku($sku);
            $lot->setQuantityInitial($quantity);
            $lot->setQuantityRemaining($quantity);
            $lot->setPurchasePrice($purchasePrice);
            $lot->setSellingPrice($sellingPrice);
            $lot->setDateEntry(new \DateTime());
            $lot->setImportReference($importReference);
            $this->entityManager->persist($lot);

            // 3. Create Stock Movement
            $movement = new StockMovement();
            $movement->setSku($sku);
            $movement->setLotId($lot->getId()); // Will be null until flush, but we can flush lot first if needed
            $movement->setType(StockMovement::TYPE_IN);
            $movement->setQuantity($quantity);
            $movement->setSource($source);
            $movement->setCreatedAt(new \DateTime());
            $this->entityManager->persist($movement);

            $this->entityManager->flush();
            
            // Update movement with lot ID after flush if necessary
            $movement->setLotId($lot->getId());
            $this->entityManager->flush();

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Decreases stock using FIFO logic across available lots.
     */
    public function decreaseStock(string $sku, int $quantity, string $source, ?string $eventId = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        $this->entityManager->beginTransaction();
        try {
            // 0. Idempotency Check
            if ($eventId) {
                $this->recordProcessedEvent($eventId);
            }

            // 1. Lock Stock Summary for Concurrency
            $stock = $this->stockRepository->findOneBySkuWithLock($sku);
            if (!$stock) {
                throw new \Exception("Stock not found for SKU: $sku");
            }

            if ($stock->getQuantity() < $quantity) {
                 throw new \Exception("Insufficient stock for SKU: $sku. Available: {$stock->getQuantity()}, Requested: $quantity");
            }

            // 2. Fetch Available Lots (FIFO order)
            $lots = $this->stockLotRepository->findAvailableLotsBySku($sku);
            
            $remainingToDeduct = $quantity;
            foreach ($lots as $lot) {
                if ($remainingToDeduct <= 0) {
                    break;
                }

                $availableInLot = $lot->getQuantityRemaining();
                $deduction = min($availableInLot, $remainingToDeduct);

                $lot->setQuantityRemaining($availableInLot - $deduction);
                $remainingToDeduct -= $deduction;

                // 3. Create Movement for this lot
                $movement = new StockMovement();
                $movement->setSku($sku);
                $movement->setLotId($lot->getId());
                $movement->setType(StockMovement::TYPE_OUT);
                $movement->setQuantity($deduction);
                $movement->setSource($source);
                $movement->setCreatedAt(new \DateTime());
                $this->entityManager->persist($movement);
            }

            if ($remainingToDeduct > 0) {
                // This shouldn't happen if summary is consistent with lots
                throw new \Exception("FIFO Logic Error: Could not deduct full quantity for SKU: $sku. Remaining: $remainingToDeduct");
            }

            // 4. Update Summary
            $stock->setQuantity($stock->getQuantity() - $quantity);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Records an event as processed within the current transaction.
     */
    private function recordProcessedEvent(string $eventId): void
    {
        $processedEvent = new ProcessedEvent();
        $processedEvent->setEventId($eventId);
        $processedEvent->setServiceName('stock-service');
        $this->entityManager->persist($processedEvent);
    }
}
