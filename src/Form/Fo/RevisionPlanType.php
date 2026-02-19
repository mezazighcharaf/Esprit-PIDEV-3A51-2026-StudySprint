<?php

namespace App\Form\Fo;

use App\Entity\RevisionPlan;
use App\Entity\Subject;
use App\Entity\Chapter;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevisionPlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du plan',
                'attr' => ['class' => 'form-input', 'placeholder' => 'Ex: Préparation examen Maths'],
                'required' => true,
            ])
            ->add('subject', EntityType::class, [
                'class' => Subject::class,
                'choice_label' => 'name',
                'label' => 'Matière',
                'attr' => ['class' => 'form-input'],
                'required' => true,
                'placeholder' => 'Sélectionnez une matière',
            ])
            ->add('chapter', EntityType::class, [
                'class' => Chapter::class,
                'choice_label' => 'title',
                'label' => 'Chapitre (optionnel)',
                'attr' => ['class' => 'form-input'],
                'required' => false,
                'placeholder' => 'Tous les chapitres',
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input'],
                'required' => true,
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-input'],
                'required' => true,
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => RevisionPlan::STATUS_DRAFT,
                    'Actif' => RevisionPlan::STATUS_ACTIVE,
                    'Terminé' => RevisionPlan::STATUS_DONE,
                ],
                'attr' => ['class' => 'form-input'],
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RevisionPlan::class,
        ]);
    }
}
