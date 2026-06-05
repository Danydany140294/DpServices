<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('', name: 'app_users')]
    public function index(UserRepository $userRepository): Response
    {
        $owners = $userRepository->findByRole('ROLE_OWNER');
        $cleaners = $userRepository->findByRole('ROLE_CLEANER');

        return $this->render('user/index.html.twig', [
            'owners' => $owners,
            'cleaners' => $cleaners,
        ]);
    }
}