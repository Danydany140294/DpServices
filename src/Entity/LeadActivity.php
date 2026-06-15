<?php

namespace App\Entity;

use App\Repository\LeadActivityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeadActivityRepository::class)]
class LeadActivity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $type = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $result = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $activityDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $followUpDate = null;

    #[ORM\ManyToOne(inversedBy: 'leadActivities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lead $lead = null;

    public function __construct()
{
    $this->activityDate = new \DateTimeImmutable();
}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): static
    {
        $this->result = $result;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getActivityDate(): ?\DateTimeImmutable
    {
        return $this->activityDate;
    }

    public function setActivityDate(\DateTimeImmutable $activityDate): static
    {
        $this->activityDate = $activityDate;

        return $this;
    }

    public function getFollowUpDate(): ?\DateTime
    {
        return $this->followUpDate;
    }

    public function setFollowUpDate(?\DateTime $followUpDate): static
    {
        $this->followUpDate = $followUpDate;

        return $this;
    }

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setLead(?Lead $lead): static
    {
        $this->lead = $lead;

        return $this;
    }
}
