<?php

namespace App\Controller;

use App\Entity\Carte;
use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Repository\CarteRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CommandeController extends AbstractController
{
    /** Page d'accueil — redirige vers la première carte ou la gestion des cartes */
    #[Route('/', name: 'app_home')]
    public function home(CarteRepository $carteRepo): Response
    {
        $cartes = $carteRepo->findAllOrdered();
        if (count($cartes) === 0) {
            return $this->redirectToRoute('app_carte_index');
        }
        return $this->redirectToRoute('app_commande_index', ['carteId' => $cartes[0]->getId()]);
    }

    /** Liste des commandes d'une carte */
    #[Route('/commandes/{carteId}', name: 'app_commande_index', requirements: ['carteId' => '\d+'])]
    public function index(
        int $carteId,
        CarteRepository $carteRepo,
        CommandeRepository $cmdRepo,
        EntityManagerInterface $em
    ): Response {
        $carte = $carteRepo->find($carteId);
        if (!$carte) {
            throw $this->createNotFoundException('Carte non trouvée');
        }

        return $this->render('commande/index.html.twig', [
            'carte' => $carte,
            'cartes' => $carteRepo->findAllOrdered(),
            'commandes' => $cmdRepo->findByCarte($carte),
            'prochainNumero' => $cmdRepo->getProchainNumero($carte),
        ]);
    }

    /** Formulaire nouvelle commande */
    #[Route('/commandes/{carteId}/nouvelle', name: 'app_commande_new')]
    public function new(
        int $carteId,
        CarteRepository $carteRepo,
        CommandeRepository $cmdRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $carte = $carteRepo->find($carteId);
        if (!$carte) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            return $this->handleSave($carte, null, $request, $cmdRepo, $em);
        }

        return $this->render('commande/form.html.twig', [
            'carte' => $carte,
            'cartes' => $carteRepo->findAllOrdered(),
            'commande' => null,
            'prochainNumero' => $cmdRepo->getProchainNumero($carte),
        ]);
    }

    /** Modification d'une commande */
    #[Route('/commandes/{carteId}/modifier/{id}', name: 'app_commande_edit')]
    public function edit(
        int $carteId,
        int $id,
        CarteRepository $carteRepo,
        CommandeRepository $cmdRepo,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $carte = $carteRepo->find($carteId);
        $commande = $cmdRepo->find($id);
        if (!$carte || !$commande) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            return $this->handleSave($carte, $commande, $request, $cmdRepo, $em);
        }

        return $this->render('commande/form.html.twig', [
            'carte' => $carte,
            'cartes' => $carteRepo->findAllOrdered(),
            'commande' => $commande,
            'prochainNumero' => $commande->getNumero(),
        ]);
    }

    /** Traitement sauvegarde commande (create ou update) */
    private function handleSave(
        Carte $carte,
        ?Commande $commande,
        Request $request,
        CommandeRepository $cmdRepo,
        EntityManagerInterface $em
    ): Response {
        $isNew = $commande === null;

        if ($isNew) {
            $commande = new Commande();
            $commande->setCarte($carte);
            $commande->setNumero($cmdRepo->getProchainNumero($carte));
        }

        $commande->setNomClient($request->request->get('nom_client', ''));
        $commande->setTelephone($request->request->get('telephone', ''));
        $commande->setAcompte((float) $request->request->get('acompte', 0));
        $commande->setCommentaire($request->request->get('commentaire'));
        $commande->setCreneauRetrait($request->request->get('creneau_retrait'));

        $dateRetrait = $request->request->get('date_retrait');
        if ($dateRetrait) {
            $commande->setDateRetrait(new \DateTime($dateRetrait));
        }

        // Supprimer les anciennes lignes en modification
        if (!$isNew) {
            foreach ($commande->getLignes() as $l) {
                $em->remove($l);
            }
            $commande->clearLignes();
        }

        // Ajouter les nouvelles lignes depuis le JSON
        $lignesJson = $request->request->get('lignes_json', '[]');
        $lignesData = json_decode($lignesJson, true) ?: [];

        foreach ($lignesData as $ld) {
            $ligne = new LigneCommande();
            $ligne->setProduitCode($ld['produitCode'] ?? '');
            $ligne->setProduitNom($ld['produitNom'] ?? '');
            $ligne->setQuantite((float) ($ld['quantite'] ?? 1));
            $ligne->setPrixUnitaire((float) ($ld['prixUnitaire'] ?? 0));
            $ligne->setUnite($ld['unite'] ?? 'pièce');
            $ligne->setAPeser(!empty($ld['aPeser']));
            $ligne->setMontantPese((float) ($ld['montantPese'] ?? 0));
            $ligne->setCommentaire($ld['commentaire'] ?? null);
            $commande->addLigne($ligne);
        }

        $commande->recalculerTotal();

        if ($isNew) {
            $em->persist($commande);
        }
        $em->flush();

        $this->addFlash('success', $isNew
            ? sprintf('Commande %s créée.', $commande->getNumero())
            : sprintf('Commande %s modifiée.', $commande->getNumero())
        );

        return $this->redirectToRoute('app_commande_index', ['carteId' => $carte->getId()]);
    }

    /** Supprimer une commande */
    #[Route('/commandes/{carteId}/supprimer/{id}', name: 'app_commande_delete', methods: ['POST'])]
    public function delete(int $carteId, int $id, CommandeRepository $repo, EntityManagerInterface $em): Response
    {
        $commande = $repo->find($id);
        if ($commande) {
            $em->remove($commande);
            $em->flush();
            $this->addFlash('success', sprintf('Commande %s supprimée.', $commande->getNumero()));
        }
        return $this->redirectToRoute('app_commande_index', ['carteId' => $carteId]);
    }

    /** Impression fiche client */
    #[Route('/commandes/{carteId}/imprimer/{id}', name: 'app_commande_print')]
    public function print(int $carteId, int $id, CommandeRepository $repo): Response
    {
        $commande = $repo->find($id);
        if (!$commande) {
            throw $this->createNotFoundException();
        }

        return $this->render('commande/print.html.twig', [
            'commande' => $commande,
        ]);
    }

    /** Impression étiquettes AVERY 99x38mm */
    #[Route('/commandes/{carteId}/etiquettes', name: 'app_commande_etiquettes')]
    public function etiquettes(int $carteId, CarteRepository $carteRepo, CommandeRepository $cmdRepo): Response
    {
        $carte = $carteRepo->find($carteId);
        if (!$carte) {
            throw $this->createNotFoundException();
        }

        return $this->render('commande/etiquettes.html.twig', [
            'carte' => $carte,
            'commandes' => $cmdRepo->findByCarte($carte),
        ]);
    }

    /** Générer numéro de devis */
    #[Route('/commandes/{carteId}/devis/{id}', name: 'app_commande_devis', methods: ['POST'])]
    public function devis(int $carteId, int $id, CommandeRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $commande = $repo->find($id);
        if (!$commande) {
            return new JsonResponse(['error' => 'Commande non trouvée'], 404);
        }

        if (!$commande->getNumeroDevis()) {
            $seq = $repo->getProchainNumeroDevis();
            $commande->genererNumeroDevis($seq);
            $em->flush();
        }

        return new JsonResponse(['numeroDevis' => $commande->getNumeroDevis()]);
    }

    /** Toggle production (AJAX) */
    #[Route('/api/production/{id}', name: 'app_production_toggle', methods: ['POST'])]
    public function toggleProduction(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $ligne = $em->getRepository(LigneCommande::class)->find($id);
        if (!$ligne) {
            return new JsonResponse(['error' => 'Ligne non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $ligne->setProduitFait(!empty($data['produitFait']));
        $em->flush();

        return new JsonResponse(['ok' => true, 'produitFait' => $ligne->isProduitFait()]);
    }

    /** Mise à jour du poids d'un produit à peser (AJAX) */
    #[Route('/api/ligne/{id}/poids', name: 'app_ligne_poids', methods: ['POST'])]
    public function updatePoids(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $ligne = $em->getRepository(LigneCommande::class)->find($id);
        if (!$ligne) {
            return new JsonResponse(['error' => 'Ligne non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $poids = (float) ($data['poids'] ?? 0);

        if ($poids > 0) {
            $ligne->setQuantite($poids);
            $ligne->setMontantPese(round($poids * $ligne->getPrixUnitaire(), 2));
        } else {
            $ligne->setQuantite(0);
            $ligne->setMontantPese(0);
        }

        // Recalculer le total de la commande
        $commande = $ligne->getCommande();
        $commande->recalculerTotal();
        $em->flush();

        return new JsonResponse([
            'ok' => true,
            'poids' => $poids,
            'montant' => $ligne->getMontantPese(),
            'total' => $commande->getTotal(),
        ]);
    }
}
