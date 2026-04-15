<?php

namespace App\Repository;

use App\Entity\Carte;
use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /** @return Produit[] */
    public function findByCarte(Carte $carte): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.categorie', 'c')
            ->where('p.carte = :carte')
            ->setParameter('carte', $carte)
            ->orderBy('c.ordre', 'ASC')
            ->addOrderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** Recherche par nom (autocomplétion) - retourne les actifs seulement */
    public function search(Carte $carte, string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.categorie', 'c')
            ->select('p.id', 'p.code', 'p.nom', 'p.prix', 'p.unite', 'p.aPeser', 'c.nom AS categorie')
            ->where('p.carte = :carte')
            ->andWhere('p.actif = true')
            ->andWhere('p.nom LIKE :q')
            ->setParameter('carte', $carte)
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('p.nom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return Produit[] groupés par catégorie */
    public function findByCarteGrouped(Carte $carte): array
    {
        $produits = $this->findByCarte($carte);
        $grouped = ['_sans_categorie' => []];

        foreach ($produits as $p) {
            $cat = $p->getCategorie();
            if ($cat) {
                $key = $cat->getId();
                if (!isset($grouped[$key])) {
                    $grouped[$key] = ['categorie' => $cat, 'produits' => []];
                }
                $grouped[$key]['produits'][] = $p;
            } else {
                $grouped['_sans_categorie'][] = $p;
            }
        }

        return $grouped;
    }
}
