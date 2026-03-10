<?php

namespace App\Repository;

use App\Entity\OfficeCluster;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OfficeClusterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OfficeCluster::class);
    }

    public function save(OfficeCluster $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(OfficeCluster $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active clusters ordered by display order
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.displayOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all clusters with their offices
     */
    public function findAllWithOffices(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.offices', 'o')
            ->addSelect('o')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.displayOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find cluster by code
     */
    public function findByCode(string $code): ?OfficeCluster
    {
        return $this->createQueryBuilder('c')
            ->where('c.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get clusters with office count
     */
    public function findAllWithCounts(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.offices', 'o')
            ->addSelect('COUNT(o.id) as officeCount')
            ->groupBy('c.id')
            ->orderBy('c.displayOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
