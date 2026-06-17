<?php

namespace App\Repository;

use App\Entity\Lead;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lead>
 */
class LeadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lead::class);
    }

    //    /**
    //     * @return Lead[] Returns an array of Lead objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('l.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Lead
    //    {
    //        return $this->createQueryBuilder('l')
    //            ->andWhere('l.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function findWithFilters(?string $status, ?string $city, ?string $categoryId): array
{
    $qb = $this->createQueryBuilder('l')
        ->leftJoin('l.category', 'c')
        ->orderBy('l.score', 'DESC');

    if ($status) {
        $qb->andWhere('l.status = :status')
           ->setParameter('status', $status);
    }

    if ($city) {
        $qb->andWhere('l.city LIKE :city')
           ->setParameter('city', '%' . $city . '%');
    }

    if ($categoryId) {
        $qb->andWhere('c.id = :category')
           ->setParameter('category', $categoryId);
    }

    return $qb->getQuery()->getResult();
}

public function findWithFiltersQuery(?string $status, ?string $city, ?string $categoryId, ?string $scoreMin = null, ?string $followUp = null): \Doctrine\ORM\Query
{
    $qb = $this->createQueryBuilder('l')
        ->leftJoin('l.category', 'c')
        ->orderBy('l.score', 'DESC');

    if ($status) {
        $qb->andWhere('l.status = :status')
           ->setParameter('status', $status);
    }
    if ($city) {
        $qb->andWhere('l.city LIKE :city')
           ->setParameter('city', '%' . $city . '%');
    }
    if ($categoryId) {
        $qb->andWhere('c.id = :category')
           ->setParameter('category', $categoryId);
    }
    if ($scoreMin) {
        $qb->andWhere('l.score >= :scoreMin')
           ->setParameter('scoreMin', (int) $scoreMin);
    }
    if ($followUp) {
        $qb->andWhere('l.nextFollowUp = :followUp')
           ->setParameter('followUp', new \DateTime($followUp));
    }

    return $qb->getQuery();
}

public function findNoResponseSince7Days(): array
{
    $sevenDaysAgo = new \DateTimeImmutable('-7 days');

    return $this->createQueryBuilder('l')
        ->where('l.status IN (:statuses)')
        ->andWhere('l.createdAt <= :sevenDaysAgo')
        ->setParameter('statuses', ['CONTACTED', 'DISCUSSION', 'QUOTE_SENT'])
        ->setParameter('sevenDaysAgo', $sevenDaysAgo)
        ->orderBy('l.score', 'DESC')
        ->getQuery()
        ->getResult();
}
}
