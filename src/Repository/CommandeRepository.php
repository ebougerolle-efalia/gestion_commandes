<?php

namespace App\Repository;

use App\Entity\Carte;
use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /** @return Commande[] */
    public function findByCarte(Carte $carte): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.carte = :carte')
            ->setParameter('carte', $carte)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Prochain numéro de commande pour une carte */
    public function getProchainNumero(Carte $carte): string
    {
        $prefix = $carte->getPrefix();

        $last = $this->createQueryBuilder('c')
            ->where('c.carte = :carte')
            ->setParameter('carte', $carte)
            ->orderBy('c.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($last && preg_match('/-(\d+)$/', $last->getNumero(), $m)) {
            $next = (int) $m[1] + 1;
        } else {
            $next = 1;
        }

        return sprintf('%s-%03d', $prefix, $next);
    }

    /** Prochain numéro de devis pour l'année courante */
    public function getProchainNumeroDevis(): int
    {
        $annee = (int) date('Y');
        $pattern = sprintf('D-%d-%%', $annee);

        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery(
            'SELECT numero_devis FROM commandes WHERE numero_devis LIKE ? ORDER BY numero_devis DESC LIMIT 1',
            [$pattern]
        )->fetchOne();

        if ($result && preg_match('/-(\d+)$/', $result, $m)) {
            return (int) $m[1] + 1;
        }

        return 1;
    }

    /** Statistiques pour une carte */
    public function getStats(Carte $carte): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $row = $conn->executeQuery(
            'SELECT COUNT(*) as total_commandes, COALESCE(SUM(total), 0) as chiffre_affaires, COALESCE(SUM(acompte), 0) as total_acomptes FROM commandes WHERE carte_id = ?',
            [$carte->getId()]
        )->fetchAssociative();

        $decompte = $conn->executeQuery(
            'SELECT lc.produit_nom, SUM(lc.quantite) as quantite, lc.unite
             FROM lignes_commande lc
             JOIN commandes c ON lc.commande_id = c.id
             WHERE c.carte_id = ?
             GROUP BY lc.produit_code, lc.produit_nom, lc.unite
             ORDER BY quantite DESC',
            [$carte->getId()]
        )->fetchAllAssociative();

        return [
            'totalCommandes' => (int) $row['total_commandes'],
            'chiffreAffaires' => (float) $row['chiffre_affaires'],
            'totalAcomptes' => (float) $row['total_acomptes'],
            'resteAEncaisser' => (float) $row['chiffre_affaires'] - (float) $row['total_acomptes'],
            'decompte' => $decompte,
        ];
    }

    /** Stats par date de retrait */
    public function getStatsParDate(Carte $carte): array
    {
        $conn = $this->getEntityManager()->getConnection();

        return $conn->executeQuery(
            'SELECT date_retrait, COUNT(*) as nb_commandes, SUM(total) as total
             FROM commandes
             WHERE carte_id = ? AND date_retrait IS NOT NULL
             GROUP BY date_retrait
             ORDER BY date_retrait',
            [$carte->getId()]
        )->fetchAllAssociative();
    }
}
