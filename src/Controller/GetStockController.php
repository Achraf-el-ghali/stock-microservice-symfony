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
    ) {}

    public function __invoke(string $sku): JsonResponse
    {
        $stock = $this->stockRepository->findOneBy(['sku' => $sku]);

        if (!$stock) {
            return $this->json(['error' => 'Stock not found'], 404);
        }

        $data = [
            'sku' => $stock->getSku(),
            'price' => $stock->getPrice(),
            'quantity' => $stock->getQuantity(),
            'finalPrice' => $stock->getFinalPrice(),
        ];

        return $this->json($data);
    }
}
