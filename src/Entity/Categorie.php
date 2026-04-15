<?php

namespace App\Entity;

use App\Repository\CategorieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\Table(name: 'categories')]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $ordre = 0;

    #[ORM\ManyToOne(targetEntity: Carte::class, inversedBy: 'categories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Carte $carte = null;

    /** @var Collection<int, Produit> */
    #[ORM\OneToMany(targetEntity: Produit::class, mappedBy: 'categorie')]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $produits;

    public function __construct()
    {
        $this->produits = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $ordre): self { $this->ordre = $ordre; return $this; }
    public function getCarte(): ?Carte { return $this->carte; }
    public function setCarte(?Carte $carte): self { $this->carte = $carte; return $this; }

    /** @return Collection<int, Produit> */
    public function getProduits(): Collection { return $this->produits; }

    public function __toString(): string { return $this->nom; }
}
