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
class GroupMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupMember::class);
    }

    /**
     * Find all members of a group
     */
    public function findByGroup(StudyGroup $group)
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
     */
    public function findGroupsByUser(User $user)
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
    public function findAdminsByGroup(StudyGroup $group): array
    {
        return $this->findByGroupAndRole($group, 'admin');
    }

    /**
     * Get all moderators of a group
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
    public function findRecentJoines(StudyGroup $group, int $limit = 5): array
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
}
