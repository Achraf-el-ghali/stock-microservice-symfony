<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Form\StockType;
use App\Repository\StockRepository;
use App\Service\KafkaProducer;
use App\Service\CsvImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class StockController extends AbstractController
{
    #[Route('/stock', name: 'app_stock', methods: ['GET'])]
    public function index(StockRepository $stockRepository): Response
    {
        return $this->render('stock/index.html.twig', [
            'stocks' => $stockRepository->findAll(),
        ]);
    }

    #[Route('/stock/new', name: 'app_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, KafkaProducer $producer): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($stock);
            $entityManager->flush();

            // Sync to Kafka
            $producer->sendProduct($stock->getSku(), $stock->getFinalPrice(), $stock->getQuantity());

            return $this->redirectToRoute('app_stock', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/new.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/stock/{id}/edit', name: 'app_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $entityManager, KafkaProducer $producer): Response
    {
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Sync to Kafka
            $producer->sendProduct($stock->getSku(), $stock->getFinalPrice(), $stock->getQuantity());

            return $this->redirectToRoute('app_stock', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/stock/{id}/delete', name: 'app_stock_delete', methods: ['POST'])]
    public function delete(Request $request, Stock $stock, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($stock);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_stock', [], Response::HTTP_SEE_OTHER);
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
    #[Route('/stock/import', name: 'app_stock_import', methods: ['POST'])]
    public function import(Request $request, CsvImportService $importService): Response
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $csvFile = $projectDir . '/stock.csv';

        $count = $importService->importStocks($csvFile);

        if ($count > 0) {
            $this->addFlash('success', sprintf('Successfully imported %d stocks from stock.csv', $count));
        } else {
            $this->addFlash('danger', 'Import failed or file not found.');
        }

        return $this->redirectToRoute('app_stock');
    }

    #[Route('/stock/{id}/sync', name: 'app_stock_sync', methods: ['POST'])]
    public function syncStock(Stock $stock, KafkaProducer $producer): Response
    {
        $producer->sendProduct(
            $stock->getSku(),
            $stock->getFinalPrice(),
            $stock->getQuantity()
        );

        $this->addFlash('success', sprintf('Stock %s synced to Kafka!', $stock->getSku()));

        return $this->redirectToRoute('app_stock');
    }

}

