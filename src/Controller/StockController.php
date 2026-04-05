<?php

namespace App\Controller;

use App\Repository\StockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\KafkaProducer;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class StockController extends AbstractController
{
    #[Route('/stock', name: 'app_stock')]
    public function index(): Response
    {
        return $this->render('stock/index.html.twig', [
            'controller_name' => 'StockController',
        ]);
    }
#[Route('/api/stock/{sku}', methods: ['GET'])]
public function getStockBySku(
    string $sku, 
    StockRepository $stockRepository,
    \App\Repository\StockLotRepository $stockLotRepository
): JsonResponse
{
    $stock = $stockRepository->findOneBy(['sku' => $sku]);

    if (!$stock) {
        return $this->json(['error' => 'Product not found'], 404);
    }

    $latestLot = $stockLotRepository->findLatestLotBySku($sku);
    $price = $latestLot ? $latestLot->getSellingPrice() : 0.0;

    return $this->json([
        'sku' => $stock->getSku(),
        'price' => $price,
        'quantity' => $stock->getQuantity(),
        'isActive' => $stock->getIsActive()
    ]);
}

    #[Route('/api/stock/add-lot', methods: ['POST'])]
    public function addStockLot(
        Request $request,
        \App\Service\StockManager $stockManager,
        StockRepository $stockRepository
    ): JsonResponse {
        $content = $request->getContent();
        error_log("[StockController] addStockLot content: " . $content);
        $data = json_decode($content, true);

        $sku = $data['sku'] ?? null;
        $quantity = (int)($data['quantity'] ?? 0);
        $purchasePrice = (float)($data['purchase_price'] ?? 0);
        $sellingPrice = (float)($data['selling_price'] ?? 0);

        error_log("[StockController] addStockLot: SKU=$sku, QTY=$quantity, PP=$purchasePrice, SP=$sellingPrice");

        try {
            $stockManager->addStock(
                $sku,
                $quantity,
                $purchasePrice,
                $sellingPrice,
                'manual_entry',
                'manual_' . date('Ymd_His')
            );
            
            $stock = $stockRepository->findOneBy(['sku' => $sku]);

            return $this->json([
                'message' => 'Stock lot added successfully',
                'sku' => $sku,
                'added_quantity' => $quantity,
                'total_quantity' => $stock ? $stock->getQuantity() : 0,
                'selling_price' => $sellingPrice
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/send-product', methods: ['GET'])]
    public function sendProduct(KafkaProducer $producer): JsonResponse
    {
        $sku = "OIL-5W30-MAX-1";
        $producer->sendProduct($sku, 200, 50);

        return $this->json(["message" => "Test product sent to kafka: $sku"]);
    }
}

