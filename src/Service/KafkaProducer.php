<?php

namespace App\Service;

class KafkaProducer
{
    public function sendProduct($sku, $price, $quantity)
    {
        $message = json_encode([
            "sku" => $sku,
            "price" => $price,
            "quantity" => $quantity
        ]);

        $cmd = "echo '$message' | docker exec -i symfony2026-kafka-1 kafka-console-producer --topic stock.products --bootstrap-server localhost:9092";

        exec($cmd);
    }
}
