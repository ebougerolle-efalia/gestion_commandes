<?php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('/utilisateurs', name: 'app_user_index')]
    public function index(UserRepository $repo): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $repo->findBy([], ['username' => 'ASC']),
        ]);
    }

    #[Route('/utilisateurs/creer', name: 'app_user_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $username = trim($request->request->get('username', ''));
        $password = $request->request->get('password', '');
        $role = $request->request->get('role', 'user');

        if (strlen($username) < 3) {
            $this->addFlash('danger', 'Identifiant trop court (min 3 caractères).');
            return $this->redirectToRoute('app_user_index');
        }
        if (strlen($password) < 6) {
            $this->addFlash('danger', 'Mot de passe trop court (min 6 caractères).');
            return $this->redirectToRoute('app_user_index');
        }

        $user = new User();
        $user->setUsername($username);
        $user->setRole($role);
        $user->setPassword($hasher->hashPassword($user, $password));
        $em->persist($user);
        $em->flush();

        $this->addFlash('success', "Utilisateur « {$username} » créé.");
        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/utilisateurs/{id}/modifier', name: 'app_user_update', methods: ['POST'])]
    public function update(int $id, Request $request, UserRepository $repo, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = $repo->find($id);
        if (!$user) throw $this->createNotFoundException();

        $user->setRole($request->request->get('role', $user->getRole()));
        $user->setIsActive($request->request->has('is_active'));

        $newPass = $request->request->get('new_password', '');
        if ($newPass !== '') {
            if (strlen($newPass) < 6) {
                $this->addFlash('danger', 'Mot de passe trop court.');
                return $this->redirectToRoute('app_user_index');
            }
            $user->setPassword($hasher->hashPassword($user, $newPass));
        }

        $em->flush();
        $this->addFlash('success', "Utilisateur « {$user->getUsername()} » modifié.");
        return $this->redirectToRoute('app_user_index');
    }

    #[Route('/utilisateurs/{id}/supprimer', name: 'app_user_delete', methods: ['POST'])]
    public function delete(int $id, UserRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $repo->find($id);
        if ($user) {
            if ($user->getId() === $this->getUser()->getId()) {
                $this->addFlash('danger', 'Impossible de supprimer son propre compte.');
                return $this->redirectToRoute('app_user_index');
            }
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }
        return $this->redirectToRoute('app_user_index');
    }
}
