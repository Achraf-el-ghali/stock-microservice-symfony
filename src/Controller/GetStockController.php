<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\StockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[AsController]
#[Route('/api/stocks/{sku}', name: 'get_stock', methods: ['GET'])]
class GetStockController extends AbstractController
{
    public function __construct(
        private StockRepository $stockRepository,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $stocks = $this->stockRepository->findAll();
        $data = [];

        foreach ($stocks as $stock) {
            $data[] = [
                'sku' => $stock->getSku(),
                'price' => $stock->getPrice(),
                'quantity' => $stock->getQuantity(),
                'finalPrice' => $stock->getFinalPrice(),
            ];
        }

        return $this->json($data);
    }
}
