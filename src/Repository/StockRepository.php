<?php

namespace App\Repository;

use App\Entity\Stock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Stock>
 */
class StockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Stock::class);
    }

    /**
     * Finds a Stock summary record by SKU and applies a pessimistic write lock.
     */
    public function findOneBySkuWithLock(string $sku): ?Stock
    {
        return $this->getEntityManager()->createQuery(
            'SELECT s FROM App\Entity\Stock s WHERE s.sku = :sku'
        )
        ->setParameter('sku', $sku)
        ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
        ->getOneOrNullResult();
    }
}
