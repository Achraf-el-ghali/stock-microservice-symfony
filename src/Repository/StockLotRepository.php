<?php

namespace App\Repository;

use App\Entity\StockLot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockLot>
 */
class StockLotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockLot::class);
    }

    /**
     * @return StockLot[]
     */
    public function findAvailableLotsBySku(string $sku): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.sku = :sku')
            ->andWhere('s.quantityRemaining > 0')
            ->setParameter('sku', $sku)
            ->orderBy('s.dateEntry', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existsByImportReference(string $reference): bool
    {
        return $this->count(['importReference' => $reference]) > 0;
    }
}
