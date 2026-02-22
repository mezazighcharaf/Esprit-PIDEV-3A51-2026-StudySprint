<?php

namespace App\Form;

use App\Dto\ProfileDTO;
use App\Entity\Student;
use App\Entity\Professor;
use App\Service\ProfessorDataProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class ProfileType extends AbstractType
{
    public function __construct(
        private ProfessorDataProvider $professorDataProvider,
        private Security $security
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $this->security->getUser();

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control']
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '+216...']
            ])
            ->add('pays', CountryType::class, [
                'label' => 'Pays',
                'placeholder' => 'Choisir un pays',
                'preferred_choices' => ['TN', 'FR'],
                'attr' => ['class' => 'form-select']
            ]);

        if ($user instanceof Student || $user instanceof Professor) {
            $builder
                ->add('age', IntegerType::class, [
                    'label' => 'Âge',
                    'attr' => ['class' => 'form-control']
                ])
                ->add('sexe', ChoiceType::class, [
                    'label' => 'Sexe',
                    'choices' => ['Homme' => 'H', 'Femme' => 'F'],
                    'attr' => ['class' => 'form-select']
                ])
                ->add('etablissement', ChoiceType::class, [
                    'label' => 'Établissement / Faculté',
                    'choices' => [], // Dynamic
                    'required' => false,
                    'attr' => ['class' => 'form-select']
                ]);

            if ($user instanceof Student) {
                $builder->add('niveau', ChoiceType::class, [
                    'label' => 'Niveau d\'études',
                    'choices' => [], // Dynamic
                    'required' => false,
                    'attr' => ['class' => 'form-select']
                ]);
            } else {
                $builder
                    ->add('specialite', ChoiceType::class, [
                        'label' => 'Spécialité',
                        'choices' => $this->professorDataProvider->getSpecialites(),
                        'placeholder' => 'Choisir une spécialité',
                        'attr' => ['class' => 'form-select']
                    ])
                    ->add('niveauEnseignement', ChoiceType::class, [
                        'label' => 'Niveau enseigné',
                        'choices' => $this->professorDataProvider->getNiveauxEnseignement(),
                        'placeholder' => 'Choisir un niveau',
                        'attr' => ['class' => 'form-select']
                    ])
                    ->add('anneesExperience', IntegerType::class, [
                        'label' => 'Années d\'expérience',
                        'attr' => ['class' => 'form-control']
                    ]);
            }
        }

        // Handle dynamic choices submission
        $builder->addEventListener(\Symfony\Component\Form\FormEvents::PRE_SUBMIT, function (\Symfony\Component\Form\FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (isset($data['etablissement']) && $data['etablissement']) {
                $form->add('etablissement', ChoiceType::class, [
                    'choices' => [$data['etablissement'] => $data['etablissement']],
                    'required' => false,
                    'attr' => ['class' => 'form-select']
                ]);
            }
            if (isset($data['niveau']) && $data['niveau']) {
                $form->add('niveau', ChoiceType::class, [
                    'choices' => [$data['niveau'] => $data['niveau']],
                    'required' => false,
                    'attr' => ['class' => 'form-select']
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfileDTO::class,
            'validation_groups' => function (\Symfony\Component\Form\FormInterface $form) {
                $user = $this->security->getUser();
                if ($user instanceof Student) {
                    return ['Default', 'student'];
                }
                if ($user instanceof Professor) {
                    return ['Default', 'professor'];
                }
                return ['Default'];
            },
        ]);
    }
}
