<?php

namespace App\Form;

use App\Entity\Property;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class PropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du logement',
                'constraints' => [new NotBlank()],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'constraints' => [new NotBlank()],
            ])
            ->add('color', ChoiceType::class, [
                'label' => 'Couleur',
                'expanded' => true,
                'multiple' => false,
                'constraints' => [new NotBlank()],
                // Palette figée sur les 11 couleurs officielles d'événement
                // Google Calendar (colorId 1 à 11), pour garantir une
                // correspondance exacte avec l'event poussé dans Google
                // Agenda (voir GoogleCalendarService::resolveGoogleColorId).
                'choices' => [
                    'Lavande' => '#7986cb',
                    'Sauge' => '#33b679',
                    'Raisin' => '#8e24aa',
                    'Flamant' => '#e67c73',
                    'Banane' => '#f6bf26',
                    'Mandarine' => '#f4511e',
                    'Paon' => '#039be5',
                    'Graphite' => '#616161',
                    'Myrtille' => '#3f51b5',
                    'Basilic' => '#0b8043',
                    'Tomate' => '#d50000',
                ],
                'choice_attr' => function (?string $choiceValue) {
                    return $choiceValue !== null ? ['data-color' => $choiceValue] : [];
                },
            ])
            ->add('sector', ChoiceType::class, [
                'label' => 'Secteur',
                'required' => false,
                'placeholder' => 'Choisir un secteur',
                'choices' => [
                    'Montpellier' => 'montpellier',
                    'Nîmes' => 'nimes',
                ],
            ])
            ->add('owner', EntityType::class, [
                'class' => User::class,
                'label' => 'Propriétaire',
                'choice_label' => fn(User $user) => $user->getFirstname() . ' ' . $user->getLastname(),
                'choices' => $options['owners'],
            ])
            // Champ NON mappé à l'entité : Property::$photo est une chaîne
            // (nom du fichier stocké), pas un objet fichier. On récupère
            // l'UploadedFile dans le contrôleur via $form->get('photoFile')->getData(),
            // puis on fait nous-mêmes $property->setPhoto($nomGenere).
            ->add('photoFile', FileType::class, [
                'label' => 'Photo du logement',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '4M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Merci de déposer une image valide (JPEG, PNG ou WebP).',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
            'owners' => [],
        ]);
    }
}