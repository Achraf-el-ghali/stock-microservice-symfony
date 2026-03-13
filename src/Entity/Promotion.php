<?php

namespace App\Entity;

use App\Repository\PromotionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;


#[ORM\Entity(repositoryClass: PromotionRepository::class)]
class Promotion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    //#[ORM\Column(nullable: true)]
    //private ?float $percentage = null;



    #[ORM\ManyToOne(inversedBy: 'promotions')]
    private ?Stock $stock = null;

    #[ORM\Column(length:255)]
    #[Groups(['stock:read'])]
    private ?string $description = null;

    #[ORM\Column(length:50)]
    #[Groups(['stock:read'])]
    private ?string $type = null;

    #[ORM\Column]
    #[Groups(['stock:read'])]
    private ?float $value = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    // public function getPercentage(): ?float
    // {
    //     return $this->percentage;
    // }

    // public function setPercentage(?float $percentage): static
    // {
    //     $this->percentage = $percentage;

    //     return $this;
    // }

    public function getStock(): ?Stock
    {
        return $this->stock;
    }

    public function setStock(?Stock $stock): static
    {
        $this->stock = $stock;

        return $this;
    }
   public function getDescription(): ?string
{
    return $this->description;
}

public function getType(): ?string
{
    return $this->type;
}

public function getValue(): ?float
{
    return $this->value;
}
public function setDescription(string $description): static
{
    $this->description = $description;
    return $this;
}

public function setType(string $type): static
{
    $this->type = $type;
    return $this;
}

public function setValue(float $value): static
{
    $this->value = $value;
    return $this;
}
}
