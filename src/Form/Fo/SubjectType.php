<?php

namespace App\Form\Fo;

use App\Entity\Subject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la matière',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex: Mathématiques Avancées'],
                'required' => true,
            ])
            ->add('code', TextType::class, [
                'label' => 'Code matière (optionnel)',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex: MATH301'],
                'required' => false,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-textarea', 'rows' => 4, 'placeholder' => 'Décrivez le contenu de cette matière...'],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Subject::class,
        ]);
    }
}
