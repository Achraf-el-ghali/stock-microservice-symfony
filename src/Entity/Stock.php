<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\StockRepository;
use Doctrine\ORM\Mapping as ORM;
//dire que sku est l identifiant
use ApiPlatform\Metadata\ApiProperty;
//validation
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;
//pour la serialisation de get..... et is.....
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['stock:read']],
    operations: [
        new GetCollection(),
        new Get()
    ])
]
class Stock
{

    public function __construct()
{
    $this->promotions = new ArrayCollection();
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
    private ?int $quantity = null;

    #[ORM\Column]
    #[Assert\Positive]
    #[Groups(['stock:read'])]

    private ?float $price = null;

    #[ORM\OneToMany(mappedBy: 'stock', targetEntity: Promotion::class)]
    #[Groups(['stock:read'])]
    private Collection $promotions;
    public function getPromotions(): Collection
    {
    return $this->promotions;
    }




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



#[Groups(['stock:read'])]
public function isInStock(): bool
{
    return $this->quantity > 0;
}

#[Groups(['stock:read'])]
public function getStockStatus(): string
{
    if ($this->quantity > 0) {
        return "available";
    }

    return "out_of_stock";
}
#[Groups(['stock:read'])]
public function getFinalPrice(): float
{
    $finalPrice = $this->price;

    foreach ($this->promotions as $promo) {

        if ($promo->getType() === "percentage") {
            $finalPrice -= $finalPrice * ($promo->getValue() / 100);
        }

        if ($promo->getType() === "cash") {
            $finalPrice -= $promo->getValue();
        }

    }

    return $finalPrice;
}


}
