<?php

namespace App\Repository;

use App\Entity\Subject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subject>
 */
class SubjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subject::class);
    }

    /**
     * Fetch all subjects with chapters eager-loaded to avoid N+1.
     * @return Subject[]
     */
    public function findAllWithChapters(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.chapters', 'c')
            ->addSelect('c')
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('c.orderNo', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
