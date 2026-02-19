<?php

namespace App\Form\Fo;

use App\Entity\StudyGroup;
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
            ->add('name', TextType::class, [
                'label' => 'Nom du groupe',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex: Prépa Maths 2026'],
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-textarea', 'rows' => 4, 'placeholder' => 'Décrivez les objectifs du groupe...'],
                'required' => false,
            ])
            ->add('privacy', ChoiceType::class, [
                'label' => 'Confidentialité',
                'choices' => [
                    'Public - Visible par tous' => StudyGroup::PRIVACY_PUBLIC,
                    'Privé - Sur invitation' => StudyGroup::PRIVACY_PRIVATE,
                ],
                'attr' => ['class' => 'form-input'],
                'required' => true,
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
