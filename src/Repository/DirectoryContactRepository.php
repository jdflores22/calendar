<?php

namespace App\Repository;

use App\Entity\DirectoryContact;
use App\Entity\Office;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DirectoryContact>
 */
class DirectoryContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DirectoryContact::class);
    }

    public function save(DirectoryContact $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(DirectoryContact $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find contacts by office
     */
    public function findByOffice(Office $office): array
    {
        return $this->createQueryBuilder('dc')
            ->andWhere('dc.office = :office')
            ->setParameter('office', $office)
            ->orderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search contacts by name or position
     */
    public function searchContacts(string $query): array
    {
        return $this->createQueryBuilder('dc')
            ->leftJoin('dc.office', 'o')
            ->andWhere('dc.name LIKE :query OR dc.position LIKE :query OR o.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all contacts with their offices
     */
    public function findAllWithOffices(): array
    {
        return $this->createQueryBuilder('dc')
            ->leftJoin('dc.office', 'o')
            ->addSelect('o')
            ->orderBy('o.name', 'ASC')
            ->addOrderBy('dc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count contacts by office
     */
    public function countByOffice(Office $office): int
    {
        return $this->createQueryBuilder('dc')
            ->select('COUNT(dc.id)')
            ->andWhere('dc.office = :office')
            ->setParameter('office', $office)
            ->getQuery()
            ->getSingleScalarResult();
    }
}