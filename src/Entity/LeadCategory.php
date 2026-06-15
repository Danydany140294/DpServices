<?php

namespace App\Entity;

use App\Repository\LeadCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeadCategoryRepository::class)]
class LeadCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $scoreBonus = null;

    /**
     * @var Collection<int, Lead>
     */
    #[ORM\OneToMany(targetEntity: Lead::class, mappedBy: 'category')]
    private Collection $leads;

    public function __construct()
    {
        $this->leads = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getScoreBonus(): ?int
    {
        return $this->scoreBonus;
    }

    public function setScoreBonus(int $scoreBonus): static
    {
        $this->scoreBonus = $scoreBonus;

        return $this;
    }

    /**
     * @return Collection<int, Lead>
     */
    public function getLeads(): Collection
    {
        return $this->leads;
    }

    public function addLead(Lead $lead): static
    {
        if (!$this->leads->contains($lead)) {
            $this->leads->add($lead);
            $lead->setCategory($this);
        }

        return $this;
    }

    public function removeLead(Lead $lead): static
    {
        if ($this->leads->removeElement($lead)) {
            // set the owning side to null (unless already changed)
            if ($lead->getCategory() === $this) {
                $lead->setCategory(null);
            }
        }

        return $this;
    }
}
