<?php

namespace App\Controller;

use App\Entity\Carte;
use App\Entity\Categorie;
use App\Repository\CarteRepository;
use App\Service\CarteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/carte')]
class CarteController extends AbstractController
{
    #[Route('/', name: 'app_carte_index')]
    public function index(CarteRepository $repo): Response
    {
        return $this->render('carte/index.html.twig', [
            'cartes' => $repo->findAllOrdered(),
        ]);
    }

    #[Route('/creer', name: 'app_carte_creer', methods: ['POST'])]
    public function creer(Request $request, EntityManagerInterface $em): Response
    {
        $nom = trim($request->request->get('nom', ''));
        if ($nom === '') {
            $this->addFlash('danger', 'Nom requis.');
            return $this->redirectToRoute('app_carte_index');
        }

        $carte = new Carte();
        $carte->setNom($nom);

        // Créer une catégorie par défaut
        $cat = new Categorie();
        $cat->setNom('Divers');
        $cat->setOrdre(0);
        $cat->setCarte($carte);

        $em->persist($carte);
        $em->persist($cat);
        $em->flush();

        $this->addFlash('success', sprintf('Carte "%s" créée.', $nom));

        return $this->redirectToRoute('app_commande_index', ['carteId' => $carte->getId()]);
    }

    #[Route('/{id}/renommer', name: 'app_carte_renommer', methods: ['POST'])]
    public function renommer(Carte $carte, Request $request, EntityManagerInterface $em): Response
    {
        $nom = trim($request->request->get('nom', ''));
        if ($nom === '') {
            $this->addFlash('danger', 'Nom requis.');
            return $this->redirectToRoute('app_carte_index');
        }

        $carte->setNom($nom);

        // Renuméroter les commandes
        $prefix = $carte->getPrefix();
        $i = 1;
        foreach ($carte->getCommandes() as $cmd) {
            $cmd->setNumero(sprintf('%s-%03d', $prefix, $i));
            $i++;
        }

        $em->flush();
        $this->addFlash('success', sprintf('Carte renommée en "%s".', $nom));

        return $this->redirectToRoute('app_carte_index');
    }

    #[Route('/{id}/dupliquer', name: 'app_carte_dupliquer', methods: ['POST'])]
    public function dupliquer(Carte $carte, Request $request, CarteService $service): Response
    {
        $nom = trim($request->request->get('nom', ''));
        if ($nom === '') {
            $this->addFlash('danger', 'Nom requis.');
            return $this->redirectToRoute('app_carte_index');
        }

        try {
            $nouvelle = $service->dupliquer($carte, $nom);
            $this->addFlash('success', sprintf(
                'Carte "%s" dupliquée vers "%s" (%d catégories, %d produits).',
                $carte->getNom(), $nom,
                $nouvelle->getCategories()->count(),
                $nouvelle->getProduits()->count()
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_carte_index');
    }

    #[Route('/{id}/supprimer', name: 'app_carte_supprimer', methods: ['POST'])]
    public function supprimer(Carte $carte, EntityManagerInterface $em, CarteRepository $repo): Response
    {
        if (count($repo->findAll()) <= 1) {
            $this->addFlash('danger', 'Impossible de supprimer la dernière carte.');
            return $this->redirectToRoute('app_carte_index');
        }

        $nom = $carte->getNom();
        $em->remove($carte);
        $em->flush();

        $this->addFlash('success', sprintf('Carte "%s" supprimée.', $nom));

        return $this->redirectToRoute('app_carte_index');
    }
}
