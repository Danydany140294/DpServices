<?php

namespace App\Form;

use App\Entity\Lead;
use App\Entity\LeadCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class LeadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, [
                'label' => 'Nom de l\'entreprise',
                'constraints' => [new NotBlank()],
            ])
            ->add('contactName', TextType::class, [
                'label' => 'Nom du contact',
                'required' => false,
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'constraints' => [new NotBlank()],
            ])
            ->add('website', UrlType::class, [
                'label' => 'Site web',
                'required' => false,
                'default_protocol' => 'https',
            ])
            ->add('googleRating', NumberType::class, [
                'label' => 'Note Google',
                'required' => false,
                'scale' => 1,
            ])
            ->add('googleReviews', IntegerType::class, [
                'label' => 'Nombre d\'avis Google',
                'required' => false,
            ])
            ->add('hasAirbnb', CheckboxType::class, [
                'label' => 'Présence sur Airbnb',
                'required' => false,
            ])
            ->add('category', EntityType::class, [
                'class' => LeadCategory::class,
                'label' => 'Catégorie',
                'choice_label' => 'name',
                'choices' => $options['categories'],
                'required' => false,
                'placeholder' => 'Choisir une catégorie',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Nouveau' => 'NEW',
                    'Contacté' => 'CONTACTED',
                    'En discussion' => 'DISCUSSION',
                    'Devis envoyé' => 'QUOTE_SENT',
                    'Client' => 'CLIENT',
                    'Perdu' => 'LOST',
                    'À relancer' => 'TO_FOLLOW_UP',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('nextFollowUp', DateType::class, [
                'label' => 'Prochaine relance',
                'required' => false,
                'widget' => 'single_text',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lead::class,
            'categories' => [],
        ]);
    }
}