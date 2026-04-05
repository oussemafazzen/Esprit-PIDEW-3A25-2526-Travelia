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
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre nom.']),
                    new Regex([
                        'pattern' => '/^[\p{L} \-\']++$/u',
                        'message' => 'Le nom ne peut contenir que des lettres.',
                    ]),
                ],
            ])
            ->add('prenom', TextType::class, [
                'attr' => ['placeholder' => 'Prénom'],
                'label' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre prénom.']),
                    new Regex([
                        'pattern' => '/^[\p{L} \-\']++$/u',
                        'message' => 'Le prénom ne peut contenir que des lettres.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'attr' => ['placeholder' => 'Email'],
                'label' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre email.']),
                    new Email(['message' => 'Veuillez saisir un format d\'email valide (ex: abc@abc.abc).']),
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les mots de passe doivent correspondre.',
                'options' => ['attr' => ['class' => 'password-field']],
                'required' => true,
                'first_options'  => [
                    'label' => false, 
                    'attr' => ['placeholder' => 'Mot de passe'],
                    'constraints' => [
                        new NotBlank(['message' => 'Veuillez saisir un mot de passe.']),
                        new Length([
                            'min' => 8,
                            'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        ]),
                        new Regex([
                            'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                            'message' => 'Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.',
                        ]),
                    ],
                ],
                'second_options' => ['label' => false, 'attr' => ['placeholder' => 'Répéter le mot de passe']],
            ])
            ->add('telephone', TelType::class, [
                'attr' => ['placeholder' => 'Téléphone'],
                'label' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre téléphone.']),
                ],
            ])
            ->add('nationalite', CountryType::class, [
                'placeholder' => 'Sélectionnez votre nationalité',
                'label' => false,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner votre nationalité.']),
                ],
            ])
            ->add('date_naissance', DateType::class, [
                'widget' => 'single_text',
                'label' => false,
                'attr' => ['placeholder' => 'Date de Naissance'],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir votre date de naissance.']),
                ],
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
