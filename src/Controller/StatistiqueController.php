<?php

namespace App\Controller;

use App\Repository\CarteRepository;
use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatistiqueController extends AbstractController
{
    #[Route('/statistiques/{carteId}', name: 'app_statistique_index')]
    public function index(int $carteId, CarteRepository $carteRepo, CommandeRepository $cmdRepo): Response
    {
        $carte = $carteRepo->find($carteId);
        if (!$carte) {
            throw $this->createNotFoundException();
        }

        return $this->render('statistique/index.html.twig', [
            'carte' => $carte,
            'cartes' => $carteRepo->findAllOrdered(),
            'stats' => $cmdRepo->getStats($carte),
            'statsParDate' => $cmdRepo->getStatsParDate($carte),
        ]);
    }
}
