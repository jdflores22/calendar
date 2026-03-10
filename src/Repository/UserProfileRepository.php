<?php

namespace App\Repository;

use App\Entity\UserProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserProfile>
 */
class UserProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserProfile::class);
    }

    /**
     * Find profile by user ID
     */
    public function findByUserId(int $userId): ?UserProfile
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find incomplete profiles
     */
    public function findIncompleteProfiles(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isComplete = :incomplete')
            ->setParameter('incomplete', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find profiles by name pattern
     */
    public function findByNamePattern(string $pattern): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.firstName LIKE :pattern OR p.lastName LIKE :pattern')
            ->setParameter('pattern', '%' . $pattern . '%')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count complete profiles
     */
    public function countCompleteProfiles(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isComplete = :complete')
            ->setParameter('complete', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count incomplete profiles
     */
    public function countIncompleteProfiles(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isComplete = :incomplete')
            ->setParameter('incomplete', false)
            ->getQuery()
            ->getSingleScalarResult();
    }
}