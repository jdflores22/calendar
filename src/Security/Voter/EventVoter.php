<?php

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EventVoter extends Voter
{
    public const VIEW = 'EVENT_VIEW';
    public const CREATE = 'EVENT_CREATE';
    public const EDIT = 'EVENT_EDIT';
    public const DELETE = 'EVENT_DELETE';
    public const OVERRIDE_CONFLICT = 'EVENT_OVERRIDE_CONFLICT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::OVERRIDE_CONFLICT
        ]) && ($subject instanceof Event || $subject === null);
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
            self::OVERRIDE_CONFLICT => $this->canOverrideConflict($user),
            default => false,
        };
    }

    private function canView(?Event $event, User $user): bool
    {
        // All authenticated users can view all events (universal visibility)
        return true;
    }

    private function canCreate(User $user): bool
    {
        // All authenticated users can create events
        return true;
    }

    private function canEdit(?Event $event, User $user): bool
    {
        if (!$event) {
            return false;
        }

        // Admin can edit all events
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        // OSEC can edit all events
        if ($user->hasRole('ROLE_OSEC')) {
            return true;
        }

        // Event creator can always edit their own events
        if ($event->getCreator() === $user) {
            return true;
        }

        // EO can edit events from their office only
        if ($user->hasRole('ROLE_EO')) {
            return $event->getOffice() === $user->getOffice();
        }

        // Division can edit events from their assigned office only
        if ($user->hasRole('ROLE_DIVISION')) {
            return $event->getOffice() === $user->getOffice();
        }

        // Province can edit only their own events (already covered above, but keeping for clarity)
        if ($user->hasRole('ROLE_PROVINCE')) {
            return $event->getCreator() === $user;
        }

        return false;
    }

    private function canDelete(?Event $event, User $user): bool
    {
        if (!$event) {
            return false;
        }

        // Admin can delete all events
        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        // OSEC can delete all events
        if ($user->hasRole('ROLE_OSEC')) {
            return true;
        }

        // Event creator can always delete their own events
        if ($event->getCreator() === $user) {
            return true;
        }

        // EO can delete events from their office only
        if ($user->hasRole('ROLE_EO')) {
            return $event->getOffice() === $user->getOffice();
        }

        // Division can delete events from their assigned office only
        if ($user->hasRole('ROLE_DIVISION')) {
            return $event->getOffice() === $user->getOffice();
        }

        // Province can delete only their own events (already covered above, but keeping for clarity)
        if ($user->hasRole('ROLE_PROVINCE')) {
            return $event->getCreator() === $user;
        }

        return false;
    }

    private function canOverrideConflict(User $user): bool
    {
        // All authenticated users can override scheduling conflicts
        return $user->hasRole('ROLE_USER');
    }
}