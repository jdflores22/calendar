<?php

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function save(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AuditLog $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find audit logs by entity type and ID
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.entityType = :entityType')
            ->andWhere('al.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('al.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find audit logs by user
     */
    public function findByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('al')
            ->andWhere('al.user = :user')
            ->setParameter('user', $user)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent audit logs for directory operations
     */
    public function findDirectoryLogs(int $limit = 100): array
    {
        return $this->createQueryBuilder('al')
            ->leftJoin('al.user', 'u')
            ->addSelect('u')
            ->andWhere('al.entityType IN (:entityTypes)')
            ->setParameter('entityTypes', ['DirectoryContact', 'Office'])
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find audit logs by action type
     */
    public function findByAction(string $action, int $limit = 50): array
    {
        return $this->createQueryBuilder('al')
            ->leftJoin('al.user', 'u')
            ->addSelect('u')
            ->andWhere('al.action = :action')
            ->setParameter('action', $action)
            ->orderBy('al.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get audit log statistics
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('al');
        
        $totalLogs = $qb->select('COUNT(al.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $todayLogs = $qb->select('COUNT(al.id)')
            ->andWhere('al.createdAt >= :today')
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getSingleScalarResult();

        $directoryLogs = $qb->select('COUNT(al.id)')
            ->andWhere('al.entityType IN (:entityTypes)')
            ->setParameter('entityTypes', ['DirectoryContact', 'Office'])
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalLogs,
            'today' => $todayLogs,
            'directory' => $directoryLogs,
        ];
    }
}