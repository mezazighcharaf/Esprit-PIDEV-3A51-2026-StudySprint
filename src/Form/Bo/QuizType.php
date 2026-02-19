<?php

namespace App\Form\Bo;

use App\Entity\Chapter;
use App\Entity\Quiz;
use App\Entity\Subject;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuizType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('owner', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'label' => 'Propriétaire',
            ])
            ->add('subject', EntityType::class, [
                'class' => Subject::class,
                'choice_label' => 'name',
                'label' => 'Matière',
            ])
            ->add('chapter', EntityType::class, [
                'class' => Chapter::class,
                'choice_label' => 'title',
                'label' => 'Chapitre',
                'required' => false,
            ])
            ->add('difficulty', ChoiceType::class, [
                'label' => 'Difficulté',
                'choices' => [
                    'Facile' => 'EASY',
                    'Moyen' => 'MEDIUM',
                    'Difficile' => 'HARD',
                ],
            ])
            ->add('questionsJson', TextareaType::class, [
                'label' => 'Questions (JSON)',
                'mapped' => false,
                'required' => false,
                'data' => $options['data']->getQuestions() ? json_encode($options['data']->getQuestions(), JSON_PRETTY_PRINT) : '[]',
            ])
            ->add('isPublished', CheckboxType::class, ['label' => 'Publié', 'required' => false])
            ->add('generatedByAi', CheckboxType::class, ['label' => 'Généré par IA', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quiz::class,
        ]);
    }
}
