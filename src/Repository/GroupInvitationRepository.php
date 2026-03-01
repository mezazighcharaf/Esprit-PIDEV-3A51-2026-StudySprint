<?php

namespace App\Repository;

use App\Entity\GroupInvitation;
use App\Entity\StudyGroup;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupInvitation>
 */
class GroupInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupInvitation::class);
    }

    /**
     * Find a valid invitation by code
     */
    public function findValidByCode(string $code): ?GroupInvitation
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.code = :code')
            ->andWhere('i.status = :status')
            ->setParameter('code', $code)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find a valid invitation by token
     */
    public function findValidByToken(string $token): ?GroupInvitation
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.token = :token')
            ->andWhere('i.status = :status')
            ->setParameter('token', $token)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find pending invitations for a given email
     * 
     * @return list<GroupInvitation>
     */
    public function findPendingByEmail(string $email): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.email = :email')
            ->andWhere('i.status = :status')
            ->setParameter('email', $email)
            ->setParameter('status', 'pending')
            ->orderBy('i.invitedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find an invitation by group and email (any status)
     */
    public function findOneByGroupAndEmail(StudyGroup $group, string $email): ?GroupInvitation
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.group = :group')
            ->andWhere('LOWER(i.email) = LOWER(:email)')
            ->setParameter('group', $group)
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if a user is already invited to a group
     */
    public function existsForGroupAndEmail(StudyGroup $group, string $email): bool
    {
        return (bool) $this->createQueryBuilder('i')
            ->select('1')
            ->andWhere('i.group = :group')
            ->andWhere('LOWER(i.email) = LOWER(:email)')
            ->setParameter('group', $group)
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<GroupInvitation>
     */
    public function findReceived(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('LOWER(i.email) = LOWER(:email)')
            ->andWhere('i.status = :status')
            ->setParameter('email', $user->getEmail())
            ->setParameter('status', 'pending')
            ->orderBy('i.invitedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<GroupInvitation>
     */
    public function findSent(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.invitedBy = :user')
            ->orderBy('i.invitedAt', 'DESC')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all invitations (for BO admin), ordered by most recent first.
     * 
     * @return list<GroupInvitation>
     */
    public function findAllOrderByInvitedAtDesc(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.group', 'g')
            ->addSelect('g')
            ->leftJoin('i.invitedBy', 'u')
            ->addSelect('u')
            ->orderBy('i.invitedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}