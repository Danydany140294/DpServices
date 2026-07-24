<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countUnreadFor(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipient = :user')
            ->andWhere('n.isRead = false')
            ->andWhere('n.type != :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', Notification::TYPE_MISSION_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Notification[]
     */
    public function findUnreadFor(User $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.recipient = :user')
            ->andWhere('n.isRead = false')
            ->andWhere('n.type != :completed')
            ->setParameter('user', $user)
            ->setParameter('completed', Notification::TYPE_MISSION_COMPLETED)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}