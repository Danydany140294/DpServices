<?php

namespace App\Entity;

use App\Repository\CleaningRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CleaningRequestRepository::class)]
class CleaningRequest
{


    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VALIDATED = 'VALIDATED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VALIDATED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];



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

    #[ORM\Column(length: 40)]
    private ?string $status = null;

    #[ORM\ManyToOne(inversedBy: 'cleaningRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Property $property = null;

    #[ORM\ManyToOne(inversedBy: 'cleaningRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CleaningService $service = null;

    #[ORM\ManyToOne(inversedBy: 'cleaningRequests')]
    private ?User $assignedCleaner = null;

    // ---- Champs de synchronisation Google Calendar (V3) ----

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleEventId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $lastSyncAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $syncSource = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $syncStatus = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $needsConfirmation = false;

    // ---- Champs de gestion des conflits (V3 - Semaine 4) ----

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $pendingScheduledDate = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $pendingScheduledTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pendingComment = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $syncInProgress = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $openedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $assignedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $reminderSentAt = null;

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

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
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

    public function getGoogleEventId(): ?string
    {
        return $this->googleEventId;
    }

    public function setGoogleEventId(?string $googleEventId): static
    {
        $this->googleEventId = $googleEventId;

        return $this;
    }

    public function getLastSyncAt(): ?\DateTime
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTime $lastSyncAt): static
    {
        $this->lastSyncAt = $lastSyncAt;

        return $this;
    }

    public function getSyncSource(): ?string
    {
        return $this->syncSource;
    }

    public function setSyncSource(?string $syncSource): static
    {
        $this->syncSource = $syncSource;

        return $this;
    }

    public function getSyncStatus(): ?string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(?string $syncStatus): static
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function isNeedsConfirmation(): bool
    {
        return $this->needsConfirmation;
    }

    public function setNeedsConfirmation(bool $needsConfirmation): static
    {
        $this->needsConfirmation = $needsConfirmation;

        return $this;
    }

    public function getPendingScheduledDate(): ?\DateTime
    {
        return $this->pendingScheduledDate;
    }

    public function setPendingScheduledDate(?\DateTime $pendingScheduledDate): static
    {
        $this->pendingScheduledDate = $pendingScheduledDate;

        return $this;
    }

    public function getPendingScheduledTime(): ?\DateTime
    {
        return $this->pendingScheduledTime;
    }

    public function setPendingScheduledTime(?\DateTime $pendingScheduledTime): static
    {
        $this->pendingScheduledTime = $pendingScheduledTime;

        return $this;
    }

    public function getPendingComment(): ?string
    {
        return $this->pendingComment;
    }

    public function setPendingComment(?string $pendingComment): static
    {
        $this->pendingComment = $pendingComment;

        return $this;
    }

    public function isSyncInProgress(): bool
    {
        return $this->syncInProgress;
    }

    public function setSyncInProgress(bool $syncInProgress): static
    {
        $this->syncInProgress = $syncInProgress;

        return $this;
    }

    public function getOpenedAt(): ?\DateTime
    {
        return $this->openedAt;
    }

    public function setOpenedAt(?\DateTime $openedAt): static
    {
        $this->openedAt = $openedAt;

        return $this;
    }

    public function getAssignedAt(): ?\DateTime
    {
        return $this->assignedAt;
    }

    public function setAssignedAt(?\DateTime $assignedAt): static
    {
        $this->assignedAt = $assignedAt;

        return $this;
    }

    public function getReminderSentAt(): ?\DateTime
    {
        return $this->reminderSentAt;
    }

    public function setReminderSentAt(?\DateTime $reminderSentAt): static
    {
        $this->reminderSentAt = $reminderSentAt;

        return $this;
    }
}

