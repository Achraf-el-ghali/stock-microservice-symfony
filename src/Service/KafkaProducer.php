<?php

namespace App\Service;

class KafkaProducer
{
    private $producer;

    public function __construct()
    {
        $broker = $_ENV['KAFKA_BROKER'] ?? 'kafka-catalogue:9092';
        try {
            $this->producer = \Jobcloud\Kafka\Producer\KafkaProducerBuilder::create()
                ->withAdditionalBroker($broker)
                ->build();
        } catch (\Exception $e) {
            $this->producer = null;
        }
    }

    public function sendProduct($sku, $price, $quantity, string $type = 'STOCK_ADD'): void
    {
        if (!$this->producer) {
            return;
        }

        $typeLabels = [
            'STOCK_ADD'    => 'added to',
            'STOCK_LOW'    => 'running low in',
            'STOCK_OUT'    => 'out of stock in',
            'BACK_IN_STORE'=> 'back in',
            'NEW_PROMOTION'=> 'promotion applied to',
            'DISCOUNT_EVENT'=> 'discount updated for',
        ];
        $label = $typeLabels[$type] ?? 'updated in';

        $payload = json_encode([
            'message'   => sprintf('SKU %s %s stock (price: %.2f, qty: %d)', $sku, $label, (float)$price, (int)$quantity),
            'type'      => $type,
            'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c'),
            'sku'       => $sku,
            'price'     => $price,
            'quantity'  => $quantity,
        ]);

        $kafkaMessage = \Jobcloud\Kafka\Message\KafkaProducerMessage::create('notifications', 0)
            ->withBody($payload);

        $this->producer->produce($kafkaMessage);
        $this->producer->flush(10000);
    }
}
