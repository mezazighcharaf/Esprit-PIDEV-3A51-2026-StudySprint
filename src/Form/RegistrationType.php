<?php

namespace App\Form;

use App\Dto\UserRegistrationDTO;
use App\Service\ProfessorDataProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrationType extends AbstractType
{
    public function __construct(
        private ProfessorDataProvider $professorDataProvider
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('role', ChoiceType::class, [
                'choices' => [
                    'Étudiant' => 'student',
                    'Professeur' => 'professor',
                ],
                'expanded' => true,
                'multiple' => false,
                'label' => 'Je suis :',
                'attr' => ['class' => 'role-selector mb-4'],
            ])
            ->add('nom', TextType::class, ['label' => 'Nom'])
            ->add('prenom', TextType::class, ['label' => 'Prénom'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('motDePasse', PasswordType::class, ['label' => 'Mot de passe'])
            
            // Student specific
            ->add('age', IntegerType::class, [
                'required' => false,
                'label' => 'Âge',
                'attr' => ['class' => 'student-field'],
                'row_attr' => ['class' => 'student-field-row'],
            ])
            ->add('sexe', ChoiceType::class, [
                'choices' => ['Homme' => 'H', 'Femme' => 'F'],
                'required' => false,
                'label' => 'Sexe',
                'attr' => ['class' => 'student-field'],
                'row_attr' => ['class' => 'student-field-row'],
            ])
            ->add('pays', CountryType::class, [
                'required' => false,
                'label' => 'Pays',
                'placeholder' => 'Choisir un pays',
                'preferred_choices' => ['TN', 'FR', 'US', 'CA'],
                'attr' => ['class' => 'form-select mb-3'], // Common field now
                'row_attr' => ['class' => 'mb-3'],
            ])
            // These choice types need to be populated dynamically, essentially allow generic choices or handle via events
            // For simplicity in this prototype, we'll allow extra fields or just use TextType on submit if we don't do strict valid on server side without data.
            // But better: Use ChoiceType with empty choices, and rely on client side validation + permissive submit or PreSubmit event.
            ->add('etablissement', ChoiceType::class, [
                'required' => false,
                'label' => 'Établissement',
                'choices' => [], // Populated via JS/Events
                'placeholder' => 'Choisir un pays d\'abord',
                'attr' => ['class' => 'student-field'],
                'row_attr' => ['class' => 'student-field-row'],
            ])
            ->add('niveau', ChoiceType::class, [
                'required' => false,
                'label' => 'Niveau d\'études',
                'choices' => [], // Populated via JS/Events
                'placeholder' => 'Choisir un pays d\'abord',
                'attr' => ['class' => 'student-field'],
                'row_attr' => ['class' => 'student-field-row'],
            ])

            // Professor specific
            ->add('specialite', ChoiceType::class, [
                'required' => false,
                'label' => 'Spécialité',
                'choices' => $this->professorDataProvider->getSpecialites(),
                'placeholder' => 'Choisir une spécialité',
                'attr' => ['class' => 'professor-field form-select'], // form-select for bootstrap styling
                'row_attr' => ['class' => 'professor-field-row'],
            ])
            ->add('niveauEnseignement', ChoiceType::class, [
                'required' => false,
                'label' => 'Niveau enseigné',
                'choices' => $this->professorDataProvider->getNiveauxEnseignement(),
                'placeholder' => 'Choisir un niveau',
                'attr' => ['class' => 'professor-field form-select'],
                'row_attr' => ['class' => 'professor-field-row'],
            ])
            ->add('anneesExperience', IntegerType::class, [
                'required' => false,
                'label' => 'Années d\'expérience',
                'attr' => ['class' => 'professor-field'],
                'row_attr' => ['class' => 'professor-field-row'],
            ])
        ;

        // Fix for "Invalid choice" error when submitting dynamic choices:
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            // Handle Etablissement
            if (isset($data['etablissement']) && $data['etablissement']) {
                $form->add('etablissement', ChoiceType::class, [
                    'choices' => [$data['etablissement'] => $data['etablissement']], // Hack to validate the submitted value
                    'required' => false,
                    'attr' => ['class' => 'student-field'],
                    'row_attr' => ['class' => 'student-field-row'],
                ]);
            }

            // Handle Niveau
            if (isset($data['niveau']) && $data['niveau']) {
                $form->add('niveau', ChoiceType::class, [
                    'choices' => [$data['niveau'] => $data['niveau']],
                    'required' => false,
                    'attr' => ['class' => 'student-field'],
                    'row_attr' => ['class' => 'student-field-row'],
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserRegistrationDTO::class,
            'data_class' => UserRegistrationDTO::class,
            'validation_groups' => function (\Symfony\Component\Form\FormInterface $form) {
                $data = $form->getData();
                if ($data instanceof UserRegistrationDTO) {
                    if ($data->role === 'student') {
                        return ['Default', 'student'];
                    }
                    if ($data->role === 'professor') {
                        return ['Default', 'professor'];
                    }
                }
                return ['Default'];
            },
        ]);
    }
}
