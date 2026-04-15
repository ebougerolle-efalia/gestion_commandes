<?php

namespace App\Entity;

use App\Repository\CarteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarteRepository::class)]
#[ORM\Table(name: 'cartes')]
class Carte
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $nom;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    /** @var Collection<int, Categorie> */
    #[ORM\OneToMany(targetEntity: Categorie::class, mappedBy: 'carte', cascade: ['remove'])]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $categories;

    /** @var Collection<int, Produit> */
    #[ORM\OneToMany(targetEntity: Produit::class, mappedBy: 'carte', cascade: ['remove'])]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $produits;

    /** @var Collection<int, Commande> */
    #[ORM\OneToMany(targetEntity: Commande::class, mappedBy: 'carte', cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $commandes;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->categories = new ArrayCollection();
        $this->produits = new ArrayCollection();
        $this->commandes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $d): self { $this->createdAt = $d; return $this; }

    /** @return Collection<int, Categorie> */
    public function getCategories(): Collection { return $this->categories; }

    /** @return Collection<int, Produit> */
    public function getProduits(): Collection { return $this->produits; }

    /** @return Collection<int, Commande> */
    public function getCommandes(): Collection { return $this->commandes; }

    /** Préfixe pour les numéros de commande */
    public function getPrefix(): string
    {
        return str_replace(' ', '-', $this->nom);
    }

    public function __toString(): string { return $this->nom; }
}
