<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\EventAttachment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventAttachment>
 */
class EventAttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventAttachment::class);
    }

    /**
     * Find attachments for a specific event
     */
    public function findByEvent(Event $event): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.event = :event')
            ->setParameter('event', $event)
            ->orderBy('a.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attachments uploaded by a specific user
     */
    public function findByUploader(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.uploadedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('a.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attachments by mime type
     */
    public function findByMimeType(string $mimeType): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.mimeType = :mimeType')
            ->setParameter('mimeType', $mimeType)
            ->orderBy('a.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find image attachments
     */
    public function findImages(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.mimeType LIKE :imageType')
            ->setParameter('imageType', 'image/%')
            ->orderBy('a.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find document attachments
     */
    public function findDocuments(): array
    {
        $documentMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
        ];

        return $this->createQueryBuilder('a')
            ->where('a.mimeType IN (:mimeTypes)')
            ->setParameter('mimeTypes', $documentMimeTypes)
            ->orderBy('a.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total storage size used by attachments
     */
    public function getTotalStorageSize(): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('SUM(a.fileSize)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Get storage size used by a specific user
     */
    public function getStorageSizeByUser(User $user): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('SUM(a.fileSize)')
            ->where('a.uploadedBy = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? 0;
    }

    /**
     * Find large attachments (above specified size in bytes)
     */
    public function findLargeAttachments(int $minSize = 10485760): array // Default 10MB
    {
        return $this->createQueryBuilder('a')
            ->where('a.fileSize >= :minSize')
            ->setParameter('minSize', $minSize)
            ->orderBy('a.fileSize', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find attachments uploaded within a date range
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.uploadedAt >= :start AND a.uploadedAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}