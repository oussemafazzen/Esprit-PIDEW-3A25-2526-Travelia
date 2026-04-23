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
                    'Bateau' => 'bateau',
                ],
                'placeholder' => 'Choisir un transport',
                'required' => true,
            ])
            ->add('numeroBillet', TextType::class, [
                'label' => 'Numéro billet',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: NO|BJ 520',
                ],
            ])
            ->add('dateDepart', DateType::class, [
                'label' => 'Date départ',
                'widget' => 'single_text',
                'html5' => false,
                'required' => true,
            ])
            ->add('dateArrivee', DateType::class, [
                'label' => 'Date arrivée',
                'widget' => 'single_text',
                'html5' => false,
                'required' => true,
            ])
            ->add('prix', NumberType::class, [
                'label' => 'Prix',
                'scale' => 2,
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: 838.20',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Confirmé' => 'confirme',
                    'Retardé' => 'retarde',
                    'En attente' => 'en_attente',
                    'Annulé' => 'annule',
                ],
                'placeholder' => 'Choisir un statut',
                'required' => true,
            ])
            ->add('reservation', EntityType::class, [
                'class' => Reservation::class,
                'choice_label' => 'id',
                'label' => 'Réservation liée',
                'placeholder' => 'Choisir une réservation',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Billet::class,
        ]);
    }
}
