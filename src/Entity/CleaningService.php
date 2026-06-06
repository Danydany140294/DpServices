<?php

namespace App\Entity;

use App\Repository\CleaningServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CleaningServiceRepository::class)]
class CleaningService
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $duration = null;

    /**
     * @var Collection<int, CleaningRequest>
     */
    #[ORM\OneToMany(targetEntity: CleaningRequest::class, mappedBy: 'service')]
    private Collection $cleaningRequests;

    public function __construct()
    {
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

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

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
            $cleaningRequest->setService($this);
        }

        return $this;
    }

    public function removeCleaningRequest(CleaningRequest $cleaningRequest): static
    {
        if ($this->cleaningRequests->removeElement($cleaningRequest)) {
            // set the owning side to null (unless already changed)
            if ($cleaningRequest->getService() === $this) {
                $cleaningRequest->setService(null);
            }
        }

        return $this;
    }
}
