<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\StockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[AsController]
#[Route('/api/stocks-test/{sku}', name: 'get_stock', methods: ['GET'])]
class GetStockController extends AbstractController
{
    public function __construct(
        private StockRepository $stockRepository,
        private \App\Repository\StockLotRepository $stockLotRepository,
    ) {}

    public function __invoke(string $sku): JsonResponse
    {
        $stock = $this->stockRepository->findOneBy(['sku' => $sku]);

        if (!$stock) {
            return $this->json(['error' => 'Stock not found'], 404);
        }

        $latestLot = $this->stockLotRepository->findLatestLotBySku($sku);
        $price = $latestLot ? $latestLot->getSellingPrice() : 0.0;

        $data = [
            'sku' => $stock->getSku(),
            'price' => $price,
            'quantity' => $stock->getQuantity(),
            'isActive' => $stock->getIsActive(),
        ];

        return $this->json($data);
    }
}
