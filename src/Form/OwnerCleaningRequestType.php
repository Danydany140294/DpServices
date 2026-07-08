<?php

namespace App\Form;

use App\Entity\CleaningRequest;
use App\Entity\CleaningService;
use App\Entity\Property;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Formulaire de demande côté propriétaire.
 * Contrairement à CleaningRequestType (admin), pas de champ
 * "assignedCleaner" — l'assignation d'une FdM reste une action admin.
 * Le choix du logement est restreint aux logements du propriétaire connecté.
 */
class OwnerCleaningRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('property', EntityType::class, [
                'class' => Property::class,
                'label' => 'Logement',
                'choice_label' => fn(Property $p) => $p->getName() . ' — ' . $p->getCity(),
                'choices' => $options['properties'],
                'constraints' => [new NotBlank()],
            ])
            ->add('service', EntityType::class, [
                'class' => CleaningService::class,
                'label' => 'Prestation',
                'choice_label' => fn(CleaningService $s) => $s->getName() . ' (' . $s->getDuration() . ' min)',
                'constraints' => [new NotBlank()],
            ])
            ->add('scheduledDate', DateType::class, [
                'label' => 'Date souhaitée',
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
            ])
            ->add('scheduledTime', TimeType::class, [
                'label' => 'Heure souhaitée',
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire (optionnel)',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CleaningRequest::class,
            'properties' => [],
        ]);
    }
}