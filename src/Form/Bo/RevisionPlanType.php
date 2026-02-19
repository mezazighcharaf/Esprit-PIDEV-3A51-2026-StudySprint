<?php

namespace App\Form\Bo;

use App\Entity\Chapter;
use App\Entity\RevisionPlan;
use App\Entity\Subject;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
            ->add('title', TextType::class, ['label' => 'Titre'])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'fullName',
                'label' => 'Utilisateur',
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
            ->add('startDate', DateType::class, [
                'label' => 'Date début',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('endDate', DateType::class, [
                'label' => 'Date fin',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => 'DRAFT',
                    'Actif' => 'ACTIVE',
                    'Terminé' => 'DONE',
                ],
            ])
            ->add('generatedByAi', CheckboxType::class, ['label' => 'Généré par IA', 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RevisionPlan::class,
        ]);
    }
}
