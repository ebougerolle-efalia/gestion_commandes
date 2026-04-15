<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Repository\CarteRepository;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/categories')]
class CategorieController extends AbstractController
{
    #[Route('/{carteId}', name: 'app_categorie_index')]
    public function index(int $carteId, CarteRepository $carteRepo, CategorieRepository $catRepo): Response
    {
        $carte = $carteRepo->find($carteId);
        if (!$carte) {
            throw $this->createNotFoundException();
        }

        return $this->render('categorie/index.html.twig', [
            'carte' => $carte,
            'cartes' => $carteRepo->findAllOrdered(),
            'categories' => $catRepo->findByCarte($carte),
        ]);
    }

    #[Route('/{carteId}/ajouter', name: 'app_categorie_add', methods: ['POST'])]
    public function add(int $carteId, Request $request, CarteRepository $carteRepo, EntityManagerInterface $em): Response
    {
        $carte = $carteRepo->find($carteId);
        if (!$carte) {
            throw $this->createNotFoundException();
        }

        $cat = new Categorie();
        $cat->setNom($request->request->get('nom', ''));
        $cat->setOrdre((int) $request->request->get('ordre', 0));
        $cat->setCarte($carte);

        $em->persist($cat);
        $em->flush();

        $this->addFlash('success', sprintf('Catégorie "%s" ajoutée.', $cat->getNom()));

        return $this->redirectToRoute('app_categorie_index', ['carteId' => $carteId]);
    }

    #[Route('/{carteId}/modifier/{id}', name: 'app_categorie_edit', methods: ['POST'])]
    public function edit(int $carteId, int $id, Request $request, CategorieRepository $repo, EntityManagerInterface $em): Response
    {
        $cat = $repo->find($id);
        if (!$cat) {
            throw $this->createNotFoundException();
        }

        $cat->setNom($request->request->get('nom', $cat->getNom()));
        $cat->setOrdre((int) $request->request->get('ordre', $cat->getOrdre()));
        $em->flush();

        $this->addFlash('success', sprintf('Catégorie "%s" modifiée.', $cat->getNom()));

        return $this->redirectToRoute('app_categorie_index', ['carteId' => $carteId]);
    }

    #[Route('/{carteId}/supprimer/{id}', name: 'app_categorie_delete', methods: ['POST'])]
    public function delete(int $carteId, int $id, CategorieRepository $repo, EntityManagerInterface $em): Response
    {
        $cat = $repo->find($id);
        if ($cat) {
            // Détacher les produits
            foreach ($cat->getProduits() as $p) {
                $p->setCategorie(null);
            }
            $em->remove($cat);
            $em->flush();
            $this->addFlash('success', sprintf('Catégorie "%s" supprimée.', $cat->getNom()));
        }

        return $this->redirectToRoute('app_categorie_index', ['carteId' => $carteId]);
    }

    /** Réordonner (AJAX drag & drop) */
    #[Route('/{carteId}/reorder', name: 'app_categorie_reorder', methods: ['POST'])]
    public function reorder(Request $request, CategorieRepository $repo, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        foreach ($data['ordres'] ?? [] as $item) {
            $cat = $repo->find($item['id']);
            if ($cat) {
                $cat->setOrdre((int) $item['ordre']);
            }
        }

        $em->flush();

        return new JsonResponse(['ok' => true]);
    }
}
