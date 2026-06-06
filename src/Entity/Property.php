<?php

namespace App\Entity;

use App\Repository\PropertyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PropertyRepository::class)]
class Property
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 7)]
    private ?string $color = null;

    #[ORM\Column(length: 100)]
    private ?string $city = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'properties')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    /**
     * @var Collection<int, CleaningRequest>
     */
    #[ORM\OneToMany(targetEntity: CleaningRequest::class, mappedBy: 'property')]
    private Collection $cleaningRequests;

    public function __construct()
{
    $this->createdAt = new \DateTimeImmutable();
    $this->cleaningRequests = new ArrayCollection();
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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * @return Collection<int, CleaningRequest>
     */
    public function getCleaningRequests(): Collection
    {
        return $this->cleaningRequests;
    }

    public function addCleaningRequest(CleaningRequest $cleaningRequest): static
    {
        if (!$this->cleaningRequests->contains($cleaningRequest)) {
            $this->cleaningRequests->add($cleaningRequest);
            $cleaningRequest->setProperty($this);
        }

        return $this;
    }

    public function removeCleaningRequest(CleaningRequest $cleaningRequest): static
    {
        if ($this->cleaningRequests->removeElement($cleaningRequest)) {
            // set the owning side to null (unless already changed)
            if ($cleaningRequest->getProperty() === $this) {
                $cleaningRequest->setProperty(null);
            }
        }

        return $this;
    }
}
