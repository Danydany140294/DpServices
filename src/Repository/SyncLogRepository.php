<?php

namespace App\Repository;

use App\Entity\SyncLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SyncLog>
 */
class SyncLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncLog::class);
    }

    /**
     * Retourne les N logs les plus récents, tous types confondus.
     *
     * @return SyncLog[]
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le log le plus récent dont l'action n'est PAS une erreur,
     * utilisé comme indicateur de "dernière synchronisation réussie".
     */
    public function findLastSuccessful(): ?SyncLog
    {
        return $this->createQueryBuilder('s')
            ->where('s.action != :error')
            ->setParameter('error', SyncLog::ACTION_ERROR)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compte le nombre d'erreurs survenues depuis une date donnée.
     * Utilisé pour afficher "X erreurs dans les dernières 24h", par exemple.
     */
    public function countErrorsSince(\DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.action = :error')
            ->andWhere('s.createdAt >= :since')
            ->setParameter('error', SyncLog::ACTION_ERROR)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Retourne les N erreurs les plus récentes uniquement.
     *
     * @return SyncLog[]
     */
    public function findRecentErrors(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.action = :error')
            ->setParameter('error', SyncLog::ACTION_ERROR)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}