<?php

namespace App\Form;

use App\Entity\LeadActivity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class LeadActivityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type d\'action',
                'choices' => [
                    '📞 Appel' => 'CALL',
                    '✉️ Email' => 'EMAIL',
                    '💬 SMS' => 'SMS',
                    '📄 Devis' => 'QUOTE',
                    '🤝 Réunion' => 'MEETING',
                ],
                'constraints' => [new NotBlank()],
            ])
            ->add('result', ChoiceType::class, [
                'label' => 'Résultat',
                'required' => false,
                'placeholder' => 'Choisir un résultat',
                'choices' => [
                    '✅ Positif' => 'POSITIVE',
                    '❌ Négatif' => 'NEGATIVE',
                    '📵 Sans réponse' => 'NO_ANSWER',
                    '🔄 À rappeler' => 'CALLBACK',
                ],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Note',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Détails de l\'action...'],
            ])
            ->add('followUpDate', DateType::class, [
                'label' => 'Date de relance',
                'required' => false,
                'widget' => 'single_text',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LeadActivity::class,
        ]);
    }
}