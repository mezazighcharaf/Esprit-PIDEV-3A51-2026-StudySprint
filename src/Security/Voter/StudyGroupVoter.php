<?php

namespace App\Security\Voter;

use App\Entity\StudyGroup;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class StudyGroupVoter extends Voter
{
    public const EDIT = 'GROUP_EDIT';
    public const DELETE = 'GROUP_DELETE';
    public const MANAGE_MEMBERS = 'GROUP_MANAGE_MEMBERS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::MANAGE_MEMBERS]) && $subject instanceof StudyGroup;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        /** @var StudyGroup $group */
        $group = $subject;

        return $group->getOwner() === $user;
    }
}
