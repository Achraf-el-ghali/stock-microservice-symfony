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

    public function sendProduct($sku, $price, $quantity)
    {
        if (!$this->producer) {
            return;
        }

        $message = json_encode([
            'sku'      => $sku,
            'price'    => $price,
            'quantity' => $quantity,
        ]);

        $kafkaMessage = \Jobcloud\Kafka\Message\KafkaProducerMessage::create('stock.products', 0)
            ->withBody($message);

        $this->producer->produce($kafkaMessage);
        $this->producer->flush(10000);
    }
}
