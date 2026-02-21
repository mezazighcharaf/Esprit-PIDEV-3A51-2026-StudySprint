<?php

namespace App\Form;

use App\Dto\PasswordResetDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PasswordResetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('verificationCode', TextType::class, [
                'label' => 'Code de vérification',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Code de vérification'],
            ])
            ->add('newPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'attr' => ['class' => 'form-control', 'placeholder' => '••••••••'],
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Confirmer le mot de passe',
                'attr' => ['class' => 'form-control', 'placeholder' => '••••••••'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PasswordResetDTO::class,
        ]);
    }
}
