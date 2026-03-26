<?php

namespace App\Service;

class KafkaProducer
{
    private $producer;

    public function __construct()
    {
        try {
            $this->producer = \Jobcloud\Kafka\Producer\KafkaProducerBuilder::create()
                ->withAdditionalBroker('kafka:9092')
                ->build();
        } catch (\Exception $e) {
            $this->producer = null;
        }
    }

    public function sendProduct($sku, $price, $quantity)
    {
        $message = json_encode([
            "sku" => $sku,
            "price" => $price,
            "quantity" => $quantity
        ]);

        $kafkaMessage = \Jobcloud\Kafka\Message\KafkaProducerMessage::create('stock.products', 0)
            ->withBody($message);

        $this->producer->produce($kafkaMessage);
        $this->producer->flush(10000);

        // Old command (shell exec):
        // $cmd = "echo '$message' | docker exec -i symfony2026-kafka-1 kafka-console-producer --topic stock.products --bootstrap-server localhost:9092";
        // exec($cmd);
    }
}
