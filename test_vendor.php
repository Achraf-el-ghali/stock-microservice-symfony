<?php
require 'vendor/autoload.php';
use ApiPlatform\Hydra\Serializer\HydraPrefixNameConverter;
use ApiPlatform\JsonLd\ContextBuilder;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

echo "Starting vendor test...\n";

try {
    if (!class_exists(ContextBuilder::class)) {
        throw new \Exception("ContextBuilder class not found!");
    }
    echo "ContextBuilder found.\n";

    if (!class_exists(HydraPrefixNameConverter::class)) {
        throw new \Exception("HydraPrefixNameConverter class not found!");
    }
    echo "HydraPrefixNameConverter found.\n";

    $mockNameConverter = new class implements NameConverterInterface {
        public function normalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string { return $propertyName; }
        public function denormalize(string $propertyName, ?string $class = null, ?string $format = null, array $context = []): string { return $propertyName; }
    };

    $hydraConverter = new HydraPrefixNameConverter($mockNameConverter);
    echo "HydraPrefixNameConverter instantiated successfully.\n";

    echo "RESULT: SUCCESS\n";
} catch (\Throwable $e) {
    echo "RESULT: FAILURE\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
