<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(private ActivityLogService $logger) {}

    #[Route('', name: 'app_users')]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'owners' => $userRepository->findByRole('ROLE_OWNER'),
            'cleaners' => $userRepository->findByRole('ROLE_CLEANER'),
        ]);
    }

    #[Route('/new', name: 'app_user_new')]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $em->persist($user);
            $em->flush();
            $this->logger->log('Utilisateur créé', $user->getFirstname() . ' ' . $user->getLastname() . ' (' . $user->getEmail() . ')');
            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('app_users');
        }

        return $this->render('user/new.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit')]
    public function edit(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('plainPassword')->getData()) {
                $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            }
            $em->flush();
            $this->logger->log('Utilisateur modifié', $user->getFirstname() . ' ' . $user->getLastname());
            $this->addFlash('success', 'Utilisateur modifié avec succès.');
            return $this->redirectToRoute('app_users');
        }

        return $this->render('user/edit.html.twig', ['form' => $form->createView(), 'user' => $user]);
    }

    #[Route('/{id}/toggle', name: 'app_user_toggle')]
    public function toggle(User $user, EntityManagerInterface $em): Response
    {
        $roles = $user->getRoles();
        if (in_array('ROLE_DISABLED', $roles)) {
            $user->setRoles(array_values(array_filter($roles, fn($r) => $r !== 'ROLE_DISABLED' && $r !== 'ROLE_USER')));
            $this->logger->log('Utilisateur activé', $user->getFirstname() . ' ' . $user->getLastname());
        } else {
            $user->setRoles([...$user->getRoles(), 'ROLE_DISABLED']);
            $this->logger->log('Utilisateur désactivé', $user->getFirstname() . ' ' . $user->getLastname());
        }
        $em->flush();
        $this->addFlash('success', 'Statut modifié.');
        return $this->redirectToRoute('app_users');
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $name = $user->getFirstname() . ' ' . $user->getLastname();
            $em->remove($user);
            $em->flush();
            $this->logger->log('Utilisateur supprimé', $name);
            $this->addFlash('success', 'Utilisateur supprimé.');
        }
        return $this->redirectToRoute('app_users');
    }
}