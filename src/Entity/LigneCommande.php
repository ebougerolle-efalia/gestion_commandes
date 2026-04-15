<?php

namespace App\Entity;

use App\Repository\LigneCommandeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LigneCommandeRepository::class)]
#[ORM\Table(name: 'lignes_commande')]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    #[ORM\Column(length: 100)]
    private string $produitCode;

    #[ORM\Column(length: 255)]
    private string $produitNom;

    #[ORM\Column(type: 'float')]
    private float $quantite;

    #[ORM\Column(type: 'float')]
    private float $prixUnitaire;

    #[ORM\Column(length: 20, options: ['default' => 'pièce'])]
    private string $unite = 'pièce';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $aPeser = false;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private float $montantPese = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $produitFait = false;

    public function getId(): ?int { return $this->id; }

    public function getCommande(): ?Commande { return $this->commande; }
    public function setCommande(?Commande $c): self { $this->commande = $c; return $this; }

    public function getProduitCode(): string { return $this->produitCode; }
    public function setProduitCode(string $v): self { $this->produitCode = $v; return $this; }

    public function getProduitNom(): string { return $this->produitNom; }
    public function setProduitNom(string $v): self { $this->produitNom = $v; return $this; }

    public function getQuantite(): float { return $this->quantite; }
    public function setQuantite(float $v): self { $this->quantite = $v; return $this; }

    public function getPrixUnitaire(): float { return $this->prixUnitaire; }
    public function setPrixUnitaire(float $v): self { $this->prixUnitaire = $v; return $this; }

    public function getUnite(): string { return $this->unite; }
    public function setUnite(string $v): self { $this->unite = $v; return $this; }

    public function isAPeser(): bool { return $this->aPeser; }
    public function setAPeser(bool $v): self { $this->aPeser = $v; return $this; }

    public function getMontantPese(): float { return $this->montantPese; }
    public function setMontantPese(float $v): self { $this->montantPese = $v; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $v): self { $this->commentaire = $v; return $this; }

    public function isProduitFait(): bool { return $this->produitFait; }
    public function setProduitFait(bool $v): self { $this->produitFait = $v; return $this; }

    /** Montant total de la ligne */
    public function getMontant(): float
    {
        if ($this->aPeser && $this->montantPese > 0) {
            return $this->montantPese;
        }
        return round($this->quantite * $this->prixUnitaire, 2);
    }

    /** Libellé du montant pour affichage */
    public function getMontantLabel(): string
    {
        if ($this->aPeser && $this->montantPese == 0) {
            return 'À peser';
        }
        return number_format($this->getMontant(), 2, ',', ' ') . ' €';
    }
}
