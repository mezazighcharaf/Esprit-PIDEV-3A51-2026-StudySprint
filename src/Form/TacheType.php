<?php

namespace App\Form;

use App\Entity\Tache;
use App\Entity\Objectif;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TacheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];
        $builder
            ->add('objectif', EntityType::class, [
                'class' => Objectif::class,
                'choice_label' => 'titre',
                'query_builder' => function (\App\Repository\ObjectifRepository $er) use ($user) {
                    return $er->createQueryBuilder('o')
                        ->where('o.etudiant = :user')
                        ->setParameter('user', $user);
                },
            ])
            ->add('titre')
            ->add('date', \Symfony\Component\Form\Extension\Core\Type\DateType::class, [
                'widget' => 'single_text',
                'label' => 'Date',
            ])
            ->add('duree', null, [
                'attr' => ['min' => 1],
                'help' => 'Durée en minutes (doit être positive)',
            ])
            ->add('priorite', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'choices' => [
                    'Basse' => 'BASSE',
                    'Moyenne' => 'MOYENNE',
                    'Haute' => 'HAUTE',
                ],
            ])
            ->add('statut', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                'choices' => [
                    'À faire' => 'A_FAIRE',
                    'En cours' => 'EN_COURS',
                    'Terminé' => 'TERMINE',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tache::class,
            'user' => null,
        ]);
    }
}
