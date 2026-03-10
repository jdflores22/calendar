<?php

namespace App\Security\Voter;

use App\Entity\Form;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FormBuilderVoter extends Voter
{
    public const VIEW = 'form_builder_view';
    public const CREATE = 'form_builder_create';
    public const EDIT = 'form_builder_edit';
    public const DELETE = 'form_builder_delete';
    public const MANAGE = 'form_builder_manage';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Check if the attribute is one we support
        if (!in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::DELETE, self::MANAGE])) {
            return false;
        }

        // For CREATE and MANAGE, subject can be null (general permission)
        if (in_array($attribute, [self::CREATE, self::MANAGE])) {
            return true;
        }

        // For other actions, subject must be a Form instance
        return $subject instanceof Form;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();

        // User must be logged in
        if (!$user instanceof User) {
            return false;
        }

        // Only Admin users can access Form Builder
        if (!$user->isAdmin()) {
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

    private function canView(?Form $form, User $user): bool
    {
        // Admin can view all forms
        return true;
    }

    private function canCreate(User $user): bool
    {
        // Admin can create forms
        return true;
    }

    private function canEdit(?Form $form, User $user): bool
    {
        // Admin can edit all forms
        return true;
    }

    private function canDelete(?Form $form, User $user): bool
    {
        // Admin can delete all forms
        return true;
    }

    private function canManage(User $user): bool
    {
        // Admin can manage form builder
        return true;
    }
}