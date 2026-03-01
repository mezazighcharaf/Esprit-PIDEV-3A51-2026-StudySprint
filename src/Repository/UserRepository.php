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
/**
 * @extends ServiceEntityRepository<User>
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
     *
     * @return array<\App\Dto\UserRegistrationYearDto>
     */
    public function countByRegistrationYear(): array
    {
        $yearExpr = 'SUBSTRING(u.dateInscription, 1, 4)';
        $results = $this->createQueryBuilder('u')
            ->select("$yearExpr as regYear, COUNT(u.id) as regCount")
            ->where('u.role != :adminRole')
            ->setParameter('adminRole', 'ROLE_ADMIN')
            ->groupBy('regYear')
            ->orderBy('regYear', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getScalarResult();

        return array_map(
            fn(array $row) => new \App\Dto\UserRegistrationYearDto((string)$row['regYear'], (int)$row['regCount']),
            $results
        );
    }

    /**
     * Group students by age range and sex
     *
     * @return array<\App\Dto\StudentAgeDistributionDto>
     */
    public function countStudentsByAgeRange(): array
    {
        $ageCase = 'CASE 
                    WHEN s.age < 18 THEN \'Moins de 18\'
                    WHEN s.age BETWEEN 18 AND 25 THEN \'18-25\'
                    WHEN s.age BETWEEN 26 AND 35 THEN \'26-35\'
                    ELSE \'Plus de 35\'
                END';

        $results = $this->getEntityManager()->createQueryBuilder()
            ->select("$ageCase as ageRange, s.sexe as sex, COUNT(s.id) as studentCount")
            ->from(\App\Entity\Student::class, 's')
            ->groupBy('ageRange, sex')
            ->setMaxResults(20)
            ->getQuery()
            ->getScalarResult();

        return array_map(
            fn(array $row) => new \App\Dto\StudentAgeDistributionDto((string)$row['ageRange'], (string)$row['sex'], (int)$row['studentCount']),
            $results
        );
    }

    /**
     * Group all users by country
     *
     * @return array<\App\Dto\ProfessorExperienceDto>
     */
    public function countUsersByCountry(): array
    {
        $results = $this->createQueryBuilder('u')
            ->select('u.pays as country, COUNT(u.id) as userCount')
            ->where('u.pays IS NOT NULL')
            ->andWhere('u INSTANCE OF App\Entity\Student OR u INSTANCE OF App\Entity\Professor')
            ->groupBy('country')
            ->orderBy('userCount', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getScalarResult();

        return array_map(
            fn(array $row) => new \App\Dto\ProfessorExperienceDto((string)$row['country'], (int)$row['userCount']),
            $results
        );
    }

    /**
     * Group professors by experience range
     *
     * @return array<\App\Dto\ProfessorExperienceDto>
     */
    public function countProfessorExperience(): array
    {
        $expCase = 'CASE 
                    WHEN p.anneesExperience < 5 THEN \'Junior (0-5 ans)\'
                    WHEN p.anneesExperience BETWEEN 5 AND 15 THEN \'Confirmé (5-15 ans)\'
                    ELSE \'Senior (15+ ans)\'
                END';

        $results = $this->getEntityManager()->createQueryBuilder()
            ->select("$expCase as expRange, COUNT(p.id) as profCount")
            ->from(\App\Entity\Professor::class, 'p')
            ->groupBy('expRange')
            ->setMaxResults(10)
            ->getQuery()
            ->getScalarResult();

        return array_map(
            fn(array $row) => new \App\Dto\ProfessorExperienceDto((string)$row['expRange'], (int)$row['profCount']),
            $results
        );
    }

    /**
     * Group professors by country AND establishment
     *
     * @return list<\App\Dto\UserHierarchyDto>
     */
    public function countProfessorsByCountryAndEstablishment(): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('NEW App\Dto\UserHierarchyDto(p.pays, p.etablissement, COUNT(p.id))')
            ->from(\App\Entity\Professor::class, 'p')
            ->where('p.pays IS NOT NULL')
            ->andWhere('p.etablissement IS NOT NULL')
            ->groupBy('p.pays, p.etablissement')
            ->orderBy('p.pays', 'ASC')
            ->addOrderBy('COUNT(p.id)', 'DESC')
            ->setMaxResults(90)
            ->getQuery()
            ->getResult();
    }

    /**
     * Group students by country AND establishment
     *
     * @return list<\App\Dto\UserHierarchyDto>
     */
    public function countStudentsByCountryAndEstablishment(): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('NEW App\Dto\UserHierarchyDto(s.pays, s.etablissement, COUNT(s.id))')
            ->from(\App\Entity\Student::class, 's')
            ->where('s.pays IS NOT NULL')
            ->andWhere('s.etablissement IS NOT NULL')
            ->groupBy('s.pays, s.etablissement')
            ->orderBy('s.pays', 'ASC')
            ->addOrderBy('COUNT(s.id)', 'DESC')
            ->setMaxResults(90)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search and filter users with sorting
     *
     * @return list<User>
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

        // Sorting
        $allowedSortFields = [
            'dateInscription' => 'u.dateInscription',
            'experience' => 'u.anneesExperience', // Specific to Professor
        ];

        if ($sortBy && isset($allowedSortFields[$sortBy])) {
            $direction = strtoupper($sortDirection ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
            $qb->orderBy($allowedSortFields[$sortBy], $direction);
        } else {
            $qb->orderBy('u.dateInscription', 'DESC');
        }

        // Add a safety limit for non-paginated search results
        $qb->setMaxResults(90);

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
        $direction = strtoupper($sortDirection ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $orderField = isset($allowedSortFields[$sortBy]) ? $allowedSortFields[$sortBy] : 'u.dateInscription';

        // ── COUNT query ──────────────────────────────────────────────
        $countQb = $this->createQueryBuilder('u')->select('NEW App\Dto\SimpleCountDto(COUNT(u.id))');
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
        /** @var \App\Dto\SimpleCountDto $totalDto */
        $totalDto = $countQb->getQuery()->getSingleResult();
        $total = $totalDto->count;

        // ── DATA query ───────────────────────────────────────────────
        $page    = max(1, $page);
        $perPage = in_array($perPage, [10, 25, 50, 90], true) ? $perPage : 25;
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
     *
     * @return array<string, int>
     */
    public function getUsersKpiData(): array
    {
        $qb = $this->createQueryBuilder('u');
        
        // 1. Total users excluding admins
        /** @var \App\Dto\SimpleCountDto $totalDto */
        $totalDto = $qb->select('NEW App\Dto\SimpleCountDto(COUNT(u.id))')
            ->where('u.role != :adminRole')
            ->setParameter('adminRole', 'ROLE_ADMIN')
            ->getQuery()
            ->getSingleResult();
        $totalNonAdmins = $totalDto->count;

        $qb = $this->createQueryBuilder('u'); // Reset QB
        
        // 2. Aggregate counts by role and status excluding admins
        $results = $qb->select('NEW App\Dto\UserKpiRowDto(u.role, u.statut, COUNT(u.id))')
            ->where('u.role != :adminRole')
            ->setParameter('adminRole', 'ROLE_ADMIN')
            ->groupBy('u.role, u.statut')
            ->setMaxResults(50)
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

        /** @var \App\Dto\UserKpiRowDto[] $results */
        foreach ($results as $dto) {
            $role = $dto->role;
            $status = $dto->status;
            $count = $dto->count;

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
     *
     * @return list<User>
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
