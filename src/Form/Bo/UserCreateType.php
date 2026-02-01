<?php

namespace App\Form\Bo;

use App\Dto\BoUserCreateDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Prénom'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label required'],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Nom'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label required'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-input', 'placeholder' => 'email@exemple.com'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label required'],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Étudiant' => 'ROLE_STUDENT',
                    'Professeur' => 'ROLE_PROFESSOR',
                ],
                'placeholder' => 'Sélectionner un rôle',
                'attr' => ['class' => 'form-select'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label required'],
            ])
            ->add('motDePasse', PasswordType::class, [
                'label' => 'Mot de passe',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Laisser vide pour ne pas modifier'],
                'required' => false,
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BoUserCreateDTO::class,
        ]);
    }
}
