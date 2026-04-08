<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\StockRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['stock:read']],
    operations: [
        new GetCollection(),
        new Get(
            uriTemplate: '/stocks/{sku}',
            controller: \App\Controller\GetStockController::class,
            read: false,
            name: 'get_stock'
        )
    ]
)]
class Stock
{
    public function __construct()
    {
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[ApiProperty(identifier: false)]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[ApiProperty(identifier: true)]
    #[Groups(['stock:read'])]
    private ?string $sku = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['stock:read'])]
    private int $quantity = 0;

    #[ORM\Column]
    #[Groups(['stock:read'])]
    private bool $isActive = true;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): static
    {
        $this->sku = $sku;
        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }


    #[Groups(['stock:read'])]
    public function isInStock(): bool
    {
        return $this->quantity > 0;
    }

    #[Groups(['stock:read'])]
    public function getStockStatus(): string
    {
        return $this->quantity > 0 ? "available" : "out_of_stock";
    }


    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getReserved(): int
    {
        return $this->reserved;
    }

    public function setReserved(int $reserved): static
    {
        $this->reserved = $reserved;
        return $this;
    }
}
