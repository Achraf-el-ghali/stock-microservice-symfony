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
public function getStockBySku(string $sku, StockRepository $stockRepository): JsonResponse
{
    $stock = $stockRepository->findOneBy(['sku' => $sku]);

    if (!$stock) {
        return $this->json(['error' => 'Product not found'], 404);
    }

    return $this->json([
        'sku' => $stock->getSku(),
        'price' => $stock->getPrice(),
        'quantity' => $stock->getQuantity(),
        'promo' => $stock->getPromotions(),
        'finalPrice' => $stock->getFinalPrice(),
        'isActive' => $stock->getIsActive()
    ]);
}

#[Route('/api/stock/{sku}/price', methods: ['PATCH'])]
public function updateStockPrice(
    string $sku,
    Request $request,
    StockRepository $stockRepository,
    EntityManagerInterface $em
): JsonResponse {
    $stock = $stockRepository->findOneBy(['sku' => $sku]);

    if (!$stock) {
        return $this->json(['error' => 'Product not found'], 404);
    }

    $body = json_decode($request->getContent(), true);

    if (isset($body['price'])) {
        $stock->setPrice((float) $body['price']);
    }
    if (isset($body['quantity'])) {
        $stock->setQuantity((int) $body['quantity']);
    }

    $em->flush();

    return $this->json([
        'sku'        => $stock->getSku(),
        'price'      => $stock->getPrice(),
        'quantity'   => $stock->getQuantity(),
        'finalPrice' => $stock->getFinalPrice(),
        'isActive'   => $stock->getIsActive(),
    ]);
}
#[Route('/send-product')]
public function sendProduct(KafkaProducer $producer)
{
    $data = [
        "sku" => "OIL5W30",
        "quantity" => 50,
        "price" => 200
    ];

    $producer->sendProduct(
        $data['sku'],
        $data['price'],
        $data['quantity']
    );

    return $this->json(["message" => "product sent to kafka"]);
}

}

