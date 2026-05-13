<?php

namespace App\Form;

use App\Entity\Activite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'activité',
                'attr'  => ['placeholder' => 'Ex: Randonnée en montagne'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['placeholder' => 'Description détaillée...', 'rows' => 3],
            ])
            ->add('lieu', TextType::class, [
                'label'    => 'Lieu',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: Tunis, Djerba...'],
            ])
            ->add('duree', IntegerType::class, [
                'label'    => 'Durée (heures)',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: 3'],
            ])
            ->add('prix', NumberType::class, [
                'label'    => 'Prix (DT)',
                'required' => false,
                'scale'    => 2,
                'attr'     => ['placeholder' => 'Ex: 45.00'],
            ])
            ->add('capaciteMax', IntegerType::class, [
                'label'    => 'Capacité max',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: 20'],
            ])
            ->add('categorie', TextType::class, [
                'label'    => 'Catégorie',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: Sport, Culture, Aventure...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Activite::class]);
    }
}
