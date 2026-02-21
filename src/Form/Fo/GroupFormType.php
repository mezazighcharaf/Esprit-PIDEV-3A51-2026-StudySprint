<?php

namespace App\Form\Fo;

use App\Dto\GroupCreateDTO;
use App\Dto\GroupUpdateDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroupFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du groupe',
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Ex: Prépa MPSI - Mathématiques',
                ],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label required'],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Sujet / Catégorie',
                'required' => false,
                'attr' => [
                    'class' => 'form-input',
                    'placeholder' => 'Ex: Mathématiques, Physique, Anglais...',
                ],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-textarea',
                    'placeholder' => 'Décrivez le but et les objectifs du groupe...',
                    'rows' => 4,
                ],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('privacy', ChoiceType::class, [
                'label' => 'Type de confidentialité',
                'choices' => [
                    'Public (visible par tous)' => 'public',
                    'Privé (sur invitation)' => 'private',
                    // 'Sur invitation' => 'by_invitation',
                ],
                'attr' => ['class' => 'form-select'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label required'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // Will be set dynamically per use case
        ]);
    }
}
