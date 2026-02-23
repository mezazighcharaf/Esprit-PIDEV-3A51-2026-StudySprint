<?php

namespace App\Form\Fo;

use App\Entity\Chapter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChapterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du chapitre',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex: Introduction aux intégrales'],
                'required' => true,
            ])
            ->add('summary', TextareaType::class, [
                'label' => 'Résumé',
                'attr' => ['class' => 'form-textarea', 'rows' => 3, 'placeholder' => 'Bref résumé du contenu...'],
                'required' => false,
            ])
            ->add('orderNo', IntegerType::class, [
                'label' => 'Numéro d\'ordre',
                'attr' => ['class' => 'form-input', 'min' => 1],
                'required' => true,
            ])
            ->add('attachmentFile', FileType::class, [
                'label' => 'Pièce jointe (PDF/Word)',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => '.pdf,.doc,.docx', 'class' => 'form-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Chapter::class,
        ]);
    }
}
