<?php

namespace App\Service;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    ) {}

    public function log(string $action, ?string $description = null): void
    {
        $log = new ActivityLog();
        $log->setAction($action);
        $log->setDescription($description);
        $log->setCreatedAt(new \DateTimeImmutable());
        $log->setAuthor($this->security->getUser());

        $this->em->persist($log);
        $this->em->flush();
    }
}