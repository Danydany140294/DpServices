<?php

namespace App\Controller;

use App\Entity\Property;
use App\Form\PropertyType;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/properties')]
#[IsGranted('ROLE_ADMIN')]
class PropertyController extends AbstractController
{
    #[Route('', name: 'app_properties')]
    public function index(PropertyRepository $propertyRepository): Response
    {
        return $this->render('property/index.html.twig', [
            'properties' => $propertyRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_property_new')]
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $property = new Property();
        $owners = $userRepository->findByRole('ROLE_OWNER');
        $form = $this->createForm(PropertyType::class, $property, ['owners' => $owners]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($property);
            $em->flush();
            $this->addFlash('success', 'Logement créé avec succès.');
            return $this->redirectToRoute('app_properties');
        }

        return $this->render('property/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_property_edit')]
    public function edit(Property $property, Request $request, EntityManagerInterface $em, UserRepository $userRepository): Response
    {
        $owners = $userRepository->findByRole('ROLE_OWNER');
        $form = $this->createForm(PropertyType::class, $property, ['owners' => $owners]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Logement modifié avec succès.');
            return $this->redirectToRoute('app_properties');
        }

        return $this->render('property/edit.html.twig', [
            'form' => $form->createView(),
            'property' => $property,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_property_delete', methods: ['POST'])]
    public function delete(Property $property, EntityManagerInterface $em): Response
    {
        $em->remove($property);
        $em->flush();
        $this->addFlash('success', 'Logement supprimé.');
        return $this->redirectToRoute('app_properties');
    }
}