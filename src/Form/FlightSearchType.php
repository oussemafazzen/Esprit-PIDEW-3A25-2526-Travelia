<?php

namespace App\Form;

use App\Entity\FlightSearchData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlightSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('destination', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'flight-search-input',
                    'placeholder' => 'Choisissez ou tapez un pays',
                    'autocomplete' => 'off',
                    'list' => 'countries-list',
                ],
            ])
            ->add('dateDepart', DateType::class, [
                'label' => false,
                'widget' => 'single_text',
                'html5' => true,
                'input' => 'datetime_immutable',
                'required' => false,
                'attr' => [
                    'class' => 'flight-search-input flight-search-date',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('passagers', IntegerType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'class' => 'flight-search-input',
                    'placeholder' => '1',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FlightSearchData::class,
            'method' => 'GET',
            'csrf_protection' => true,
        ]);
    }
}