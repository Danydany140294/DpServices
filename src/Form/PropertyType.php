<?php

namespace App\Form;

use App\Entity\Property;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
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
            ->add('color', ColorType::class, [
                'label' => 'Couleur',
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