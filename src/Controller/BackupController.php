<?php

namespace App\Controller;

use App\Repository\CarteRepository;
use App\Service\BackupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/backup')]
class BackupController extends AbstractController
{
    #[Route('/', name: 'app_backup_index')]
    public function index(CarteRepository $carteRepo): Response
    {
        return $this->render('backup/index.html.twig', [
            'cartes' => $carteRepo->findAllOrdered(),
        ]);
    }

    /** Export JSON complet */
    #[Route('/export', name: 'app_backup_export')]
    public function export(BackupService $service): Response
    {
        $data = $service->export();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $dateStr = date('Y-m-d');

        return new Response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => sprintf('attachment; filename="bougerolle_backup_%s.json"', $dateStr),
        ]);
    }

    /** Import JSON */
    #[Route('/import', name: 'app_backup_import', methods: ['POST'])]
    public function import(Request $request, BackupService $service): Response
    {
        $file = $request->files->get('backup_file');
        if (!$file) {
            $this->addFlash('danger', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('app_backup_index');
        }

        try {
            $content = file_get_contents($file->getPathname());
            $json = json_decode($content, true);

            if (!$json) {
                throw new \InvalidArgumentException('Fichier JSON invalide');
            }

            // Support v1 (data à la racine) et v2 (data dans "data")
            $data = $json['data'] ?? $json;

            $stats = $service->import($data);

            $this->addFlash('success', sprintf(
                'Restauration réussie : %d catégories, %d produits, %d commandes.',
                $stats['categories'], $stats['produits'], $stats['commandes']
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_backup_index');
    }
}
