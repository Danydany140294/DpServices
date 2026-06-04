<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cleaner')]
#[IsGranted('ROLE_CLEANER')]
class CleanerController extends AbstractController
{
    #[Route('', name: 'app_cleaner')]
    public function index(): Response
    {
        return $this->render('cleaner/index.html.twig');
    }
}