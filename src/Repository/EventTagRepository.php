<?php

namespace App\Repository;

use App\Entity\EventTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventTag>
 */
class EventTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventTag::class);
    }

    /**
     * Find tag by name
     */
    public function findByName(string $name): ?EventTag
    {
        return $this->findOneBy(['name' => trim($name)]);
    }

    /**
     * Find or create a tag by name
     */
    public function findOrCreateByName(string $name): EventTag
    {
        $tag = $this->findByName($name);
        
        if (!$tag) {
            $tag = new EventTag();
            $tag->setName($name);
            $this->getEntityManager()->persist($tag);
        }
        
        return $tag;
    }

    /**
     * Find popular tags (most used)
     */
    public function findPopularTags(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'COUNT(e.id) as eventCount')
            ->leftJoin('t.events', 'e')
            ->groupBy('t.id')
            ->orderBy('eventCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search tags by name
     */
    public function searchByName(string $query): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unused tags (tags with no events)
     */
    public function findUnusedTags(): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.events', 'e')
            ->where('e.id IS NULL')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}