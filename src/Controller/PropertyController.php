<?php

namespace App\Controller;

use App\Entity\Property;
use App\Form\PropertyType;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

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
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepository, SluggerInterface $slugger): Response
    {
        $property = new Property();
        $owners = $userRepository->findByRole('ROLE_OWNER');
        $form = $this->createForm(PropertyType::class, $property, ['owners' => $owners]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePhotoUpload($form, $property, $slugger);

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
    public function edit(Property $property, Request $request, EntityManagerInterface $em, UserRepository $userRepository, SluggerInterface $slugger): Response
    {
        $owners = $userRepository->findByRole('ROLE_OWNER');
        $form = $this->createForm(PropertyType::class, $property, ['owners' => $owners]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handlePhotoUpload($form, $property, $slugger);

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

    /**
     * Traite l'upload de la photo (champ non mappé "photoFile") :
     * - génère un nom de fichier unique (slug + hash + extension)
     * - déplace le fichier vers public/uploads/properties/
     * - supprime l'ancienne photo si on est en édition et qu'une nouvelle
     *   photo remplace la précédente (évite d'accumuler des fichiers orphelins)
     * - met à jour Property::$photo avec le nom de fichier généré
     *
     * Non bloquant côté formulaire : si aucun fichier n'est envoyé
     * (photoFile vide), on ne touche pas à la photo existante.
     */
    private function handlePhotoUpload($form, Property $property, SluggerInterface $slugger): void
    {
        /** @var UploadedFile|null $photoFile */
        $photoFile = $form->get('photoFile')->getData();

        if (!$photoFile) {
            return;
        }

        $originalFilename = pathinfo($photoFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $photoFile->guessExtension();

        try {
            $photoFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/properties',
                $newFilename
            );
        } catch (FileException $e) {
            $this->addFlash('error', 'Erreur lors de l\'upload de la photo : ' . $e->getMessage());
            return;
        }

        // Supprime l'ancienne photo si elle existe (remplacement en édition)
        $oldPhoto = $property->getPhoto();
        if ($oldPhoto) {
            $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/properties/' . $oldPhoto;
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $property->setPhoto($newFilename);
    }
}