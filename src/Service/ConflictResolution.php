<?php

namespace App\Service;

class ConflictResolution
{
    public const RESOLUTION_ALLOWED = 'allowed';
    public const RESOLUTION_WARNING = 'warning';
    public const RESOLUTION_BLOCKED = 'blocked';

    public function __construct(
        private string $resolution,
        private string $message,
        private array $conflicts = []
    ) {}

    public function getResolution(): string
    {
        return $this->resolution;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    public function isAllowed(): bool
    {
        return $this->resolution === self::RESOLUTION_ALLOWED;
    }

    public function isWarning(): bool
    {
        return $this->resolution === self::RESOLUTION_WARNING;
    }

    public function isBlocked(): bool
    {
        return $this->resolution === self::RESOLUTION_BLOCKED;
    }

    public function hasConflicts(): bool
    {
        return !empty($this->conflicts);
    }

    public function getConflictCount(): int
    {
        return count($this->conflicts);
    }

    public function toArray(): array
    {
        return [
            'resolution' => $this->resolution,
            'message' => $this->message,
            'conflicts' => $this->conflicts,
            'conflict_count' => $this->getConflictCount(),
            'has_conflicts' => $this->hasConflicts(),
        ];
    }
}