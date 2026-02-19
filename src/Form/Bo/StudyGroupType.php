<?php

namespace App\Form\Bo;

use App\Entity\StudyGroup;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StudyGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('privacy', ChoiceType::class, [
                'label' => 'Visibilité',
                'choices' => [
                    'Public' => 'PUBLIC',
                    'Privé' => 'PRIVATE',
                ],
            ])
            ->add('createdBy', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'label' => 'Créé par',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => StudyGroup::class,
        ]);
    }
}
