<?php

namespace App\Repository;

use App\Entity\Form;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Form>
 */
class FormRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Form::class);
    }

    /**
     * Find forms by tag
     */
    public function findByTag(string $tag): array
    {
        return $this->createQueryBuilder('f')
            ->where('JSON_CONTAINS(f.tags, :tag) = 1')
            ->setParameter('tag', json_encode($tag))
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find forms assigned to a specific page or module
     */
    public function findByAssignment(string $assignment): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.assignedTo = :assignment')
            ->setParameter('assignment', $assignment)
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active forms created by a specific user
     */
    public function findByCreator(User $creator): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.creator = :creator')
            ->setParameter('creator', $creator)
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find forms with specific field types
     */
    public function findWithFieldType(string $fieldType): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.fields', 'field')
            ->where('field.type = :fieldType')
            ->setParameter('fieldType', $fieldType)
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->andWhere('field.isActive = :fieldActive')
            ->setParameter('fieldActive', true)
            ->distinct()
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search forms by name or description
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.name LIKE :query OR f.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get forms with field count
     */
    public function findWithFieldCount(): array
    {
        return $this->createQueryBuilder('f')
            ->select('f', 'COUNT(field.id) as fieldCount')
            ->leftJoin('f.fields', 'field', 'WITH', 'field.isActive = :fieldActive')
            ->setParameter('fieldActive', true)
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('f.id')
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find forms by multiple tags (AND condition)
     */
    public function findByTags(array $tags): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.isActive = :active')
            ->setParameter('active', true);

        foreach ($tags as $index => $tag) {
            $qb->andWhere("JSON_CONTAINS(f.tags, :tag{$index}) = 1")
               ->setParameter("tag{$index}", json_encode($tag));
        }

        return $qb->orderBy('f.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Get recently created forms
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get forms statistics
     */
    public function getStatistics(): array
    {
        $totalForms = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $totalFields = $this->createQueryBuilder('f')
            ->select('COUNT(field.id)')
            ->join('f.fields', 'field')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->andWhere('field.isActive = :fieldActive')
            ->setParameter('fieldActive', true)
            ->getQuery()
            ->getSingleScalarResult();

        $avgFieldsPerForm = $totalForms > 0 ? round($totalFields / $totalForms, 2) : 0;

        return [
            'totalForms' => (int) $totalForms,
            'totalFields' => (int) $totalFields,
            'avgFieldsPerForm' => $avgFieldsPerForm,
        ];
    }

    /**
     * Find forms that need schema migration (older versions)
     */
    public function findForSchemaMigration(string $currentVersion = '1.0'): array
    {
        return $this->createQueryBuilder('f')
            ->where('JSON_EXTRACT(f.schema, "$.version") != :version OR JSON_EXTRACT(f.schema, "$.version") IS NULL')
            ->setParameter('version', $currentVersion)
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }

    public function save(Form $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Form $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}