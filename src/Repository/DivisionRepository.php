<?php

namespace App\Repository;

use App\Entity\Division;
use App\Entity\Office;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DivisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Division::class);
    }

    public function save(Division $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Division $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active divisions
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.office', 'o')
            ->addSelect('o')
            ->where('d.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('o.name', 'ASC')
            ->addOrderBy('d.displayOrder', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find divisions by office
     */
    public function findByOffice(Office $office): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.office = :office')
            ->andWhere('d.isActive = :active')
            ->setParameter('office', $office)
            ->setParameter('active', true)
            ->orderBy('d.displayOrder', 'ASC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find division by code
     */
    public function findByCode(string $code): ?Division
    {
        return $this->createQueryBuilder('d')
            ->where('d.code = :code')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
