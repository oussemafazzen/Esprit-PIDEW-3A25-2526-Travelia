<?php

namespace App\Form;

use App\Entity\Paiement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaiementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('datePaiement')
            ->add('montant')
            ->add('methodePaiement', ChoiceType::class, [
                'choices' => [
                    'Visa' => 'Visa',
                    'MasterCard' => 'MasterCard',
                    'American express' => 'American express',
                    'PayPal' => 'PayPal',
                ],
                'placeholder' => 'Sélectionnez une méthode...',
            ])
            ->add('idReservation')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Paiement::class,
        ]);
    }
}
