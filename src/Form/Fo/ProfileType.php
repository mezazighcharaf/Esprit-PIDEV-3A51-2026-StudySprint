<?php

namespace App\Form\Fo;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nom complet',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Votre nom complet'],
                'required' => true,
            ])
            ->add('level', TextType::class, [
                'label' => 'Niveau',
                'required' => false,
                'mapped' => true,
                'property_path' => 'profile.level',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex: L2, M1, Terminale...'],
            ])
            ->add('specialty', TextType::class, [
                'label' => 'Spécialité',
                'required' => false,
                'mapped' => true,
                'property_path' => 'profile.specialty',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex: Informatique, Maths...'],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Bio',
                'required' => false,
                'mapped' => true,
                'property_path' => 'profile.bio',
                'attr' => ['class' => 'form-textarea', 'rows' => 4, 'placeholder' => 'Quelques mots sur vous...'],
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '3M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WEBP).',
                    ]),
                ],
                'attr' => ['class' => 'form-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
