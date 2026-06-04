<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin');
        }

        if ($this->isGranted('ROLE_OWNER')) {
            return $this->redirectToRoute('app_owner');
        }

        if ($this->isGranted('ROLE_CLEANER')) {
            return $this->redirectToRoute('app_cleaner');
        }

        return $this->render('dashboard/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}