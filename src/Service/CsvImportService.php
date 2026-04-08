<?php

namespace App\Service;

use App\Entity\Promotion;
use App\Entity\Stock;
use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;

class CsvImportService
{
    public function __construct(
        private EntityManagerInterface $em,
        private StockRepository $stockRepository,
        private KafkaProducer $producer
    ) {}

    public function importStocks(string $filePath): int
    {
        if (!file_exists($filePath)) {
            return 0;
        }

        $handle = fopen($filePath, 'r');
        fgetcsv($handle); // skip header

        $count = 0;
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 3) continue;

            $sku = $data[0];
            $quantity = (int)$data[1];
            $price = (float)$data[2];

            $stock = $this->stockRepository->findOneBy(['sku' => $sku]) ?? new Stock();
            if (!$stock->getId()) $stock->setSku($sku);

            $stock->setQuantity($quantity);
            $stock->setPrice($price);

            $this->em->persist($stock);
            $this->producer->sendProduct($sku, $stock->getFinalPrice(), $quantity, 'STOCK_ADD');
            $count++;
        }

        fclose($handle);
        $this->em->flush();

        return $count;
    }

    public function importPromotions(string $filePath): int
    {
        if (!file_exists($filePath)) {
            return 0;
        }

        $handle = fopen($filePath, 'r');
        fgetcsv($handle); // skip header

        $count = 0;
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 4) continue;

            $sku = $data[0];
            $description = $data[1];
            $type = $data[2];
            $value = (float)$data[3];

            $stock = $this->stockRepository->findOneBy(['sku' => $sku]);
            if (!$stock) continue;

            $promotion = new Promotion();
            $promotion->setDescription($description);
            $promotion->setType($type);
            $promotion->setValue($value);
            $stock->addPromotion($promotion);

            $this->em->persist($promotion);
            // Sync updated price (due to new promotion) to Kafka
            $this->producer->sendProduct($stock->getSku(), $stock->getFinalPrice(), $stock->getQuantity(), 'NEW_PROMOTION');
            $count++;
        }

        fclose($handle);
        $this->em->flush();

        return $count;
    }
}
