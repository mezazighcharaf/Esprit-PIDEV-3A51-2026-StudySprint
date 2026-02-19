<?php

namespace App\Form\Bo;

use App\Entity\PlanTask;
use App\Entity\RevisionPlan;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanTaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plan', EntityType::class, [
                'class' => RevisionPlan::class,
                'choice_label' => 'title',
                'label' => 'Plan',
            ])
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('taskType', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Révision' => 'REVISION',
                    'Quiz' => 'QUIZ',
                    'Flashcard' => 'FLASHCARD',
                    'Personnalisé' => 'CUSTOM',
                ],
            ])
            ->add('startAt', DateTimeType::class, [
                'label' => 'Début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endAt', DateTimeType::class, [
                'label' => 'Fin',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'À faire' => 'TODO',
                    'En cours' => 'DOING',
                    'Terminé' => 'DONE',
                ],
            ])
            ->add('priority', IntegerType::class, [
                'label' => 'Priorité (1-3)',
                'attr' => ['min' => 1, 'max' => 3],
            ])
            ->add('notes', TextareaType::class, ['label' => 'Notes', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlanTask::class,
        ]);
    }
}
