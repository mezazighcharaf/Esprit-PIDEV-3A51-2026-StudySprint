<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setMotDePasse($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Group users by registration year
     */
    public function countByRegistrationYear(): array
    {
        return $this->getEntityManager()->createQuery(
            'SELECT SUBSTRING(u.dateInscription, 1, 4) as year, COUNT(u.id) as count
             FROM App\Entity\User u
             WHERE u.role != :adminRole
             GROUP BY year
             ORDER BY year ASC'
        )->setParameter('adminRole', 'ROLE_ADMIN')->getResult();
    }

    /**
     * Group students by age range and sex
     */
    public function countStudentsByAgeRange(): array
    {
        return $this->getEntityManager()->createQuery(
            'SELECT
                CASE
                    WHEN s.age < 18 THEN \'Moins de 18\'
                    WHEN s.age BETWEEN 18 AND 25 THEN \'18-25\'
                    WHEN s.age BETWEEN 26 AND 35 THEN \'26-35\'
                    ELSE \'Plus de 35\'
                END as ageRange,
                s.sexe as sex,
                COUNT(s.id) as count
            FROM App\Entity\Student s
            GROUP BY ageRange, sex'
        )->getResult();
    }

    /**
     * Group all users by country
     */
    public function countUsersByCountry(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT pays as label, COUNT(id) as value FROM users WHERE pays IS NOT NULL AND discr IN ("student", "professor") GROUP BY pays';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Group professors by experience range
     */
    public function countProfessorExperience(): array
    {
        return $this->getEntityManager()->createQuery(
            'SELECT
                CASE
                    WHEN p.anneesExperience < 5 THEN \'Junior (0-5 ans)\'
                    WHEN p.anneesExperience BETWEEN 5 AND 15 THEN \'Confirmé (5-15 ans)\'
                    ELSE \'Senior (15+ ans)\'
                END as label,
                COUNT(p.id) as value
            FROM App\Entity\Professor p
            GROUP BY label'
        )->getResult();
    }

    /**
     * Group professors by country AND establishment
     */
    public function countProfessorsByCountryAndEstablishment(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT pays, etablissement, COUNT(id) as count
                FROM users
                WHERE discr = "professor" AND pays IS NOT NULL AND etablissement IS NOT NULL
                GROUP BY pays, etablissement
                ORDER BY pays ASC, count DESC';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Group students by country AND establishment
     */
    public function countStudentsByCountryAndEstablishment(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT pays, etablissement, COUNT(id) as count
                FROM users
                WHERE discr = "student" AND pays IS NOT NULL AND etablissement IS NOT NULL
                GROUP BY pays, etablissement
                ORDER BY pays ASC, count DESC';
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Search and filter users with sorting
     */
    public function findBySearchQuery(?string $query = null, ?string $role = null, ?string $status = null, ?string $sortBy = 'dateInscription', ?string $sortDirection = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('u');

        if ($query) {
            $qb->andWhere('u.email LIKE :query OR u.nom LIKE :query OR u.prenom LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        if ($role) {
            $qb->andWhere('u.role = :role')
               ->setParameter('role', $role);
        }

        if ($status) {
            $qb->andWhere('u.statut = :status')
               ->setParameter('status', $status);
        }

        $allowedSortFields = [
            'dateInscription' => 'u.dateInscription',
            'experience' => 'u.anneesExperience',
        ];

        if ($sortBy && isset($allowedSortFields[$sortBy])) {
            $direction = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
            $qb->orderBy($allowedSortFields[$sortBy], $direction);
        } else {
            $qb->orderBy('u.dateInscription', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Search, filter and paginate users.
     *
     * @return array{users: User[], total: int, pages: int, page: int, perPage: int}
     */
    public function findPaginated(
        ?string $query = null,
        ?string $role = null,
        ?string $status = null,
        ?string $sortBy = 'dateInscription',
        ?string $sortDirection = 'DESC',
        int $page = 1,
        int $perPage = 25
    ): array {
        $allowedSortFields = [
            'dateInscription' => 'u.dateInscription',
            'experience'      => 'u.anneesExperience',
        ];
        $direction = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';
        $orderField = isset($allowedSortFields[$sortBy]) ? $allowedSortFields[$sortBy] : 'u.dateInscription';

        $countQb = $this->createQueryBuilder('u')->select('COUNT(u.id)');
        if ($query) {
            $countQb->andWhere('u.email LIKE :q OR u.nom LIKE :q OR u.prenom LIKE :q')
                    ->setParameter('q', '%' . $query . '%');
        }
        if ($role) {
            $countQb->andWhere('u.role = :role')->setParameter('role', $role);
        }
        if ($status) {
            $countQb->andWhere('u.statut = :status')->setParameter('status', $status);
        }
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $page    = max(1, $page);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25;
        $pages   = max(1, (int) ceil($total / $perPage));
        $page    = min($page, $pages);
        $offset  = ($page - 1) * $perPage;

        $dataQb = $this->createQueryBuilder('u');
        if ($query) {
            $dataQb->andWhere('u.email LIKE :q OR u.nom LIKE :q OR u.prenom LIKE :q')
                   ->setParameter('q', '%' . $query . '%');
        }
        if ($role) {
            $dataQb->andWhere('u.role = :role')->setParameter('role', $role);
        }
        if ($status) {
            $dataQb->andWhere('u.statut = :status')->setParameter('status', $status);
        }
        $dataQb->orderBy($orderField, $direction)
               ->setMaxResults($perPage)
               ->setFirstResult($offset);

        return [
            'users'   => $dataQb->getQuery()->getResult(),
            'total'   => $total,
            'pages'   => $pages,
            'page'    => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Get user KPIs for the dashboard
     */
    public function getUsersKpiData(): array
    {
        $qb = $this->createQueryBuilder('u');

        $totalNonAdmins = (int) $qb->select('COUNT(u.id)')
            ->where('u.role != :adminRole')
            ->setParameter('adminRole', 'ROLE_ADMIN')
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('u');

        $results = $qb->select('u.role, u.statut, COUNT(u.id) as count')
            ->where('u.role != :adminRole')
            ->setParameter('adminRole', 'ROLE_ADMIN')
            ->groupBy('u.role, u.statut')
            ->getQuery()
            ->getResult();

        $stats = [
            'total_non_admins' => $totalNonAdmins,
            'students' => 0,
            'professors' => 0,
            'active' => 0,
            'inactive' => 0,
            'pending' => 0
        ];

        foreach ($results as $row) {
            $role   = $row['role'];
            $status = $row['statut'];
            $count  = (int) $row['count'];

            if ($role === 'ROLE_STUDENT') {
                $stats['students'] += $count;
            } elseif ($role === 'ROLE_PROFESSOR') {
                $stats['professors'] += $count;
            }

            if ($status === 'actif') {
                $stats['active'] += $count;
            } elseif ($status === 'inactif') {
                $stats['inactive'] += $count;
            } elseif ($status === 'attente' || $status === 'pending') {
                $stats['pending'] += $count;
            }
        }

        return $stats;
    }

    /**
     * Find recently registered users (excluding admins)
     */
    public function findRecentUsers(int $limit = 5): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.role != :adminRole')
            ->setParameter('adminRole', 'ROLE_ADMIN')
            ->orderBy('u.dateInscription', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
