<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Jobcloud\Kafka\Producer\KafkaProducerBuilder;
use Jobcloud\Kafka\Message\KafkaProducerMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use DateTime;
use Exception;


/**
 * Unified Kafka notification publisher for the Stock microservice.
 * Publishes to the 'notifications_raw' topic consumed by Spring Boot.
 */
class NotificationSenderService
{
    private HttpClientInterface $client;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Publish a notification event to the Spring Boot relay via REST.
     * Standard format: {"message": "...", "type": "...", "origin": "STOCK_SERVICE", "timestamp": "..."}
     *
     * @param string $message  The human-readable notification content.
     * @param string $type     Event type: STOCK_ADD, STOCK_LOW, etc.
     */
    public function sendNotification(string $message, string $type): void
    {
        try {
            $this->client->request('POST', 'http://spring-boot-app:8085/api/notifications/receive', [
                'json' => [
                    'message'   => $message,
                    'type'      => $type,
                    'origin'    => 'STOCK_SERVICE',
                    'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
                ],
            ]);

            $this->logger->info('[REST] Notification envoyée au relais Spring Boot', [
                'type'    => $type,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[REST] Erreur envoi notification : ' . $e->getMessage());
        }
    }
}
