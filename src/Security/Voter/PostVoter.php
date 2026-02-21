<?php

namespace App\Security\Voter;

use App\Entity\GroupPost;
use App\Entity\User;
use App\Enum\GroupRole;
use App\Repository\GroupMemberRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter for post-level permissions.
 * Determines if a user can perform actions on a group post.
 */
class PostVoter extends Voter
{
    public const VIEW = 'POST_VIEW';
    public const EDIT = 'POST_EDIT';
    public const DELETE = 'POST_DELETE';
    public const LIKE = 'POST_LIKE';
    public const RATE = 'POST_RATE';
    public const COMMENT = 'POST_COMMENT';

    public function __construct(
        private GroupMemberRepository $memberRepository
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::LIKE,
            self::RATE,
            self::COMMENT,
        ], true) && $subject instanceof GroupPost;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        
        /** @var GroupPost $post */
        $post = $subject;
        $group = $post->getGroup();

        // Viewing depends on group visibility
        if ($attribute === self::VIEW) {
            if ($group->getPrivacy() === 'public') {
                return true;
            }
            
            if (!$user instanceof User) {
                return false;
            }
            
            return $this->memberRepository->isMember($group, $user);
        }

        // All other actions require login
        if (!$user instanceof User) {
            return false;
        }

        // Get user's role in the group
        $roleString = $this->memberRepository->getUserRoleInGroup($group, $user);
        $role = GroupRole::tryFromString($roleString);
        
        // Must be a member for any action
        if ($role === null) {
            return false;
        }

        return match($attribute) {
            self::EDIT => $this->canEdit($post, $user, $role),
            self::DELETE => $this->canDelete($post, $user, $role),
            self::LIKE => true, // Any member can like
            self::RATE => true, // Any member can rate
            self::COMMENT => true, // Any member can comment
            default => false,
        };
    }

    private function canEdit(GroupPost $post, User $user, GroupRole $role): bool
    {
        // Only author can edit their post
        return $post->getAuthor()->getId() === $user->getId();
    }

    private function canDelete(GroupPost $post, User $user, GroupRole $role): bool
    {
        // Author can delete their own post
        if ($post->getAuthor()->getId() === $user->getId()) {
            return true;
        }
        
        // Admin can delete any post
        return $role->canDeleteAnyPost();
    }
}
