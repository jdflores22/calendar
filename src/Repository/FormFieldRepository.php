<?php

namespace App\Repository;

use App\Entity\Form;
use App\Entity\FormField;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormField>
 */
class FormFieldRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormField::class);
    }

    /**
     * Find fields by form ordered by sort order
     */
    public function findByFormOrdered(Form $form): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.form = :form')
            ->setParameter('form', $form)
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.sortOrder', 'ASC')
            ->addOrderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find fields by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.type = :type')
            ->setParameter('type', $type)
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.form', 'ASC')
            ->addOrderBy('f.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find required fields for a form
     */
    public function findRequiredByForm(Form $form): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.form = :form')
            ->setParameter('form', $form)
            ->andWhere('f.isRequired = :required')
            ->setParameter('required', true)
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find fields with specific validation rules
     */
    public function findWithValidationRule(string $rule): array
    {
        return $this->createQueryBuilder('f')
            ->where('JSON_EXTRACT(f.validationRules, :rule) IS NOT NULL')
            ->setParameter('rule', '$.' . $rule)
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.form', 'ASC')
            ->addOrderBy('f.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the next sort order for a form
     */
    public function getNextSortOrder(Form $form): int
    {
        $result = $this->createQueryBuilder('f')
            ->select('MAX(f.sortOrder)')
            ->where('f.form = :form')
            ->setParameter('form', $form)
            ->getQuery()
            ->getSingleScalarResult();

        return ($result ?? 0) + 1;
    }

    /**
     * Reorder fields in a form
     */
    public function reorderFields(Form $form, array $fieldIds): void
    {
        $em = $this->getEntityManager();
        
        foreach ($fieldIds as $index => $fieldId) {
            $field = $this->find($fieldId);
            if ($field && $field->getForm() === $form) {
                $field->setSortOrder($index + 1);
                $em->persist($field);
            }
        }
        
        $em->flush();
    }

    /**
     * Find fields that support options (select, radio, checkbox)
     */
    public function findWithOptions(Form $form = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.type IN (:types)')
            ->setParameter('types', [FormField::TYPE_SELECT, FormField::TYPE_RADIO, FormField::TYPE_CHECKBOX])
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true);

        if ($form) {
            $qb->andWhere('f.form = :form')
               ->setParameter('form', $form);
        }

        return $qb->orderBy('f.form', 'ASC')
                  ->addOrderBy('f.sortOrder', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Search fields by name or label
     */
    public function search(string $query, Form $form = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.name LIKE :query OR f.label LIKE :query OR f.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true);

        if ($form) {
            $qb->andWhere('f.form = :form')
               ->setParameter('form', $form);
        }

        return $qb->orderBy('f.form', 'ASC')
                  ->addOrderBy('f.sortOrder', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Get field type statistics
     */
    public function getTypeStatistics(): array
    {
        $results = $this->createQueryBuilder('f')
            ->select('f.type, COUNT(f.id) as count')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('f.type')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $statistics = [];
        foreach ($results as $result) {
            $statistics[$result['type']] = (int) $result['count'];
        }

        return $statistics;
    }

    /**
     * Find fields with default values
     */
    public function findWithDefaultValues(Form $form = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.defaultValue IS NOT NULL')
            ->andWhere('f.defaultValue != :empty')
            ->setParameter('empty', '')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true);

        if ($form) {
            $qb->andWhere('f.form = :form')
               ->setParameter('form', $form);
        }

        return $qb->orderBy('f.form', 'ASC')
                  ->addOrderBy('f.sortOrder', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Duplicate field to another form
     */
    public function duplicateField(FormField $field, Form $targetForm): FormField
    {
        $newField = new FormField();
        $newField->setName($field->getName())
                 ->setLabel($field->getLabel())
                 ->setType($field->getType())
                 ->setDescription($field->getDescription())
                 ->setPlaceholder($field->getPlaceholder())
                 ->setDefaultValue($field->getDefaultValue())
                 ->setRequired($field->isRequired())
                 ->setActive($field->isActive())
                 ->setSortOrder($this->getNextSortOrder($targetForm))
                 ->setOptions($field->getOptions())
                 ->setValidationRules($field->getValidationRules())
                 ->setAttributes($field->getAttributes())
                 ->setForm($targetForm);

        $this->getEntityManager()->persist($newField);
        $this->getEntityManager()->flush();

        return $newField;
    }

    /**
     * Bulk update field properties
     */
    public function bulkUpdate(array $fieldIds, array $properties): int
    {
        $qb = $this->createQueryBuilder('f')
            ->update()
            ->where('f.id IN (:ids)')
            ->setParameter('ids', $fieldIds);

        foreach ($properties as $property => $value) {
            $qb->set("f.{$property}", ":{$property}")
               ->setParameter($property, $value);
        }

        return $qb->getQuery()->execute();
    }

    public function save(FormField $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FormField $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}