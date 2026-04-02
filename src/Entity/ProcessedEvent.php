<?php

namespace App\Entity;

use App\Repository\ProcessedEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProcessedEventRepository::class)]
#[ORM\Table(name: 'processed_events')]
#[ORM\UniqueConstraint(name: 'unique_event_service', columns: ['event_id', 'service_name'])]
class ProcessedEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $eventId = null;

    #[ORM\Column(length: 255)]
    private ?string $serviceName = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct()
    {
        $this->processedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventId(): ?string
    {
        return $this->eventId;
    }

    public function setEventId(string $eventId): static
    {
        $this->eventId = $eventId;
        return $this;
    }

    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    public function setServiceName(string $serviceName): static
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;
        return $this;
    }
}
