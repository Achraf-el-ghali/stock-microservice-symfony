<?php

namespace App\Service;

use App\Entity\StockLot;
use App\Repository\StockLotRepository;
use App\Repository\StockRepository;
use Psr\Log\LoggerInterface;

class CsvSyncService
{
    public function __construct(
        private StockManager $stockManager,
        private StockLotRepository $stockLotRepository,
        private StockRepository $stockRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Imports new lots from a CSV file.
     * Format: sku,quantity,price
     */
    public function import(string $filePath, string $source = 'csv_sync'): array
    {
        $results = [
            'imported' => 0,
            'skipped' => 0,
            'invalid' => 0,
            'errors' => []
        ];

        if (!file_exists($filePath)) {
            $msg = "CSV File not found: $filePath";
            $this->logger->error($msg);
            $results['errors'][] = $msg;
            return $results;
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
             $msg = "Could not open CSV file: $filePath";
             $this->logger->error($msg);
             $results['errors'][] = $msg;
             return $results;
        }

        // Skip header
        fgetcsv($handle);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 3) {
                $this->logger->warning("Invalid CSV row (too few columns): " . implode(',', $data));
                $results['invalid']++;
                continue;
            }

            $sku = trim($data[0]);
            $quantity = (int)$data[1];
            $price = (float)$data[2];

            // 1. Strict Validation
            if (empty($sku) || $quantity <= 0 || $price <= 0) {
                $this->logger->warning("Skipping invalid row: SKU='$sku', Qty=$quantity, Price=$price");
                $results['invalid']++;
                continue;
            }

            // 2. Idempotency Check (hash: sku + qty + price + source)
            $importRef = md5($sku . $quantity . $price . $source);
            if ($this->stockLotRepository->existsByImportReference($importRef)) {
                $this->logger->info("Skipping duplicate row (already imported): SKU='$sku', Qty=$quantity");
                $results['skipped']++;
                continue;
            }

            // 3. Create New Lot via StockManager
            try {
                $this->stockManager->addStock($sku, $quantity, $price, $price, $source, $importRef);
                $this->logger->info("Successfully imported lot: SKU='$sku', Qty=$quantity");
                $results['imported']++;
            } catch (\Exception $e) {
                $this->logger->error("Error importing SKU '$sku': " . $e->getMessage());
                $results['errors'][] = "SKU '$sku': " . $e->getMessage();
            }
        }

        fclose($handle);
        return $results;
    }

    /**
     * Exports current stock summary to a CSV file.
     * Format: sku, total_quantity, last_selling_price
     */
    public function export(string $filePath): void
    {
        $stocks = $this->stockRepository->findAll();
        $handle = fopen($filePath, 'w');

        // Header
        fputcsv($handle, ['sku', 'total_quantity', 'last_selling_price']);

        foreach ($stocks as $stock) {
            fputcsv($handle, [
                $stock->getSku(),
                $stock->getQuantity(),
                $stock->getPrice() // Stock entity stores latest price
            ]);
        }

        fclose($handle);
    }
}
