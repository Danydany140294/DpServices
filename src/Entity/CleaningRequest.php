<?php

namespace App\Entity;

use App\Repository\CleaningRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CleaningRequestRepository::class)]
class CleaningRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $scheduledDate = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTime $scheduledTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'cleaningRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[ORM\ManyToOne(inversedBy: 'cleaningRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CleaningService $service = null;

    #[ORM\ManyToOne(inversedBy: 'cleaningRequests')]
    private ?User $assignedCleaner = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScheduledDate(): ?\DateTime
    {
        return $this->scheduledDate;
    }

    public function setScheduledDate(?\DateTime $scheduledDate): static
{
    $this->scheduledDate = $scheduledDate;

    return $this;
}

    public function getScheduledTime(): ?\DateTime
    {
        return $this->scheduledTime;
    }

    public function setScheduledTime(?\DateTime $scheduledTime): static
{
    $this->scheduledTime = $scheduledTime;

    return $this;
}

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function setProperty(?Property $property): static
    {
        $this->property = $property;

        return $this;
    }

    public function getService(): ?CleaningService
    {
        return $this->service;
    }

    public function setService(?CleaningService $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getAssignedCleaner(): ?User
    {
        return $this->assignedCleaner;
    }

    public function setAssignedCleaner(?User $assignedCleaner): static
    {
        $this->assignedCleaner = $assignedCleaner;

        return $this;
    }
}
