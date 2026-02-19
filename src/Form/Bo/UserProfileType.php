<?php

namespace App\Form\Bo;

use App\Entity\User;
use App\Entity\UserProfile;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'label' => 'Utilisateur',
            ])
            ->add('level', TextType::class, ['label' => 'Niveau', 'required' => false])
            ->add('specialty', TextType::class, ['label' => 'Spécialité', 'required' => false])
            ->add('bio', TextareaType::class, ['label' => 'Bio', 'required' => false])
            ->add('avatarUrl', UrlType::class, ['label' => 'Avatar URL', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserProfile::class,
        ]);
    }
}
