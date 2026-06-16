<?php

namespace App\Entity;

use App\Repository\TarifPrestationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TarifPrestationRepository::class)]
class TarifPrestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $categorie = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?float $prix = null;

    #[ORM\Column(length: 30)]
    private ?string $unite = null;

    #[ORM\Column(nullable: true)]
    private ?int $surface_min = null;

    #[ORM\Column(nullable: true)]
    private ?int $surface_max = null;

    #[ORM\Column]
    private ?bool $sur_devis = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = $prix;

        return $this;
    }

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): static
    {
        $this->unite = $unite;

        return $this;
    }

    public function getSurfaceMin(): ?int
    {
        return $this->surface_min;
    }

    public function setSurfaceMin(?int $surface_min): static
    {
        $this->surface_min = $surface_min;

        return $this;
    }

    public function getSurfaceMax(): ?int
    {
        return $this->surface_max;
    }

    public function setSurfaceMax(?int $surface_max): static
    {
        $this->surface_max = $surface_max;

        return $this;
    }

    public function isSurDevis(): ?bool
    {
        return $this->sur_devis;
    }

    public function setSurDevis(bool $sur_devis): static
    {
        $this->sur_devis = $sur_devis;

        return $this;
    }
}
