<?php

namespace App\Service;

use App\Entity\User;

class RoleHierarchyService
{
    /**
     * Role hierarchy levels (higher number = higher authority)
     */
    private const ROLE_LEVELS = [
        'ROLE_USER' => 0,
        'ROLE_PROVINCE' => 1,
        'ROLE_DIVISION' => 2,
        'ROLE_EO' => 3,
        'ROLE_OSEC' => 4,
        'ROLE_ADMIN' => 5,
    ];

    /**
     * Get the highest role level for a user
     */
    public function getUserRoleLevel(User $user): int
    {
        $maxLevel = 0;
        foreach ($user->getRoles() as $role) {
            $level = self::ROLE_LEVELS[$role] ?? 0;
            $maxLevel = max($maxLevel, $level);
        }
        return $maxLevel;
    }

    /**
     * Check if user has higher or equal authority than target user
     */
    public function hasHigherOrEqualAuthority(User $user, User $targetUser): bool
    {
        return $this->getUserRoleLevel($user) >= $this->getUserRoleLevel($targetUser);
    }

    /**
     * Check if user has higher authority than target user
     */
    public function hasHigherAuthority(User $user, User $targetUser): bool
    {
        return $this->getUserRoleLevel($user) > $this->getUserRoleLevel($targetUser);
    }

    /**
     * Get the role name for a given level
     */
    public function getRoleNameByLevel(int $level): ?string
    {
        return array_search($level, self::ROLE_LEVELS) ?: null;
    }

    /**
     * Get all roles at or below a certain level
     */
    public function getRolesAtOrBelowLevel(int $level): array
    {
        return array_keys(array_filter(self::ROLE_LEVELS, fn($roleLevel) => $roleLevel <= $level));
    }

    /**
     * Check if a role can manage another role
     */
    public function canRoleManageRole(string $managerRole, string $targetRole): bool
    {
        $managerLevel = self::ROLE_LEVELS[$managerRole] ?? 0;
        $targetLevel = self::ROLE_LEVELS[$targetRole] ?? 0;
        
        return $managerLevel > $targetLevel;
    }

    /**
     * Get the primary role for a user (highest authority role)
     */
    public function getPrimaryRole(User $user): string
    {
        $maxLevel = 0;
        $primaryRole = 'ROLE_USER';
        
        foreach ($user->getRoles() as $role) {
            $level = self::ROLE_LEVELS[$role] ?? 0;
            if ($level > $maxLevel) {
                $maxLevel = $level;
                $primaryRole = $role;
            }
        }
        
        return $primaryRole;
    }

    /**
     * Check if user can override scheduling conflicts
     */
    public function canOverrideConflicts(User $user): bool
    {
        return $user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_OSEC');
    }

    /**
     * Check if user can manage office events
     */
    public function canManageOfficeEvents(User $user): bool
    {
        return $user->hasRole('ROLE_ADMIN') || 
               $user->hasRole('ROLE_OSEC') || 
               $user->hasRole('ROLE_EO') || 
               $user->hasRole('ROLE_DIVISION');
    }

    /**
     * Check if user can only manage their own events
     */
    public function canOnlyManageOwnEvents(User $user): bool
    {
        return $user->hasRole('ROLE_PROVINCE') && 
               !$user->hasRole('ROLE_DIVISION') && 
               !$user->hasRole('ROLE_EO') && 
               !$user->hasRole('ROLE_OSEC') && 
               !$user->hasRole('ROLE_ADMIN');
    }
}