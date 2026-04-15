<?php

namespace App\Controller;

use App\Entity\Carte;
use App\Entity\Produit;
use App\Repository\CarteRepository;
use App\Repository\CategorieRepository;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProduitController extends AbstractController
{
    #[Route('/produits/{carteId}', name: 'app_produit_index')]
    public function index(
        int $carteId,
        CarteRepository $carteRepo,
        ProduitRepository $produitRepo,
        CategorieRepository $catRepo
    ): Response {
        $carte = $carteRepo->find($carteId);
        if (!$carte) {
            throw $this->createNotFoundException();
        }

        return $this->render('produit/index.html.twig', [
            'carte' => $carte,
            'cartes' => $carteRepo->findAllOrdered(),
            'categories' => $catRepo->findByCarte($carte),
            'produits' => $produitRepo->findByCarte($carte),
        ]);
    }

    #[Route('/produits/{carteId}/ajouter', name: 'app_produit_add', methods: ['POST'])]
    public function add(int $carteId, Request $request, CarteRepository $carteRepo, CategorieRepository $catRepo, EntityManagerInterface $em): Response
    {
        $carte = $carteRepo->find($carteId);
        if (!$carte) {
            throw $this->createNotFoundException();
        }

        $produit = new Produit();
        $produit->setNom($request->request->get('nom', ''));
        $produit->setPrix((float) $request->request->get('prix', 0));
        $produit->setUnite($request->request->get('unite', 'pièce'));
        $produit->setAPeser($request->request->getBoolean('a_peser'));
        $produit->setCarte($carte);
        $produit->generateCode();

        $catId = $request->request->get('categorie_id');
        if ($catId) {
            $cat = $catRepo->find($catId);
            if ($cat) {
                $produit->setCategorie($cat);
            }
        }

        $em->persist($produit);
        $em->flush();

        $this->addFlash('success', sprintf('Produit "%s" ajouté.', $produit->getNom()));

        return $this->redirectToRoute('app_produit_index', ['carteId' => $carteId]);
    }

    #[Route('/produits/{carteId}/modifier/{id}', name: 'app_produit_edit', methods: ['POST'])]
    public function edit(int $carteId, int $id, Request $request, ProduitRepository $repo, CategorieRepository $catRepo, EntityManagerInterface $em): Response
    {
        $produit = $repo->find($id);
        if (!$produit) {
            throw $this->createNotFoundException();
        }

        $produit->setNom($request->request->get('nom', $produit->getNom()));
        $produit->setPrix((float) $request->request->get('prix', $produit->getPrix()));
        $produit->setUnite($request->request->get('unite', $produit->getUnite()));
        $produit->setAPeser($request->request->getBoolean('a_peser'));
        $produit->setActif($request->request->getBoolean('actif', true));

        $catId = $request->request->get('categorie_id');
        $produit->setCategorie($catId ? $catRepo->find($catId) : null);

        $em->flush();

        $this->addFlash('success', sprintf('Produit "%s" modifié.', $produit->getNom()));

        return $this->redirectToRoute('app_produit_index', ['carteId' => $carteId]);
    }

    #[Route('/produits/{carteId}/supprimer/{id}', name: 'app_produit_delete', methods: ['POST'])]
    public function delete(int $carteId, int $id, ProduitRepository $repo, EntityManagerInterface $em): Response
    {
        $produit = $repo->find($id);
        if ($produit) {
            $em->remove($produit);
            $em->flush();
            $this->addFlash('success', sprintf('Produit "%s" supprimé.', $produit->getNom()));
        }
        return $this->redirectToRoute('app_produit_index', ['carteId' => $carteId]);
    }

    /** API recherche produits (autocomplétion) */
    #[Route('/api/produits/recherche', name: 'app_api_produit_search', methods: ['GET'])]
    public function search(Request $request, CarteRepository $carteRepo, ProduitRepository $produitRepo): JsonResponse
    {
        $q = $request->query->get('q', '');
        $carteId = $request->query->getInt('carteId');

        if (strlen($q) < 1 || !$carteId) {
            return new JsonResponse([]);
        }

        $carte = $carteRepo->find($carteId);
        if (!$carte) {
            return new JsonResponse([]);
        }

        $results = $produitRepo->search($carte, $q);

        return new JsonResponse($results);
    }
}
