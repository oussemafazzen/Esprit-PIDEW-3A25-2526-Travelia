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
                'attr' => [
                    'class' => 'form-control',
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
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('modalitesPaiement', ChoiceType::class, [
                'label' => 'Modalités paiement',
                'choices' => [
                    'Carte' => 'carte',
                    'Espèces' => 'especes',
                    'Virement' => 'virement',
                ],
                'placeholder' => 'Choisir un mode de paiement',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('clientId', IntegerType::class, [
                'label' => 'Client ID',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: 59',
                    'min' => 1,
                ],
            ])
            ->add('paysDestination', TextType::class, [
                'label' => 'Pays destination',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Paris',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}