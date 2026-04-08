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

