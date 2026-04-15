<?php

namespace App\Service;

use App\Entity\Carte;
use App\Entity\Categorie;
use App\Entity\Produit;
use Doctrine\ORM\EntityManagerInterface;

class CarteService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** Duplique une carte avec ses catégories et produits (sans les commandes) */
    public function dupliquer(Carte $source, string $nouveauNom): Carte
    {
        $nouvelle = new Carte();
        $nouvelle->setNom($nouveauNom);
        $this->em->persist($nouvelle);
        $this->em->flush(); // Pour obtenir l'id

        $mappingCategories = [];

        // Copier les catégories
        foreach ($source->getCategories() as $catSource) {
            $cat = new Categorie();
            $cat->setNom($catSource->getNom());
            $cat->setOrdre($catSource->getOrdre());
            $cat->setCarte($nouvelle);
            $this->em->persist($cat);
            $this->em->flush();
            $mappingCategories[$catSource->getId()] = $cat;
        }

        // Copier les produits
        $suffix = '_c' . $nouvelle->getId();
        foreach ($source->getProduits() as $prodSource) {
            $prod = new Produit();
            $prod->setCode($prodSource->getCode() . $suffix);
            $prod->setNom($prodSource->getNom());
            $prod->setPrix($prodSource->getPrix());
            $prod->setUnite($prodSource->getUnite());
            $prod->setAPeser($prodSource->isAPeser());
            $prod->setActif($prodSource->isActif());
            $prod->setCarte($nouvelle);

            if ($prodSource->getCategorie() && isset($mappingCategories[$prodSource->getCategorie()->getId()])) {
                $prod->setCategorie($mappingCategories[$prodSource->getCategorie()->getId()]);
            }

            $this->em->persist($prod);
        }

        $this->em->flush();

        return $nouvelle;
    }
}
