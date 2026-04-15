<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'attr' => ['placeholder' => 'Nom'],
                'label' => false,
            ])
            ->add('prenom', TextType::class, [
                'attr' => ['placeholder' => 'Prénom'],
                'label' => false,
            ])
            ->add('email', EmailType::class, [
                'attr' => ['placeholder' => 'Email'],
                'label' => false,
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options'  => [
                    'label' => false, 
                    'attr' => ['placeholder' => 'Mot de passe'],
                ],
                'second_options' => ['label' => false, 'attr' => ['placeholder' => 'Répéter le mot de passe']],
            ])
            ->add('phone_prefix', ChoiceType::class, [
                'choices' => [
                    'Tunisie (+216)' => '+216',
                    'France (+33)' => '+33',
                    'Maroc (+212)' => '+212',
                    'Algérie (+213)' => '+213',
                    'USA (+1)' => '+1',
                    'UK (+44)' => '+44',
                    'Allemagne (+49)' => '+49',
                    'Italie (+39)' => '+39',
                    'Espagne (+34)' => '+34',
                    'Canada (+1)' => '+1',
                ],
                'mapped' => false,
                'label' => false,
                'attr' => ['class' => 'prefix-dropdown']
            ])
            ->add('telephone', TelType::class, [
                'attr' => ['placeholder' => 'Téléphone'],
                'label' => false,
            ])
            ->add('nationalite', CountryType::class, [
                'placeholder' => 'Sélectionnez votre nationalité',
                'label' => false,
            ])
            ->add('date_naissance', DateType::class, [
                'widget' => 'single_text',
                'label' => false,
                'attr' => ['placeholder' => 'Date de Naissance'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
