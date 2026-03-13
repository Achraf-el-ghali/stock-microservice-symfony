<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\StockRepository;
use Doctrine\ORM\Mapping as ORM;
//dire que sku est l identifiant
use ApiPlatform\Metadata\ApiProperty;
//validation
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ApiResource]
class Stock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]

    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[ApiProperty(identifier: true)]

    private ?string $sku = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Assert\Positive]

    private ?float $price = null;

    #[ORM\Column]
    #[Assert\Positive]
    private ?float $promo = null;

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

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }
    public function getPromo(): ?float
{
    return $this->promo;
}

public function setPromo(float $promo): static
{
    $this->promo = $promo;

    return $this;
}

public function getFinalPrice(): float
{
    if ($this->promo === null) {
        return $this->price;
    }

    return $this->price - ($this->price * $this->promo / 100);
}
}
