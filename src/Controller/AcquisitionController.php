<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/acquisition')]
#[IsGranted('ROLE_ADMIN')]
class AcquisitionController extends AbstractController
{
    #[Route('', name: 'app_acquisition')]
    public function index(): Response
    {
        return $this->render('acquisition/index.html.twig');
    }
}