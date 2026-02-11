<?php

namespace App\Form\Bo;

use App\Dto\BoUserCreateDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use App\Service\ProfessorDataProvider;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class UserCreateType extends AbstractType
{
    public function __construct(
        private ProfessorDataProvider $professorDataProvider
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Prénom'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label required'],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nom'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label required'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control', 'placeholder' => 'email@exemple.com'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label required'],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Étudiant' => 'ROLE_STUDENT',
                    'Professeur' => 'ROLE_PROFESSOR',
                ],
                'placeholder' => 'Sélectionner un rôle',
                'attr' => ['class' => 'form-select role-selector-field'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label required'],
            ])
            ->add('pays', CountryType::class, [
                'label' => 'Pays',
                'required' => false,
                'placeholder' => 'Sélectionner un pays',
                'attr' => ['class' => 'form-select'],
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label'],
            ])
            
            // Student specific fields
            ->add('age', IntegerType::class, [
                'label' => 'Âge',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'row_attr' => ['class' => 'form-group student-field'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('sexe', ChoiceType::class, [
                'label' => 'Sexe',
                'required' => false,
                'choices' => ['Homme' => 'H', 'Femme' => 'F'],
                'placeholder' => 'Sélectionner',
                'attr' => ['class' => 'form-select'],
                'row_attr' => ['class' => 'form-group student-field'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('etablissement', ChoiceType::class, [
                'label' => 'École / Université',
                'required' => false,
                'choices' => [], // Dynamic
                'placeholder' => 'Choisir un pays d\'abord',
                'attr' => ['class' => 'form-select school-selector-field'],
                'row_attr' => ['class' => 'form-group student-field'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('niveau', ChoiceType::class, [
                'label' => 'Niveau d\'études',
                'required' => false,
                'choices' => [], // Dynamic
                'placeholder' => 'Choisir un pays d\'abord',
                'attr' => ['class' => 'form-select level-selector-field'],
                'row_attr' => ['class' => 'form-group student-field'],
                'label_attr' => ['class' => 'form-label'],
            ])

            // Professor specific fields
            ->add('specialite', ChoiceType::class, [
                'label' => 'Spécialité',
                'required' => false,
                'choices' => $this->professorDataProvider->getSpecialites(),
                'placeholder' => 'Sélectionner une spécialité',
                'attr' => ['class' => 'form-select'],
                'row_attr' => ['class' => 'form-group professor-field'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('niveauEnseignement', ChoiceType::class, [
                'label' => 'Niveau enseigné',
                'required' => false,
                'choices' => $this->professorDataProvider->getNiveauxEnseignement(),
                'placeholder' => 'Sélectionner un niveau',
                'attr' => ['class' => 'form-select'],
                'row_attr' => ['class' => 'form-group professor-field'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('anneesExperience', IntegerType::class, [
                'label' => 'Années d\'expérience',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'row_attr' => ['class' => 'form-group professor-field'],
                'label_attr' => ['class' => 'form-label'],
            ])
            ->add('etablissementProfesseur', TextType::class, [
                'label' => 'Établissement professionnel',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'row_attr' => ['class' => 'form-group professor-field'],
                'label_attr' => ['class' => 'form-label'],
            ])

            ->add('motDePasse', PasswordType::class, [
                'label' => 'Mot de passe',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Laisser vide pour ne pas modifier'],
                'required' => false,
                'row_attr' => ['class' => 'form-group'],
                'label_attr' => ['class' => 'form-label'],
            ])
        ;

        // Dynamic Choice Handlers
        $builder->addEventListener(\Symfony\Component\Form\FormEvents::PRE_SUBMIT, function (\Symfony\Component\Form\FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (isset($data['etablissement']) && $data['etablissement']) {
                $form->add('etablissement', ChoiceType::class, [
                    'choices' => [$data['etablissement'] => $data['etablissement']],
                    'required' => false,
                    'attr' => ['class' => 'form-select school-selector-field'],
                    'row_attr' => ['class' => 'form-group student-field'],
                ]);
            }
            if (isset($data['niveau']) && $data['niveau']) {
                $form->add('niveau', ChoiceType::class, [
                    'choices' => [$data['niveau'] => $data['niveau']],
                    'required' => false,
                    'attr' => ['class' => 'form-select level-selector-field'],
                    'row_attr' => ['class' => 'form-group student-field'],
                ]);
            }
        });

        $builder->addEventListener(\Symfony\Component\Form\FormEvents::POST_SET_DATA, function (\Symfony\Component\Form\FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if ($data && property_exists($data, 'etablissement') && $data->etablissement) {
                $form->add('etablissement', ChoiceType::class, [
                    'choices' => [$data->etablissement => $data->etablissement],
                    'required' => false,
                    'attr' => ['class' => 'form-select school-selector-field'],
                    'row_attr' => ['class' => 'form-group student-field'],
                    'data' => $data->etablissement,
                ]);
            }
            if ($data && property_exists($data, 'niveau') && $data->niveau) {
                $form->add('niveau', ChoiceType::class, [
                    'choices' => [$data->niveau => $data->niveau],
                    'required' => false,
                    'attr' => ['class' => 'form-select level-selector-field'],
                    'row_attr' => ['class' => 'form-group student-field'],
                    'data' => $data->niveau,
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BoUserCreateDTO::class,
            'validation_groups' => function (\Symfony\Component\Form\FormInterface $form) {
                $data = $form->getData();
                $groups = ['Default'];
                
                // Add creation group if it's a new submission (no ID yet)
                // In our case, this DTO is only used for creation in this controller
                $groups[] = 'creation';

                if ($data instanceof BoUserCreateDTO) {
                    if ($data->role === 'ROLE_STUDENT') {
                        $groups[] = 'student';
                    } elseif ($data->role === 'ROLE_PROFESSOR') {
                        $groups[] = 'professor';
                    }
                    
                    if (!empty($data->motDePasse)) {
                        $groups[] = 'password_strength';
                    }
                }
                return $groups;
            },
        ]);
    }
}
