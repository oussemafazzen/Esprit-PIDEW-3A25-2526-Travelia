<?php

namespace App\Form;

use App\Entity\Billet;
use App\Entity\Reservation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BilletType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeTransport', ChoiceType::class, [
                'label' => 'Type de transport',
                'choices' => [
                    'Avion' => 'avion',
                    'Train' => 'train',
                    'Bus' => 'bus',
                ],
                'placeholder' => 'Choisir un transport',
            ])
            ->add('numeroBillet', TextType::class, [
                'label' => 'Numéro billet',
                'attr' => [
                    'placeholder' => 'Ex: NO|BJ 520',
                ],
            ])
            ->add('dateDepart', DateType::class, [
                'label' => 'Date départ',
                'widget' => 'single_text',
            ])
            ->add('dateArrivee', DateType::class, [
                'label' => 'Date arrivée',
                'widget' => 'single_text',
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix',
                'scale' => 2,
                'attr' => [
                    'placeholder' => 'Ex: 838.20',
                    'min' => 0,
                    'step' => '0.01',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Confirmé' => 'confirme',
                    'En attente' => 'en_attente',
                    'Annulé' => 'annule',
                ],
                'placeholder' => 'Choisir un statut',
            ])
            ->add('reservation', EntityType::class, [
                'class' => Reservation::class,
                'choice_label' => 'id',
                'label' => 'Réservation liée',
                'placeholder' => 'Choisir une réservation',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Billet::class,
        ]);
    }
}