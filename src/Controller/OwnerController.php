<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/owner')]
#[IsGranted('ROLE_OWNER')]
class OwnerController extends AbstractController
{
    #[Route('', name: 'app_owner')]
    public function index(): Response
    {
        return $this->render('owner/index.html.twig');
    }
}