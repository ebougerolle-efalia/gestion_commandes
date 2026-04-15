<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class BackupService
{
    public function __construct(private Connection $conn)
    {
    }

    /** Export complet en tableau associatif */
    public function export(): array
    {
        return [
            'version' => '2.0',
            'date' => (new \DateTime())->format('c'),
            'data' => [
                'cartes' => $this->conn->executeQuery('SELECT * FROM cartes ORDER BY id')->fetchAllAssociative(),
                'categories' => $this->conn->executeQuery('SELECT * FROM categories ORDER BY carte_id, ordre')->fetchAllAssociative(),
                'produits' => $this->conn->executeQuery('SELECT * FROM produits ORDER BY carte_id, nom')->fetchAllAssociative(),
                'commandes' => $this->exportCommandes(),
            ],
        ];
    }

    private function exportCommandes(): array
    {
        $commandes = $this->conn->executeQuery('SELECT * FROM commandes ORDER BY carte_id, created_at DESC')->fetchAllAssociative();

        foreach ($commandes as &$cmd) {
            $cmd['lignes'] = $this->conn->executeQuery(
                'SELECT * FROM lignes_commande WHERE commande_id = ?',
                [$cmd['id']]
            )->fetchAllAssociative();
        }

        return $commandes;
    }

    /** Import depuis un tableau (structure version 2.0) */
    public function import(array $data): array
    {
        if (!isset($data['categories'], $data['produits'], $data['commandes'])) {
            throw new \InvalidArgumentException('Format de sauvegarde invalide');
        }

        $this->conn->beginTransaction();

        try {
            // Vider les tables
            $this->conn->executeStatement('DELETE FROM lignes_commande');
            $this->conn->executeStatement('DELETE FROM commandes');
            $this->conn->executeStatement('DELETE FROM produits');
            $this->conn->executeStatement('DELETE FROM categories');
            $this->conn->executeStatement('DELETE FROM cartes');

            // Restaurer les cartes
            if (!empty($data['cartes'])) {
                foreach ($data['cartes'] as $c) {
                    $this->conn->executeStatement(
                        'INSERT INTO cartes (id, nom, created_at) VALUES (?, ?, ?)',
                        [$c['id'], $c['nom'], $c['created_at'] ?? date('Y-m-d H:i:s')]
                    );
                }
            } else {
                $this->conn->executeStatement("INSERT INTO cartes (id, nom) VALUES (1, 'Import')");
            }

            // Restaurer les catégories
            foreach ($data['categories'] as $c) {
                $this->conn->executeStatement(
                    'INSERT INTO categories (id, nom, ordre, carte_id) VALUES (?, ?, ?, ?)',
                    [$c['id'], $c['nom'], $c['ordre'] ?? 0, $c['carte_id'] ?? 1]
                );
            }

            // Restaurer les produits
            foreach ($data['produits'] as $p) {
                $this->conn->executeStatement(
                    'INSERT INTO produits (id, code, nom, prix, unite, categorie_id, actif, a_peser, carte_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $p['id'], $p['code'], $p['nom'], $p['prix'],
                        $p['unite'] ?? 'pièce', $p['categorie_id'] ?? null,
                        $p['actif'] ?? 1, $p['a_peser'] ?? 0, $p['carte_id'] ?? 1,
                    ]
                );
            }

            // Restaurer les commandes
            foreach ($data['commandes'] as $cmd) {
                $this->conn->executeStatement(
                    'INSERT INTO commandes (id, numero, numero_devis, nom_client, telephone, total, acompte, date_retrait, creneau_retrait, commentaire, date_creation, created_at, carte_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $cmd['id'], $cmd['numero'], $cmd['numero_facture'] ?? $cmd['numero_devis'] ?? null,
                        $cmd['nom_client'], $cmd['telephone'], $cmd['total'], $cmd['acompte'],
                        $cmd['date_retrait'] ?? null, $cmd['creneau_retrait'] ?? null,
                        $cmd['commentaire'] ?? null, $cmd['date_creation'],
                        $cmd['created_at'] ?? date('Y-m-d H:i:s'), $cmd['carte_id'] ?? 1,
                    ]
                );

                foreach ($cmd['lignes'] ?? [] as $l) {
                    $this->conn->executeStatement(
                        'INSERT INTO lignes_commande (id, commande_id, produit_code, produit_nom, quantite, prix_unitaire, unite, a_peser, montant_pese, commentaire, produit_fait) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [
                            $l['id'], $l['commande_id'], $l['produit_code'], $l['produit_nom'],
                            $l['quantite'], $l['prix_unitaire'], $l['unite'] ?? 'pièce',
                            $l['a_peser'] ?? 0, $l['montant_pese'] ?? 0,
                            $l['commentaire'] ?? null, $l['produit_fait'] ?? 0,
                        ]
                    );
                }
            }

            $this->conn->commit();

            return [
                'categories' => count($data['categories']),
                'produits' => count($data['produits']),
                'commandes' => count($data['commandes']),
            ];
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }
}
