<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commandes')]
#[ORM\Index(name: 'idx_commande_numero', columns: ['numero'])]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $numero;

    #[ORM\Column(length: 50, nullable: true, unique: true)]
    private ?string $numeroDevis = null;

    #[ORM\Column(length: 255)]
    private string $nomClient;

    #[ORM\Column(length: 50)]
    private string $telephone;

    #[ORM\Column(type: 'float')]
    private float $total = 0;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private float $acompte = 0;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateRetrait = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $creneauRetrait = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $createdAt;

    #[ORM\ManyToOne(targetEntity: Carte::class, inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Carte $carte = null;

    /** @var Collection<int, LigneCommande> */
    #[ORM\OneToMany(targetEntity: LigneCommande::class, mappedBy: 'commande', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNumero(): string { return $this->numero; }
    public function setNumero(string $v): self { $this->numero = $v; return $this; }

    public function getNumeroDevis(): ?string { return $this->numeroDevis; }
    public function setNumeroDevis(?string $v): self { $this->numeroDevis = $v; return $this; }

    public function getNomClient(): string { return $this->nomClient; }
    public function setNomClient(string $v): self { $this->nomClient = $v; return $this; }

    public function getTelephone(): string { return $this->telephone; }
    public function setTelephone(string $v): self { $this->telephone = $v; return $this; }

    public function getTotal(): float { return $this->total; }
    public function setTotal(float $v): self { $this->total = $v; return $this; }

    public function getAcompte(): float { return $this->acompte; }
    public function setAcompte(float $v): self { $this->acompte = $v; return $this; }

    public function getReste(): float { return $this->total - $this->acompte; }

    public function getDateRetrait(): ?\DateTimeInterface { return $this->dateRetrait; }
    public function setDateRetrait(?\DateTimeInterface $v): self { $this->dateRetrait = $v; return $this; }

    public function getCreneauRetrait(): ?string { return $this->creneauRetrait; }
    public function setCreneauRetrait(?string $v): self { $this->creneauRetrait = $v; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $v): self { $this->commentaire = $v; return $this; }

    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $v): self { $this->dateCreation = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): self { $this->createdAt = $v; return $this; }

    public function getCarte(): ?Carte { return $this->carte; }
    public function setCarte(?Carte $carte): self { $this->carte = $carte; return $this; }

    /** @return Collection<int, LigneCommande> */
    public function getLignes(): Collection { return $this->lignes; }

    public function addLigne(LigneCommande $ligne): self
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setCommande($this);
        }
        return $this;
    }

    public function removeLigne(LigneCommande $ligne): self
    {
        if ($this->lignes->removeElement($ligne)) {
            if ($ligne->getCommande() === $this) {
                $ligne->setCommande(null);
            }
        }
        return $this;
    }

    public function clearLignes(): self
    {
        $this->lignes->clear();
        return $this;
    }

    /** Recalcule le total depuis les lignes */
    public function recalculerTotal(): self
    {
        $total = 0;
        foreach ($this->lignes as $ligne) {
            $total += $ligne->getMontant();
        }
        $this->total = round($total, 2);
        return $this;
    }

    /** Génère un numéro de devis D-AAAA-XXXX */
    public function genererNumeroDevis(int $sequence): self
    {
        $annee = (int) $this->dateCreation->format('Y');
        $this->numeroDevis = sprintf('D-%d-%04d', $annee, $sequence);
        return $this;
    }

    public function __toString(): string { return $this->numero; }
}
