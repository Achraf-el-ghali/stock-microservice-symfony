<?php

namespace App\Service;

use Enqueue\RdKafka\RdKafkaConnectionFactory;

class KafkaProducer
{
    public function sendProduct(array $data)
    {
        $factory = new RdKafkaConnectionFactory([
            'global' => [
                'bootstrap.servers' => 'localhost:9092',
            ],
        ]);

        $context = $factory->createContext();

        $topic = $context->createTopic('product');

        $message = $context->createMessage(json_encode($data));

        $producer = $context->createProducer();

        $producer->send($topic, $message);
    }
}
