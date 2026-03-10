<?php

namespace App\Security\Voter;

use App\Entity\DirectoryContact;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DirectoryVoter extends Voter
{
    public const VIEW = 'DIRECTORY_VIEW';
    public const CREATE = 'DIRECTORY_CREATE';
    public const EDIT = 'DIRECTORY_EDIT';
    public const DELETE = 'DIRECTORY_DELETE';
    public const MANAGE = 'DIRECTORY_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::MANAGE
        ]) && ($subject instanceof DirectoryContact || $subject === null);
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
            self::MANAGE => $this->canManage($user),
            default => false,
        };
    }

    private function canView(?DirectoryContact $contact, User $user): bool
    {
        // All authenticated users can view directory contacts
        return true;
    }

    private function canCreate(User $user): bool
    {
        // Only Admin can create directory contacts
        return $user->hasRole('ROLE_ADMIN');
    }

    private function canEdit(?DirectoryContact $contact, User $user): bool
    {
        // Only Admin can edit directory contacts
        return $user->hasRole('ROLE_ADMIN');
    }

    private function canDelete(?DirectoryContact $contact, User $user): bool
    {
        // Only Admin can delete directory contacts
        return $user->hasRole('ROLE_ADMIN');
    }

    private function canManage(User $user): bool
    {
        // Only Admin can access directory management
        return $user->hasRole('ROLE_ADMIN');
    }
}