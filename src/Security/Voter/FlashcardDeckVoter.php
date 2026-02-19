<?php

namespace App\Security\Voter;

use App\Entity\FlashcardDeck;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FlashcardDeckVoter extends Voter
{
    public const EDIT = 'DECK_EDIT';
    public const DELETE = 'DECK_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE]) && $subject instanceof FlashcardDeck;
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

        /** @var FlashcardDeck $deck */
        $deck = $subject;

        return $deck->getOwner() === $user;
    }
}
