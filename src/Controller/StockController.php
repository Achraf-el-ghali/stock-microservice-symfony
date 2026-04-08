<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Form\StockType;
use App\Repository\StockRepository;
use App\Service\KafkaProducer;
use App\Service\CsvImportService;
use App\Service\NotificationSenderService;
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
    public function new(Request $request, EntityManagerInterface $entityManager, KafkaProducer $producer, NotificationSenderService $notifier): Response
    {
        $stock = new Stock();
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->persist($stock);
                $entityManager->flush();
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur : Le SKU "' . $stock->getSku() . '" existe déjà dans le système.');
                return $this->render('stock/new.html.twig', [
                    'stock' => $stock,
                    'form' => $form,
                ]);
            }

            // Sync to Kafka
            $producer->sendProduct($stock->getSku(), $stock->getFinalPrice(), $stock->getQuantity());

            // Envoi d'une notification temps réel !
            $notifier->sendNotification("Nouveau produit SKU: " . $stock->getSku() . " ajouté au stock.", "STOCK_ADD");

            return $this->redirectToRoute('app_stock', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/new.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/stock/{id}/edit', name: 'app_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Stock $stock, EntityManagerInterface $entityManager, KafkaProducer $producer, NotificationSenderService $notifier): Response
    {
        $form = $this->createForm(StockType::class, $stock);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get original quantity to check for 'Back in stock'
            $originalQuantity = $entityManager->getUnitOfWork()->getOriginalEntityData($stock)['quantity'] ?? 0;
            
            $entityManager->flush();

            // Sync to Kafka
            $producer->sendProduct($stock->getSku(), $stock->getFinalPrice(), $stock->getQuantity());

            // Envoi de l'alerte temps réel 
            if ($originalQuantity === 0 && $stock->getQuantity() > 0) {
                $notifier->sendNotification("L'article " . $stock->getSku() . " est de nouveau en stock, profitez-en vite !", "BACK_IN_STORE");
            } elseif ($stock->getQuantity() < 10 && $stock->getQuantity() > 0) {
                $notifier->sendNotification("⚠️ STOCK FAIBLE : seulement " . $stock->getQuantity() . " unités pour " . $stock->getSku(), "STOCK_LOW");
            } elseif ($stock->getQuantity() == 0) {
                $notifier->sendNotification("🛑 RUPTURE DE STOCK pour " . $stock->getSku() . " !", "STOCK_OUT");
            } else {
                $notifier->sendNotification("Mise à jour du stock : " . $stock->getSku() . " a maintenant " . $stock->getQuantity() . " unités.", "STOCK_INFO");
            }

            return $this->redirectToRoute('app_stock', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('stock/edit.html.twig', [
            'stock' => $stock,
            'form' => $form,
        ]);
    }

    #[Route('/stock/{id}/delete', name: 'app_stock_delete', methods: ['POST'])]
    public function delete(Request $request, Stock $stock, EntityManagerInterface $entityManager, NotificationSenderService $notifier): Response
    {
        if ($this->isCsrfTokenValid('delete'.$stock->getId(), $request->getPayload()->getString('_token'))) {
            $sku = $stock->getSku();
            $entityManager->remove($stock);
            $entityManager->flush();
            
            $notifier->sendNotification("Le produit sku: " . $sku . " a été supprimé de l'inventaire !", "STOCK_ALERT");
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

