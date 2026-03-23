<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Promotion;
use App\Repository\StockRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StockRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['stock:read']],
    operations: [
        new GetCollection(),
        new Get()
    ]
)]
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
    private int $quantity = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Groups(['stock:read'])]
    private float $price = 0;

    #[ORM\ManyToMany(targetEntity: Promotion::class, inversedBy: 'stocks')]
    #[ORM\JoinTable(name: "stock_promotion")]
    #[Groups(['stock:read'])]
    private Collection $promotions;

    #[ORM\Column]
    #[Groups(['stock:read'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Groups(['stock:read'])]
    private int $reserved = 0;

    public function addPromotion(Promotion $promotion): static
    {
        if (!$this->promotions->contains($promotion)) {
            $this->promotions->add($promotion);
        }

        return $this;
    }

    public function removePromotion(Promotion $promotion): static
    {
        $this->promotions->removeElement($promotion);

        return $this;
    }

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

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPrice(): float
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
        return $this->quantity > 0 ? "available" : "out_of_stock";
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
