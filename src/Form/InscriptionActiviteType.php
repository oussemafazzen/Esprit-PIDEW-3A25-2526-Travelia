<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\InscriptionActivite;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InscriptionActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('activite', EntityType::class, [
                'class'        => Activite::class,
                'choice_label' => 'nom',
                'label'        => 'Activité',
                'placeholder'  => '— Choisir une activité —',
            ])
            ->add('dateActivite', DateType::class, [
                'label'    => 'Date de l\'activité',
                'widget'   => 'single_text',
                'required' => false,
            ])
            ->add('nombreParticipants', IntegerType::class, [
                'label'    => 'Nombre de participants',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: 5', 'min' => 1],
            ])
            ->add('idClient', IntegerType::class, [
                'label'    => 'ID Client',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: 12'],
            ])
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => [
                    'En attente'  => 'En attente',
                    'Confirmée'   => 'Confirmée',
                    'Annulée'     => 'Annulée',
                ],
                'required' => false,
                'placeholder' => '— Choisir un statut —',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InscriptionActivite::class]);
    }
}
