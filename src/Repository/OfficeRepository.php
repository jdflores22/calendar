<?php

namespace App\Repository;

use App\Entity\Office;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Office>
 */
class OfficeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Office::class);
    }

    /**
     * Find office by code
     */
    public function findByCode(string $code): ?Office
    {
        return $this->findOneBy(['code' => strtoupper($code)]);
    }

    /**
     * Find office by color
     */
    public function findByColor(string $color): ?Office
    {
        // Ensure color starts with # and is uppercase
        if (!str_starts_with($color, '#')) {
            $color = '#' . $color;
        }
        return $this->findOneBy(['color' => strtoupper($color)]);
    }

    /**
     * Find all root offices (offices with no parent)
     */
    public function findRootOffices(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.parent IS NULL')
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all child offices of a parent
     */
    public function findChildOffices(Office $parent): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find offices by depth level
     */
    public function findByDepthLevel(int $level): array
    {
        if ($level === 0) {
            return $this->findRootOffices();
        }

        // For deeper levels, we need to use a more complex query
        $qb = $this->createQueryBuilder('o');
        
        for ($i = 1; $i <= $level; $i++) {
            $qb->join('o.parent', "p{$i}");
        }
        
        $qb->where("p{$level}.parent IS NULL")
           ->orderBy('o.name', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Check if a color is already in use by another office
     */
    public function isColorInUse(string $color, ?Office $excludeOffice = null): bool
    {
        // Ensure color starts with # and is uppercase
        if (!str_starts_with($color, '#')) {
            $color = '#' . $color;
        }
        $color = strtoupper($color);

        $qb = $this->createQueryBuilder('o')
            ->where('o.color = :color')
            ->setParameter('color', $color);

        if ($excludeOffice) {
            $qb->andWhere('o.id != :excludeId')
               ->setParameter('excludeId', $excludeOffice->getId());
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * Check if a code is already in use by another office
     */
    public function isCodeInUse(string $code, ?Office $excludeOffice = null): bool
    {
        $code = strtoupper($code);

        $qb = $this->createQueryBuilder('o')
            ->where('o.code = :code')
            ->setParameter('code', $code);

        if ($excludeOffice) {
            $qb->andWhere('o.id != :excludeId')
               ->setParameter('excludeId', $excludeOffice->getId());
        }

        return $qb->getQuery()->getOneOrNullResult() !== null;
    }

    /**
     * Get all used colors
     */
    public function getAllUsedColors(): array
    {
        return $this->createQueryBuilder('o')
            ->select('o.color')
            ->where('o.color IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Get all used codes
     */
    public function getAllUsedCodes(): array
    {
        return $this->createQueryBuilder('o')
            ->select('o.code')
            ->where('o.code IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Find offices with users count
     */
    public function findWithUserCounts(): array
    {
        return $this->createQueryBuilder('o')
            ->select('o', 'COUNT(u.id) as userCount')
            ->leftJoin('o.users', 'u')
            ->groupBy('o.id')
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find offices with event counts
     */
    public function findWithEventCounts(): array
    {
        return $this->createQueryBuilder('o')
            ->select('o', 'COUNT(e.id) as eventCount')
            ->leftJoin('o.events', 'e')
            ->groupBy('o.id')
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search offices by name or code
     */
    public function searchByNameOrCode(string $query): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.name LIKE :query OR o.code LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search offices (alias for searchByNameOrCode for API consistency)
     */
    public function searchOffices(string $query): array
    {
        return $this->searchByNameOrCode($query);
    }

    /**
     * Get office hierarchy as a tree structure
     */
    public function getHierarchyTree(): array
    {
        $offices = $this->findAll();
        $tree = [];
        $indexed = [];

        // Index all offices by ID
        foreach ($offices as $office) {
            $indexed[$office->getId()] = [
                'office' => $office,
                'children' => []
            ];
        }

        // Build the tree structure
        foreach ($offices as $office) {
            if ($office->getParent() === null) {
                // Root office
                $tree[] = &$indexed[$office->getId()];
            } else {
                // Child office
                $parentId = $office->getParent()->getId();
                if (isset($indexed[$parentId])) {
                    $indexed[$parentId]['children'][] = &$indexed[$office->getId()];
                }
            }
        }

        return $tree;
    }
}