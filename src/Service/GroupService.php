<?php

namespace App\Service;

use App\Dto\GroupCreateDTO;
use App\Dto\GroupUpdateDTO;
use App\Entity\GroupMember;
use App\Entity\GroupPost;
use App\Entity\StudyGroup;
use App\Entity\User;
use App\Enum\GroupRole;
use App\Repository\GroupMemberRepository;
use App\Repository\GroupPostRepository;
use App\Repository\StudyGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Service for managing study groups
 * Refactored for PHP 8.0 compatibility (using string roles instead of Enums)
 */
class GroupService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudyGroupRepository $groupRepository,
        private GroupMemberRepository $memberRepository,
        private GroupPostRepository $postRepository,
        private \App\Repository\GroupInvitationRepository $invitationRepository,
    ) {
    }

    /**
     * Create a new group
     * The creator is automatically added as Admin
     */
    public function createGroup(GroupCreateDTO $dto, User $creator): StudyGroup
    {
        $group = new StudyGroup();
        $group->setName($dto->name);
        $group->setDescription($dto->description);
        $group->setPrivacy($dto->privacy);
        $group->setSubject($dto->subject);
        $group->setCreatedBy($creator);

        $this->entityManager->persist($group);
        $this->entityManager->flush();

        // Add creator as Admin member
        $this->addMember($group, $creator, GroupRole::ADMIN);

        return $group;
    }

    /**
     * Update an existing group
     * Only Admin or Moderator can update
     */
    public function updateGroup(StudyGroup $group, GroupUpdateDTO $dto, User $currentUser): void
    {
        $userRole = $this->getUserRole($group, $currentUser);

        if (!$userRole || !GroupRole::canEditGroup($userRole)) {
            throw new AccessDeniedHttpException('Vous n\'avez pas la permission de modifier ce groupe');
        }

        $group->setName($dto->name);
        $group->setDescription($dto->description);
        $group->setPrivacy($dto->privacy);
        $group->setSubject($dto->subject);

        $this->entityManager->flush();
    }

    /**
     * Delete a group
     * Only Admin can delete
     */
    public function deleteGroup(StudyGroup $group, User $currentUser): void
    {
        $userRole = $this->getUserRole($group, $currentUser);

        if (!$userRole || !GroupRole::canDeleteGroup($userRole)) {
            throw new AccessDeniedHttpException('Seuls les administrateurs du groupe peuvent le supprimer');
        }

        // Remove all group invitations first
        $invitations = $this->invitationRepository->findBy(['group' => $group]);
        foreach ($invitations as $invitation) {
            $this->entityManager->remove($invitation);
        }
        $this->entityManager->flush();

        // Remove all group posts first
        $posts = $this->postRepository->findBy(['group' => $group]);
        foreach ($posts as $post) {
            $this->entityManager->remove($post);
        }
        $this->entityManager->flush();

        // Remove all group members
        $members = $this->memberRepository->findBy(['group' => $group]);
        foreach ($members as $member) {
            $this->entityManager->remove($member);
        }
        $this->entityManager->flush();

        // Now delete the group
        $this->entityManager->remove($group);
        $this->entityManager->flush();
    }

    /**
     * Add a member to a group
     */
    public function addMember(StudyGroup $group, User $user, string $role = GroupRole::MEMBER): GroupMember
    {
        // Check if already a member
        $existingMember = $this->memberRepository->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);

        if ($existingMember) {
            return $existingMember;
        }

        $member = new GroupMember();
        $member->setGroup($group);
        $member->setUser($user);
        $member->setMemberRole($role);

        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return $member;
    }

    public function removeMember(StudyGroup $group, User $user, User $currentUser, bool $isAppAdmin = false): void
    {
        if (!$isAppAdmin) {
            $currentUserRole = $this->getUserRole($group, $currentUser);
            if (!$currentUserRole || !GroupRole::canManageMembers($currentUserRole)) {
                throw new AccessDeniedHttpException('Seuls les administrateurs peuvent retirer des membres');
            }
        }

        $member = $this->memberRepository->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);

        if ($member) {
            $memberRole = $member->getMemberRole();

            if ($memberRole === GroupRole::ADMIN) {
                $adminCount = $this->memberRepository->countByGroupAndRole($group, GroupRole::ADMIN);
                if ($adminCount <= 1) {
                    throw new \LogicException('Un groupe doit toujours avoir au moins un administrateur.');
                }
            }

            $this->entityManager->remove($member);
            $this->entityManager->flush();
        }
    }

    public function changeMemberRole(StudyGroup $group, User $member, string $newRole, User $currentUser, bool $isAppAdmin = false): void
    {
        if (!$isAppAdmin) {
            $currentUserRole = $this->getUserRole($group, $currentUser);

            if (!$currentUserRole || !GroupRole::canManageMembers($currentUserRole)) {
                throw new AccessDeniedHttpException('Seuls les administrateurs peuvent modifier les rôles');
            }
        }

        $groupMember = $this->memberRepository->findOneBy([
            'group' => $group,
            'user' => $member,
        ]);

        if ($groupMember) {
            $currentMemberRole = $groupMember->getMemberRole();
            $targetRole = GroupRole::tryFromString($newRole);

            if (!$targetRole) {
                throw new \InvalidArgumentException('Rôle invalide');
            }

            if ($currentMemberRole === GroupRole::ADMIN && $targetRole !== GroupRole::ADMIN) {
                $adminCount = $this->memberRepository->countByGroupAndRole($group, GroupRole::ADMIN);
                if ($adminCount <= 1) {
                    throw new \LogicException('Impossible de changer le rôle du seul administrateur.');
                }
            }

            $groupMember->setMemberRole($targetRole);
            $this->entityManager->flush();
        }
    }

    /**
     * Search groups
     */
    public function searchGroups(string $query, ?string $privacy = null): array
    {
        $qb = $this->groupRepository->createQueryBuilder('g');

        $qb->where(
            $qb->expr()->orX(
                $qb->expr()->like('LOWER(g.name)', ':query'),
                $qb->expr()->like('LOWER(g.description)', ':query')
            )
        )
            ->setParameter('query', '%' . strtolower($query) . '%');

        if ($privacy) {
            $qb->andWhere('g.privacy = :privacy')
                ->setParameter('privacy', $privacy);
        }

        return $qb->orderBy('g.name', 'ASC')->getQuery()->getResult();
    }

    /**
     * Get available public groups
     */
    public function getAvailableGroupsForUser(User $user): array
    {
        return $this->groupRepository->createQueryBuilder('g')
            ->leftJoin(
                GroupMember::class,
                'm',
                'WITH',
                'm.group = g AND m.user = :user'
            )
            ->where('m.id IS NULL')
            ->andWhere('g.privacy = :public')
            ->setParameter('user', $user)
            ->setParameter('public', 'public')
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user's role in a group as string
     */
    public function getUserRole(StudyGroup $group, User $user): ?string
    {
        $roleString = $this->memberRepository->getUserRoleInGroup($group, $user);
        return $roleString ?: null;
    }

    public function canEditGroup(StudyGroup $group, User $user): bool
    {
        $role = $this->getUserRole($group, $user);
        return $role !== null && GroupRole::canEditGroup($role);
    }

    public function canDeleteGroup(StudyGroup $group, User $user): bool
    {
        $role = $this->getUserRole($group, $user);
        return $role !== null && GroupRole::canDeleteGroup($role);
    }

    public function isGroupAdmin(StudyGroup $group, User $user): bool
    {
        $role = $this->getUserRole($group, $user);
        return $role === GroupRole::ADMIN;
    }

    public function joinPublicGroup(StudyGroup $group, User $user): GroupMember
    {
        if ($group->getPrivacy() !== 'public') {
            throw new AccessDeniedHttpException('This group is private');
        }

        return $this->addMember($group, $user, GroupRole::MEMBER);
    }

    public function isMember(StudyGroup $group, User $user): bool
    {
        return (bool) $this->memberRepository->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);
    }
}
