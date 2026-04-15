<?php

namespace App\Repository;

use App\Entity\Carte;
use App\Entity\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categorie>
 */
class CategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

    /** @return Categorie[] */
    public function findByCarte(Carte $carte): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.carte = :carte')
            ->setParameter('carte', $carte)
            ->orderBy('c.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
