<?php

namespace App\Form\Bo;

use App\Entity\Chapter;
use App\Entity\Subject;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
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
            ->add('subject', EntityType::class, [
                'class' => Subject::class,
                'choice_label' => 'name',
                'label' => 'Matière',
            ])
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('orderNo', IntegerType::class, ['label' => 'Ordre'])
            ->add('summary', TextareaType::class, ['label' => 'Résumé', 'required' => false])
            ->add('content', TextareaType::class, ['label' => 'Contenu', 'required' => false])
            ->add('attachmentUrl', TextType::class, [
                'label' => 'Pièce jointe (URL PDF/Word)',
                'required' => false,
            ])
            ->add('attachmentFile', FileType::class, [
                'label' => 'Upload (PDF/Word)',
                'mapped' => false,
                'required' => false,
                'attr' => ['accept' => '.pdf,.doc,.docx'],
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
            'data_class' => Chapter::class,
        ]);
    }
}
