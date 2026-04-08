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
            // Fire-and-forget: do NOT call ->getContent() or ->getStatusCode()
            // Symfony HttpClient is lazy — the request is only sent when the
            // response is consumed. We intentionally never consume it so the
            // HTTP call runs in the background and never blocks the response.
            $this->client->request('POST', 'http://spring-boot-app:8085/api/notifications/receive', [
                'json' => [
                    'message'   => $message,
                    'type'      => $type,
                    'origin'    => 'STOCK_SERVICE',
                    'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
                ],
                'timeout'         => 2,   // give up after 2 s if Spring Boot is slow
                'max_duration'    => 2,
            ]);

            $this->logger->info('[REST] Notification envoyée au relais Spring Boot', [
                'type'    => $type,
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            // Never let a failed notification block or crash the stock operation
            $this->logger->warning('[REST] Notification non envoyée (Spring Boot indisponible) : ' . $e->getMessage());
        }
    }
}
