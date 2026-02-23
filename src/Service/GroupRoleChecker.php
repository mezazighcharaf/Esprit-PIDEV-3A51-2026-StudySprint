<?php

namespace App\Service;

use App\Entity\StudyGroup;
use App\Entity\User;
use App\Enum\GroupRole;
use App\Repository\GroupMemberRepository;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Centralized service for checking group member roles and permissions
 * Eliminates duplicate role checking logic across services and controllers
 * Refactored for PHP 8.0 compatibility (using string roles instead of Enums)
 */
class GroupRoleChecker
{
    public function __construct(
        private GroupMemberRepository $memberRepository
    ) {
    }

    /**
     * Get user's role in a group
     * @return string|null
     */
    public function getUserRole(StudyGroup $group, User $user): ?string
    {
        $roleString = $this->memberRepository->getUserRoleInGroup($group, $user);
        return GroupRole::tryFromString($roleString);
    }

    /**
     * Check if user is member of group
     */
    public function isMember(StudyGroup $group, User $user): bool
    {
        return $this->memberRepository->isMember($group, $user);
    }

    /**
     * Ensure user can edit group (with exception thrown if not)
     */
    public function ensureCanEdit(StudyGroup $group, User $user): string
    {
        $role = $this->getUserRole($group, $user);
        if ($role === null || !GroupRole::canEditGroup($role)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les permissions pour modifier ce groupe');
        }
        return $role;
    }

    /**
     * Ensure user can delete group (with exception thrown if not)
     */
    public function ensureCanDelete(StudyGroup $group, User $user): string
    {
        $role = $this->getUserRole($group, $user);
        if ($role === null || !GroupRole::canDeleteGroup($role)) {
            throw new AccessDeniedHttpException('Seuls les administrateurs du groupe peuvent le supprimer');
        }
        return $role;
    }

    /**
     * Ensure user can manage group members (with exception thrown if not)
     */
    public function ensureCanManageMembers(StudyGroup $group, User $user): string
    {
        $role = $this->getUserRole($group, $user);
        if ($role === null || !GroupRole::canManageMembers($role)) {
            throw new AccessDeniedHttpException('Seuls les administrateurs peuvent modifier les rôles');
        }
        return $role;
    }

    /**
     * Ensure user can invite members (with exception thrown if not)
     */
    public function ensureCanInvite(StudyGroup $group, User $user): string
    {
        $role = $this->getUserRole($group, $user);
        if ($role === null || !GroupRole::canInviteMembers($role)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les permissions pour inviter des membres');
        }
        return $role;
    }

    /**
     * Ensure user can remove members (with exception thrown if not)
     */
    public function ensureCanRemoveMembers(StudyGroup $group, User $user): string
    {
        $role = $this->getUserRole($group, $user);
        if ($role === null || !GroupRole::canRemoveMembers($role)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas les permissions pour retirer des membres');
        }
        return $role;
    }

    /**
     * Ensure user is member of group (with exception thrown if not)
     */
    public function ensureMember(StudyGroup $group, User $user): void
    {
        if (!$this->memberRepository->isMember($group, $user)) {
            throw new AccessDeniedHttpException('Vous devez être membre du groupe pour effectuer cette action');
        }
    }

    /**
     * Check if user is admin of group
     */
    public function isAdmin(StudyGroup $group, User $user): bool
    {
        $role = $this->getUserRole($group, $user);
        return $role === GroupRole::ADMIN;
    }

    /**
     * Check if user can delete post in group
     */
    public function canDeletePost(StudyGroup $group, User $user, User $postAuthor): bool
    {
        // Author can delete their own post
        if ($postAuthor->getId() === $user->getId()) {
            return true;
        }

        // Admin can delete any post
        $role = $this->getUserRole($group, $user);
        return $role !== null && GroupRole::canDeleteAnyPost($role);
    }

    /**
     * Check if user can delete comment in group
     */
    public function canDeleteComment(StudyGroup $group, User $user, User $commentAuthor): bool
    {
        // Author can delete their own comment
        if ($commentAuthor->getId() === $user->getId()) {
            return true;
        }

        // Admin can delete any comment
        $role = $this->getUserRole($group, $user);
        return $role !== null && GroupRole::canDeleteAnyComment($role);
    }
}
