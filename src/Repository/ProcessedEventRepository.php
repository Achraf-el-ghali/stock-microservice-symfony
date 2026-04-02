<?php

namespace App\Repository;

use App\Entity\ProcessedEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProcessedEvent>
 */
class ProcessedEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProcessedEvent::class);
    }

    public function isProcessed(string $eventId, string $serviceName): bool
    {
        return $this->findOneBy([
            'eventId' => $eventId,
            'serviceName' => $serviceName
        ]) !== null;
    }
}
