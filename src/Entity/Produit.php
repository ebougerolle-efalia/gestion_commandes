<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
#[ORM\Table(name: 'produits')]
#[ORM\Index(name: 'idx_produit_nom', columns: ['nom'])]
class Produit
{
    public const UNITE_PIECE = 'pièce';
    public const UNITE_KG = 'kg';
    public const UNITE_GRAMMES = 'grammes';
    public const UNITE_PERS = 'pers.';

    public const UNITES = [
        'pièce' => self::UNITE_PIECE,
        'kg' => self::UNITE_KG,
        'grammes' => self::UNITE_GRAMMES,
        'pers.' => self::UNITE_PERS,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $nom;

    #[ORM\Column(type: 'float')]
    private float $prix;

    #[ORM\Column(length: 20, options: ['default' => 'pièce'])]
    private string $unite = 'pièce';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $aPeser = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    #[ORM\ManyToOne(targetEntity: Categorie::class, inversedBy: 'produits')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Categorie $categorie = null;

    #[ORM\ManyToOne(targetEntity: Carte::class, inversedBy: 'produits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Carte $carte = null;

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): self { $this->code = $code; return $this; }
    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }
    public function getPrix(): float { return $this->prix; }
    public function setPrix(float $prix): self { $this->prix = $prix; return $this; }
    public function getUnite(): string { return $this->unite; }
    public function setUnite(string $unite): self { $this->unite = $unite; return $this; }
    public function isAPeser(): bool { return $this->aPeser; }
    public function setAPeser(bool $v): self { $this->aPeser = $v; return $this; }
    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $v): self { $this->actif = $v; return $this; }
    public function getCategorie(): ?Categorie { return $this->categorie; }
    public function setCategorie(?Categorie $c): self { $this->categorie = $c; return $this; }
    public function getCarte(): ?Carte { return $this->carte; }
    public function setCarte(?Carte $carte): self { $this->carte = $carte; return $this; }

    /** Génère un code unique à partir du nom */
    public function generateCode(): self
    {
        $base = mb_strtolower($this->nom);
        $base = transliterator_transliterate('Any-Latin; Latin-ASCII', $base);
        $base = preg_replace('/[^a-z0-9]+/', '_', $base);
        $base = substr($base, 0, 30);
        $this->code = $base . '_' . time() . '_' . random_int(100, 999);
        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (%.2f€/%s)', $this->nom, $this->prix, $this->unite);
    }
}
