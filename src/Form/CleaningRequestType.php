<?php

namespace App\Form;

use App\Entity\CleaningRequest;
use App\Entity\CleaningService;
use App\Entity\Property;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CleaningRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('property', EntityType::class, [
                'class' => Property::class,
                'label' => 'Logement',
                'choice_label' => fn(Property $p) => $p->getName() . ' — ' . $p->getCity(),
                'constraints' => [new NotBlank()],
            ])
            ->add('service', EntityType::class, [
                'class' => CleaningService::class,
                'label' => 'Prestation',
                'choice_label' => fn(CleaningService $s) => $s->getName() . ' (' . $s->getDuration() . ' min)',
                'constraints' => [new NotBlank()],
            ])
            ->add('scheduledDate', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
            ])
            ->add('scheduledTime', TimeType::class, [
                'label' => 'Heure',
                'widget' => 'single_text',
                'constraints' => [new NotBlank()],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
            ])
            ->add('assignedCleaner', EntityType::class, [
                'class' => User::class,
                'label' => 'Femme de ménage',
                'choice_label' => fn(User $u) => $u->getFirstname() . ' ' . $u->getLastname(),
                'choices' => $options['cleaners'],
                'required' => false,
                'placeholder' => 'Non assignée',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CleaningRequest::class,
            'cleaners' => [],
        ]);
    }
}