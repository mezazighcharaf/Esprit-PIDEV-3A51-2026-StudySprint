<?php

namespace App\Security\Voter;

use App\Entity\Quiz;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class QuizVoter extends Voter
{
    public const EDIT = 'QUIZ_EDIT';
    public const DELETE = 'QUIZ_DELETE';
    public const VIEW = 'QUIZ_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::VIEW]) && $subject instanceof Quiz;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Quiz $quiz */
        $quiz = $subject;

        // Admins can do everything
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $quiz->isPublished() || $quiz->getOwner() === $user,
            self::EDIT, self::DELETE => $quiz->getOwner() === $user,
            default => false,
        };
    }
}
