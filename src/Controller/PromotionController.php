<?php

namespace App\Controller;

use App\Entity\Promotion;
use App\Form\PromotionType;
use App\Repository\PromotionRepository;
use App\Service\CsvImportService;
use App\Service\KafkaProducer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/promotion')]
class PromotionController extends AbstractController
{
    #[Route('/', name: 'app_promotion_index', methods: ['GET'])]
    public function index(PromotionRepository $promotionRepository): Response
    {
        return $this->render('promotion/index.html.twig', [
            'promotions' => $promotionRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_promotion_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, KafkaProducer $producer, \App\Service\NotificationSenderService $notifier): Response
    {
        $promotion = new Promotion();
        $form = $this->createForm(PromotionType::class, $promotion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($promotion);
            $entityManager->flush();

            // Sync linked stocks to Kafka
            foreach ($promotion->getStocks() as $stock) {
                $producer->sendProduct($stock->getSku(), $stock->getFinalPrice(), $stock->getQuantity(), 'NEW_PROMOTION');
            }

            // Send notification to clients
            $notifier->sendNotification(
                sprintf("🔥 Promotion Flash ! -%d%% sur les articles liés jusqu'à ce weekend ! 🚀", $promotion->getDiscountPercentage()),
                "NEW_PROMOTION"
            );

            return $this->redirectToRoute('app_promotion_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('promotion/new.html.twig', [
            'promotion' => $promotion,
            'form' => $form,
        ]);
    }

    #[Route('/promotion/import', name: 'app_promotion_import', methods: ['POST'])]
    public function import(Request $request, CsvImportService $importService): Response
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $csvFile = $projectDir . '/promo.csv';

        $count = $importService->importPromotions($csvFile);

        if ($count > 0) {
            $this->addFlash('success', sprintf('Successfully imported %d promotions from promo.csv', $count));
        } else {
            $this->addFlash('danger', 'Import failed or file not found.');
        }

        return $this->redirectToRoute('app_promotion_index');
    }

    #[Route('/{id}/edit', name: 'app_promotion_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Promotion $promotion, EntityManagerInterface $entityManager, KafkaProducer $producer, \App\Service\NotificationSenderService $notifier): Response
    {
        $form = $this->createForm(PromotionType::class, $promotion);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Sync linked stocks to Kafka
            foreach ($promotion->getStocks() as $stock) {
                $producer->sendProduct($stock->getSku(), $stock->getFinalPrice(), $stock->getQuantity(), 'DISCOUNT_EVENT');
            }

            // Send notification to clients about update
            $notifier->sendNotification(
                sprintf("✨ Mise à jour Promotion : -%d%% de réduction ! Profitez-en maintenant !", $promotion->getDiscountPercentage()),
                "DISCOUNT_EVENT"
            );

            return $this->redirectToRoute('app_promotion_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('promotion/edit.html.twig', [
            'promotion' => $promotion,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_promotion_delete', methods: ['POST'])]
    public function delete(Request $request, Promotion $promotion, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$promotion->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($promotion);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_promotion_index', [], Response::HTTP_SEE_OTHER);
    }
}
