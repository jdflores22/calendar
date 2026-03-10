<?php

namespace App\Security\Voter;

use App\Entity\Office;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class OfficeVoter extends Voter
{
    public const VIEW = 'OFFICE_VIEW';
    public const CREATE = 'OFFICE_CREATE';
    public const EDIT = 'OFFICE_EDIT';
    public const DELETE = 'OFFICE_DELETE';
    public const MANAGE_COLORS = 'OFFICE_MANAGE_COLORS';
    public const ASSIGN_USERS = 'OFFICE_ASSIGN_USERS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::MANAGE_COLORS,
            self::ASSIGN_USERS
        ]) && ($subject instanceof Office || $subject === null);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::CREATE => $this->canCreate($user),
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            self::MANAGE_COLORS => $this->canManageColors($user),
            self::ASSIGN_USERS => $this->canAssignUsers($user),
            default => false,
        };
    }

    private function canView(?Office $office, User $user): bool
    {
        // All authenticated users can view all offices (for transparency)
        return true;
    }

    private function canCreate(User $user): bool
    {
        // Only Admin can create new offices
        return $user->hasRole('ROLE_ADMIN');
    }

    private function canEdit(?Office $office, User $user): bool
    {
        if (!$office) {
            return false;
        }

        // Admin can edit all offices
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        // OSEC can edit office details but not create/delete
        if ($user->hasRole('ROLE_OSEC')) {
            return true;
        }

        return false;
    }

    private function canDelete(?Office $office, User $user): bool
    {
        if (!$office) {
            return false;
        }

        // Only Admin can delete offices
        if ($user->hasRole('ROLE_ADMIN')) {
            // Cannot delete office if it has users assigned
            return $office->getUsers()->isEmpty();
        }

        return false;
    }

    private function canManageColors(User $user): bool
    {
        // Admin and OSEC can manage office colors
        return $user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_OSEC');
    }

    private function canAssignUsers(User $user): bool
    {
        // Only Admin can assign users to offices
        return $user->hasRole('ROLE_ADMIN');
    }
}