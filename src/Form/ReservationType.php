<?php

namespace App\Form;

use App\Entity\Reservation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
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
                'required' => false,
                'attr' => [
                    'class' => 'form-control js-travelia-datepicker',
                    'autocomplete' => 'off',
                    'placeholder' => 'Choisir une date',
                ],
            ])
            ->add('paysDestination', TextType::class, [
                'label' => 'Destination (optionnel)',
                'required' => false,
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