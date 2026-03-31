<?php

namespace App\Entity;

use App\Repository\StockLotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockLotRepository::class)]
#[ORM\Index(name: 'idx_sku_date', columns: ['sku', 'date_entry'])]
class StockLot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $sku = null;

    #[ORM\Column]
    private int $quantityInitial = 0;

    #[ORM\Column]
    private int $quantityRemaining = 0;

    #[ORM\Column]
    private float $purchasePrice = 0;

    #[ORM\Column]
    private float $sellingPrice = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateEntry = null;

    #[ORM\Column(length: 64, unique: true, nullable: true)]
    private ?string $importReference = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getQuantityInitial(): int
    {
        return $this->quantityInitial;
    }

    public function setQuantityInitial(int $quantityInitial): static
    {
        $this->quantityInitial = $quantityInitial;

        return $this;
    }

    public function getQuantityRemaining(): int
    {
        return $this->quantityRemaining;
    }

    public function setQuantityRemaining(int $quantityRemaining): static
    {
        $this->quantityRemaining = $quantityRemaining;

        return $this;
    }

    public function getPurchasePrice(): float
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(float $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getSellingPrice(): float
    {
        return $this->sellingPrice;
    }

    public function setSellingPrice(float $sellingPrice): static
    {
        $this->sellingPrice = $sellingPrice;

        return $this;
    }

    public function getDateEntry(): ?\DateTimeInterface
    {
        return $this->dateEntry;
    }

    public function setDateEntry(\DateTimeInterface $dateEntry): static
    {
        $this->dateEntry = $dateEntry;

        return $this;
    }

    public function getImportReference(): ?string
    {
        return $this->importReference;
    }

    public function setImportReference(?string $importReference): static
    {
        $this->importReference = $importReference;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
