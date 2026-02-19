<?php

namespace App\Form\Fo;

use App\Entity\Subject;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class GeneratePlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', EntityType::class, [
                'class' => Subject::class,
                'choice_label' => 'name',
                'label' => 'Matière',
                'placeholder' => 'Choisir une matière',
                'constraints' => [
                    new Assert\NotNull(['message' => 'Veuillez sélectionner une matière.']),
                ],
            ])
            ->add('startDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de début',
                'constraints' => [
                    new Assert\NotNull(['message' => 'Veuillez entrer une date de début.']),
                ],
            ])
            ->add('endDate', DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date de fin',
                'constraints' => [
                    new Assert\NotNull(['message' => 'Veuillez entrer une date de fin.']),
                ],
            ])
            ->add('sessionsPerDay', IntegerType::class, [
                'label' => 'Sessions par jour',
                'data' => 2,
                'constraints' => [
                    new Assert\Range([
                        'min' => 1,
                        'max' => 4,
                        'notInRangeMessage' => 'Le nombre de sessions doit être entre {{ min }} et {{ max }}.',
                    ]),
                ],
            ])
            ->add('skipWeekends', CheckboxType::class, [
                'label' => 'Exclure les week-ends',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
