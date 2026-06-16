<?php

namespace App\Entity;

use App\Repository\LeadRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeadRepository::class)]
class Lead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $companyName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    private ?string $city = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $source = null;

    #[ORM\Column]
    private ?int $score = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(nullable: true)]
    private ?float $googleRating = null;

    #[ORM\Column(nullable: true)]
    private ?int $googleReviews = null;

    #[ORM\Column]
    private ?bool $hasAirbnb = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $nextFollowUp = null;

    #[ORM\ManyToOne(inversedBy: 'leads')]
    private ?LeadCategory $category = null;

    /**
     * @var Collection<int, LeadActivity>
     */
    #[ORM\OneToMany(targetEntity: LeadActivity::class, mappedBy: 'lead', orphanRemoval: true)]
    private Collection $leadActivities;

    /**
     * @var Collection<int, Devis>
     */
    #[ORM\OneToMany(targetEntity: Devis::class, mappedBy: 'lead')]
    private Collection $devis;

    public function __construct()
{
    $this->createdAt = new \DateTimeImmutable();
    $this->score = 0;
    $this->status = 'NEW';
    $this->hasAirbnb = false;
    $this->leadActivities = new ArrayCollection();
    $this->devis = new ArrayCollection();
}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function setContactName(?string $contactName): static
    {
        $this->contactName = $contactName;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

        return $this;
    }

    public function getGoogleRating(): ?float
    {
        return $this->googleRating;
    }

    public function setGoogleRating(?float $googleRating): static
    {
        $this->googleRating = $googleRating;

        return $this;
    }

    public function getGoogleReviews(): ?int
    {
        return $this->googleReviews;
    }

    public function setGoogleReviews(?int $googleReviews): static
    {
        $this->googleReviews = $googleReviews;

        return $this;
    }

    public function hasAirbnb(): ?bool
    {
        return $this->hasAirbnb;
    }

    public function setHasAirbnb(bool $hasAirbnb): static
    {
        $this->hasAirbnb = $hasAirbnb;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getNextFollowUp(): ?\DateTime
    {
        return $this->nextFollowUp;
    }

    public function setNextFollowUp(?\DateTime $nextFollowUp): static
    {
        $this->nextFollowUp = $nextFollowUp;

        return $this;
    }

    public function getCategory(): ?LeadCategory
    {
        return $this->category;
    }

    public function setCategory(?LeadCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, LeadActivity>
     */
    public function getLeadActivities(): Collection
    {
        return $this->leadActivities;
    }

    public function addLeadActivity(LeadActivity $leadActivity): static
    {
        if (!$this->leadActivities->contains($leadActivity)) {
            $this->leadActivities->add($leadActivity);
            $leadActivity->setLead($this);
        }

        return $this;
    }

    public function removeLeadActivity(LeadActivity $leadActivity): static
    {
        if ($this->leadActivities->removeElement($leadActivity)) {
            // set the owning side to null (unless already changed)
            if ($leadActivity->getLead() === $this) {
                $leadActivity->setLead(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Devis>
     */
    public function getDevis(): Collection
    {
        return $this->devis;
    }

    public function addDevi(Devis $devi): static
    {
        if (!$this->devis->contains($devi)) {
            $this->devis->add($devi);
            $devi->setLead($this);
        }

        return $this;
    }

    public function removeDevi(Devis $devi): static
    {
        if ($this->devis->removeElement($devi)) {
            // set the owning side to null (unless already changed)
            if ($devi->getLead() === $this) {
                $devi->setLead(null);
            }
        }

        return $this;
    }
}
