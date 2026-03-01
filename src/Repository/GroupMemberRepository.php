<?php

namespace App\Repository;

use App\Entity\GroupMember;
use App\Entity\StudyGroup;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupMember>
 *
 * @method GroupMember|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupMember|null findOneBy(array $criteria, array $orderBy = null)
 * @method GroupMember[]    findAll()
 * @method GroupMember[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
/**
 * @extends ServiceEntityRepository<GroupMember>
 */
class GroupMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupMember::class);
    }

    /**
     * Find all members of a group
     *
     * @return list<GroupMember>
     */
    public function findByGroup(StudyGroup $group): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.group = :group')
            ->setParameter('group', $group)
            ->orderBy('m.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a user is member of a group
     */
    public function isMember(StudyGroup $group, User $user): bool
    {
        return null !== $this->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);
    }

    /**
     * Get user's role in a group
     */
    public function getUserRoleInGroup(StudyGroup $group, User $user): ?string
    {
        $member = $this->findOneBy([
            'group' => $group,
            'user' => $user,
        ]);

        return $member?->getMemberRole();
    }

    /**
     * Find all groups for a user
     *
     * @return list<GroupMember>
     */
    public function findGroupsByUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.group', 'g')
            ->addSelect('g')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find members by role in a group
     */
    /**
     * @return list<GroupMember>
     */
    public function findByGroupAndRole(StudyGroup $group, string $role): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.group = :group')
            ->andWhere('m.memberRole = :role')
            ->setParameter('group', $group)
            ->setParameter('role', $role)
            ->orderBy('m.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count members by role in a group
     */
    public function countByGroupAndRole(StudyGroup $group, string $role): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.group = :group')
            ->andWhere('m.memberRole = :role')
            ->setParameter('group', $group)
            ->setParameter('role', $role)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get all admins of a group
     */
    /**
     * @return list<GroupMember>
     */
    public function findAdminsByGroup(StudyGroup $group): array
    {
        return $this->findByGroupAndRole($group, 'admin');
    }

    /**
     * Get all moderators of a group
     */
    /**
     * @return list<GroupMember>
     */
    public function findModeratorsByGroup(StudyGroup $group): array
    {
        return $this->findByGroupAndRole($group, 'moderator');
    }

    /**
     * Get total member count for a group
     */
    public function countByGroup(StudyGroup $group): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.group = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find recently joined members
     */
    /** @return list<GroupMember> */ public function findRecentJoines(\App\Entity\StudyGroup $group, int $limit = 5): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.group = :group')
            ->setParameter('group', $group)
            ->orderBy('m.joinedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all groups for a user with member counts (optimized - avoids N+1)
     * 
     * @return list<array{membership: GroupMember, memberCount: int}>
     */
    public function findGroupsByUserWithCounts(User $user): array
    {
        // Get user's memberships with groups
        $memberships = $this->createQueryBuilder('m')
            ->leftJoin('m.group', 'g')
            ->addSelect('g')
            ->where('m.user = :user')
            ->setParameter('user', $user)
            ->orderBy('g.lastActivity', 'DESC')
            ->getQuery()
            ->getResult();

        if (empty($memberships)) {
            return [];
        }

        // Get group IDs
        $groupIds = array_map(fn(GroupMember $m) => ($g = $m->getGroup()) ? $g->getId() : 0, $memberships);

        // Batch query for member counts
        $memberCounts = $this->getEntityManager()
            ->createQuery('
                SELECT NEW App\Dto\IdCountDto(g.id, COUNT(m.id))
                FROM App\Entity\GroupMember m
                JOIN m.group g
                WHERE g.id IN (:groupIds)
                GROUP BY g.id
            ')
            ->setParameter('groupIds', $groupIds)
            ->getResult();

        $countsMap = [];
        /** @var \App\Dto\IdCountDto[] $memberCounts */
        foreach ($memberCounts as $dto) {
            $countsMap[$dto->id] = $dto->count;
        }

        // Build result
        $result = [];
        foreach ($memberships as $membership) {
            $groupId = $membership->getGroup()->getId();
            $result[] = [
                'membership' => $membership,
                'memberCount' => $countsMap[$groupId] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Get members for multiple groups at once (batch loading)
     * @param StudyGroup[] $groups
     * @return array<int, list<GroupMember>>
     */
    public function findMembersByGroups(array $groups): array
    {
        if (empty($groups)) {
            return [];
        }

        $groupIds = array_map(fn(StudyGroup $g) => $g->getId(), $groups);

        $members = $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->leftJoin('m.group', 'g')
            ->addSelect('g')
            ->where('g.id IN (:groupIds)')
            ->setParameter('groupIds', $groupIds)
            ->orderBy('m.joinedAt', 'ASC')
            ->getQuery()
            ->getResult();

        // Group by group ID
        $result = [];
        foreach ($members as $member) {
            $groupId = $member->getGroup()->getId();
            if (!isset($result[$groupId])) {
                $result[$groupId] = [];
            }
            $result[$groupId][] = $member;
        }

        return $result;
    }
}
