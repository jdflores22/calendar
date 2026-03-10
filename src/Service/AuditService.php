<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogRepository $auditLogRepository,
        private RequestStack $requestStack,
        private Security $security
    ) {
    }

    /**
     * Log an audit event
     */
    public function log(
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?User $user = null
    ): AuditLog {
        $auditLog = new AuditLog();
        $auditLog->setAction($action);
        $auditLog->setEntityType($entityType);
        $auditLog->setEntityId($entityId);
        $auditLog->setOldValues($oldValues);
        $auditLog->setNewValues($newValues);
        $auditLog->setDescription($description);

        // Set user (current user if not provided)
        $currentUser = $user ?? $this->security->getUser();
        if ($currentUser instanceof User) {
            $auditLog->setUser($currentUser);
        }

        // Set request information
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $auditLog->setIpAddress($request->getClientIp());
            $auditLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->auditLogRepository->save($auditLog, true);

        return $auditLog;
    }

    /**
     * Log directory contact creation
     */
    public function logContactCreated(object $contact, ?User $user = null): AuditLog
    {
        return $this->log(
            'CONTACT_CREATED',
            'DirectoryContact',
            $contact->getId(),
            null,
            $this->extractContactData($contact),
            "Created directory contact: {$contact->getName()}",
            $user
        );
    }

    /**
     * Log directory contact update
     */
    public function logContactUpdated(object $contact, array $oldValues, ?User $user = null): AuditLog
    {
        return $this->log(
            'CONTACT_UPDATED',
            'DirectoryContact',
            $contact->getId(),
            $oldValues,
            $this->extractContactData($contact),
            "Updated directory contact: {$contact->getName()}",
            $user
        );
    }

    /**
     * Log directory contact deletion
     */
    public function logContactDeleted(object $contact, ?User $user = null): AuditLog
    {
        return $this->log(
            'CONTACT_DELETED',
            'DirectoryContact',
            $contact->getId(),
            $this->extractContactData($contact),
            null,
            "Deleted directory contact: {$contact->getName()}",
            $user
        );
    }

    /**
     * Log office creation
     */
    public function logOfficeCreated(object $office, ?User $user = null): AuditLog
    {
        return $this->log(
            'OFFICE_CREATED',
            'Office',
            $office->getId(),
            null,
            $this->extractOfficeData($office),
            "Created office: {$office->getName()}",
            $user
        );
    }

    /**
     * Log office update
     */
    public function logOfficeUpdated(object $office, array $oldValues, ?User $user = null): AuditLog
    {
        return $this->log(
            'OFFICE_UPDATED',
            'Office',
            $office->getId(),
            $oldValues,
            $this->extractOfficeData($office),
            "Updated office: {$office->getName()}",
            $user
        );
    }

    /**
     * Log office deletion
     */
    public function logOfficeDeleted(object $office, ?User $user = null): AuditLog
    {
        return $this->log(
            'OFFICE_DELETED',
            'Office',
            $office->getId(),
            $this->extractOfficeData($office),
            null,
            "Deleted office: {$office->getName()}",
            $user
        );
    }

    /**
     * Get audit logs for an entity
     */
    public function getEntityLogs(string $entityType, int $entityId): array
    {
        return $this->auditLogRepository->findByEntity($entityType, $entityId);
    }

    /**
     * Get recent directory audit logs
     */
    public function getDirectoryLogs(int $limit = 100): array
    {
        return $this->auditLogRepository->findDirectoryLogs($limit);
    }

    /**
     * Get audit statistics
     */
    public function getStatistics(): array
    {
        return $this->auditLogRepository->getStatistics();
    }

    /**
     * Extract contact data for audit logging
     */
    private function extractContactData(object $contact): array
    {
        return [
            'name' => $contact->getName(),
            'position' => $contact->getPosition(),
            'email' => $contact->getEmail(),
            'phone' => $contact->getPhone(),
            'address' => $contact->getAddress(),
            'office_id' => $contact->getOffice()?->getId(),
            'office_name' => $contact->getOffice()?->getName(),
        ];
    }

    /**
     * Extract office data for audit logging
     */
    private function extractOfficeData(object $office): array
    {
        return [
            'name' => $office->getName(),
            'code' => $office->getCode(),
            'color' => $office->getColor(),
            'description' => $office->getDescription(),
            'parent_id' => $office->getParent()?->getId(),
            'parent_name' => $office->getParent()?->getName(),
        ];
    }
}