<?php

namespace App\Security\Voter;

use App\Entity\StudyGroup;
use App\Entity\User;
use App\Enum\GroupRole;
use App\Repository\GroupMemberRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter for group-level permissions.
 * Refactored for PHP 8.0 compatibility (using string roles instead of Enums)
 */
class GroupVoter extends Voter
{
    public const VIEW = 'GROUP_VIEW';
    public const EDIT = 'GROUP_EDIT';
    public const DELETE = 'GROUP_DELETE';
    public const INVITE = 'GROUP_INVITE';
    public const POST = 'GROUP_POST';
    public const MANAGE_MEMBERS = 'GROUP_MANAGE_MEMBERS';
    public const REMOVE_MEMBER = 'GROUP_REMOVE_MEMBER';

    public function __construct(
        private GroupMemberRepository $memberRepository
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::INVITE,
            self::POST,
            self::MANAGE_MEMBERS,
            self::REMOVE_MEMBER,
        ], true) && $subject instanceof StudyGroup;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Must be logged in for most actions
        if (!$user instanceof User) {
            // Allow viewing public groups without login
            if ($attribute === self::VIEW && $subject->getPrivacy() === 'public') {
                return true;
            }
            return false;
        }

        // App admins (ROLE_ADMIN) can do everything on any group
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        /** @var StudyGroup $group */
        $group = $subject;

        // Get user's role in this group
        $role = $this->memberRepository->getUserRoleInGroup($group, $user);

        return match ($attribute) {
            self::VIEW => $this->canView($group, $role),
            self::EDIT => $this->canEdit($role),
            self::DELETE => $this->canDelete($role),
            self::INVITE => $this->canInvite($group, $role),
            self::POST => $this->canPost($role),
            self::MANAGE_MEMBERS => $this->canManageMembers($role),
            self::REMOVE_MEMBER => $this->canRemoveMember($role),
            default => false,
        };
    }

    private function canView(StudyGroup $group, ?string $role): bool
    {
        // Public groups are viewable by everyone
        if ($group->getPrivacy() === 'public') {
            return true;
        }

        // Private groups require membership
        return $role !== null;
    }

    private function canEdit(?string $role): bool
    {
        if ($role === null) {
            return false;
        }

        return GroupRole::canEditGroup($role);
    }

    private function canDelete(?string $role): bool
    {
        if ($role === null) {
            return false;
        }

        return GroupRole::canDeleteGroup($role);
    }

    private function canInvite(StudyGroup $group, ?string $role): bool
    {
        // Can only invite to private groups
        if ($group->getPrivacy() !== 'private') {
            return false;
        }

        if ($role === null) {
            return false;
        }

        return GroupRole::canInviteMembers($role);
    }

    private function canPost(?string $role): bool
    {
        // Must be a member to post
        return $role !== null;
    }

    private function canManageMembers(?string $role): bool
    {
        if ($role === null) {
            return false;
        }

        return GroupRole::canManageMembers($role);
    }

    private function canRemoveMember(?string $role): bool
    {
        if ($role === null) {
            return false;
        }

        return GroupRole::canRemoveMembers($role);
    }
}
