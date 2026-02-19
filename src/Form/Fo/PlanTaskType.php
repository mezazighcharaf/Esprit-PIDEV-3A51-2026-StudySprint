<?php

namespace App\Form\Fo;

use App\Entity\PlanTask;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la session',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex: Réviser chapitres 1-3'],
                'required' => true,
            ])
            ->add('taskType', ChoiceType::class, [
                'label' => 'Type de session',
                'choices' => [
                    'Révision' => PlanTask::TYPE_REVISION,
                    'Quiz' => PlanTask::TYPE_QUIZ,
                    'Flashcards' => PlanTask::TYPE_FLASHCARD,
                    'Personnalisé' => PlanTask::TYPE_CUSTOM,
                ],
                'attr' => ['class' => 'form-input'],
                'required' => true,
            ])
            ->add('startAt', DateTimeType::class, [
                'label' => 'Date et heure de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input'],
                'required' => true,
            ])
            ->add('endAt', DateTimeType::class, [
                'label' => 'Date et heure de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input'],
                'required' => true,
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'choices' => [
                    'Basse' => 1,
                    'Moyenne' => 2,
                    'Haute' => 3,
                ],
                'attr' => ['class' => 'form-input'],
                'required' => true,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'À faire' => PlanTask::STATUS_TODO,
                    'En cours' => PlanTask::STATUS_DOING,
                    'Terminé' => PlanTask::STATUS_DONE,
                ],
                'attr' => ['class' => 'form-input'],
                'required' => true,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'attr' => ['class' => 'form-textarea', 'rows' => 3, 'placeholder' => 'Notes ou instructions...'],
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanTask::class,
        ]);
    }
}
