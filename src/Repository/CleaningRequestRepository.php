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

    public function findUpcomingForCleaner(mixed $user, \DateTime $from): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.property', 'p')
            ->where('r.assignedCleaner = :user')
            ->andWhere('r.scheduledDate >= :from')
            ->andWhere('r.status != :cancelled')
            ->setParameter('user', $user)
            ->setParameter('from', $from)
            ->setParameter('cancelled', 'CANCELLED')
            ->orderBy('r.scheduledDate', 'ASC')
            ->addOrderBy('r.scheduledTime', 'ASC');

        if ($user->getSector()) {
            $qb->andWhere('LOWER(p.city) LIKE :sector')
               ->setParameter('sector', '%' . strtolower($user->getSector()) . '%');
        }

        return $qb->getQuery()->getResult();
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