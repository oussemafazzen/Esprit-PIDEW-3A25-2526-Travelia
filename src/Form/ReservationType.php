<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateReservation', DateType::class, [
                'label' => 'Date réservation',
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
                'attr' => [
                    'class' => 'form-control js-travelia-datepicker',
                    'autocomplete' => 'off',
                    'placeholder' => 'Choisir une date',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Confirmé' => 'confirmé',
                    'En attente' => 'en_attente',
                    'Annulé' => 'annulé',
                ],
                'placeholder' => 'Choisir un statut',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('modalitesPaiement', ChoiceType::class, [
                'label' => 'Modalités de paiement',
                'choices' => [
                    'Carte' => 'carte',
                    'Virement' => 'virement',
                    'Espèces' => 'especes',
                ],
                'placeholder' => 'Choisir un mode de paiement',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('clientId', IntegerType::class, [
                'label' => 'ID client',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Ex: 17',
                ],
            ])
            ->add('paysDestination', TextType::class, [
                'label' => 'Destination',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'autocomplete' => 'off',
                    'placeholder' => 'Ex: Canada',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
