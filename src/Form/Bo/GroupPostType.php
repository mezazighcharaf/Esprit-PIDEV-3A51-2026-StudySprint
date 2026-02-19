<?php

namespace App\Form\Bo;

use App\Entity\GroupPost;
use App\Entity\StudyGroup;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class GroupPostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('group', EntityType::class, [
                'class' => StudyGroup::class,
                'choice_label' => 'name',
                'label' => 'Groupe',
            ])
            ->add('author', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'label' => 'Auteur',
            ])
            ->add('postType', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Post' => 'POST',
                    'Commentaire' => 'COMMENT',
                ],
            ])
            ->add('title', TextType::class, ['label' => 'Titre', 'required' => false])
            ->add('body', TextareaType::class, ['label' => 'Contenu'])
            ->add('attachmentUrl', UrlType::class, ['label' => 'Pièce jointe URL', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => GroupPost::class,
        ]);
    }
}
