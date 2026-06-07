<?php

namespace App\Repository;

use App\Entity\CleaningRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CleaningRequest>
 */
class CleaningRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CleaningRequest::class);
    }

    //    /**
    //     * @return CleaningRequest[] Returns an array of CleaningRequest objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?CleaningRequest
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findUpcomingForCleaner(mixed $user, \DateTime $from): array
{
    return $this->createQueryBuilder('r')
        ->where('r.assignedCleaner = :user')
        ->andWhere('r.scheduledDate >= :from')
        ->andWhere('r.status != :cancelled')
        ->setParameter('user', $user)
        ->setParameter('from', $from)
        ->setParameter('cancelled', 'CANCELLED')
        ->orderBy('r.scheduledDate', 'ASC')
        ->addOrderBy('r.scheduledTime', 'ASC')
        ->getQuery()
        ->getResult();
}

public function findCompletedForCleaner(mixed $user): array
{
    return $this->createQueryBuilder('r')
        ->where('r.assignedCleaner = :user')
        ->andWhere('r.status = :completed')
        ->setParameter('user', $user)
        ->setParameter('completed', 'COMPLETED')
        ->orderBy('r.scheduledDate', 'DESC')
        ->getQuery()
        ->getResult();
}
}
