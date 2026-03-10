<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    public const VIEW = 'USER_VIEW';
    public const CREATE = 'USER_CREATE';
    public const EDIT = 'USER_EDIT';
    public const DELETE = 'USER_DELETE';
    public const MANAGE_ROLES = 'USER_MANAGE_ROLES';
    public const VIEW_PROFILE = 'USER_VIEW_PROFILE';
    public const EDIT_PROFILE = 'USER_EDIT_PROFILE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::MANAGE_ROLES,
            self::VIEW_PROFILE,
            self::EDIT_PROFILE
        ]) && ($subject instanceof User || $subject === null);
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
            self::MANAGE_ROLES => $this->canManageRoles($user),
            self::VIEW_PROFILE => $this->canViewProfile($subject, $user),
            self::EDIT_PROFILE => $this->canEditProfile($subject, $user),
            default => false,
        };
    }

    private function canView(?User $targetUser, User $user): bool
    {
        // Admin can view all users
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        // OSEC can view all users
        if ($user->hasRole('ROLE_OSEC')) {
            return true;
        }

        // EO can view users from their office
        if ($user->hasRole('ROLE_EO') && $targetUser) {
            return $targetUser->getOffice() === $user->getOffice();
        }

        // Division can view users from their office
        if ($user->hasRole('ROLE_DIVISION') && $targetUser) {
            return $targetUser->getOffice() === $user->getOffice();
        }

        // Province can only view their own profile
        if ($user->hasRole('ROLE_PROVINCE') && $targetUser) {
            return $targetUser === $user;
        }

        return false;
    }

    private function canCreate(User $user): bool
    {
        // Only Admin can create new users
        return $user->hasRole('ROLE_ADMIN');
    }

    private function canEdit(?User $targetUser, User $user): bool
    {
        if (!$targetUser) {
            return false;
        }

        // Admin can edit all users
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        // Users can edit their own profile
        if ($targetUser === $user) {
            return true;
        }

        // OSEC can edit users with lower roles
        if ($user->hasRole('ROLE_OSEC')) {
            return !$targetUser->hasRole('ROLE_ADMIN') && !$targetUser->hasRole('ROLE_OSEC');
        }

        return false;
    }

    private function canDelete(?User $targetUser, User $user): bool
    {
        if (!$targetUser) {
            return false;
        }

        // Admin can delete users (except themselves)
        if ($user->hasRole('ROLE_ADMIN')) {
            return $targetUser !== $user;
        }

        return false;
    }

    private function canManageRoles(User $user): bool
    {
        // Only Admin can manage user roles
        return $user->hasRole('ROLE_ADMIN');
    }

    private function canViewProfile(?User $targetUser, User $user): bool
    {
        if (!$targetUser) {
            return false;
        }

        // Admin can view all profiles
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        // OSEC can view all profiles
        if ($user->hasRole('ROLE_OSEC')) {
            return true;
        }

        // Users can view their own profile
        if ($targetUser === $user) {
            return true;
        }

        // EO can view profiles from their office
        if ($user->hasRole('ROLE_EO')) {
            return $targetUser->getOffice() === $user->getOffice();
        }

        // Division can view profiles from their office
        if ($user->hasRole('ROLE_DIVISION')) {
            return $targetUser->getOffice() === $user->getOffice();
        }

        return false;
    }

    private function canEditProfile(?User $targetUser, User $user): bool
    {
        if (!$targetUser) {
            return false;
        }

        // Admin can edit all profiles
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        // Users can edit their own profile
        return $targetUser === $user;
    }
}