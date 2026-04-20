<?php
namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig');
    }

    #[Route('/profil/mot-de-passe', name: 'app_profile_password', methods: ['POST'])]
    public function changePassword(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = $this->getUser();
        $current = $request->request->get('current_password', '');
        $new = $request->request->get('new_password', '');
        $confirm = $request->request->get('confirm_password', '');

        // Vérifier le mot de passe actuel
        if (!$hasher->isPasswordValid($user, $current)) {
            $this->addFlash('danger', 'Mot de passe actuel incorrect.');
            return $this->redirectToRoute('app_profile');
        }

        if (strlen($new) < 6) {
            $this->addFlash('danger', 'Le nouveau mot de passe doit faire au moins 6 caractères.');
            return $this->redirectToRoute('app_profile');
        }

        if ($new !== $confirm) {
            $this->addFlash('danger', 'Les deux mots de passe ne correspondent pas.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setPassword($hasher->hashPassword($user, $new));
        $em->flush();

        $this->addFlash('success', 'Mot de passe modifié avec succès.');
        return $this->redirectToRoute('app_profile');
    }
}
