<?php

namespace App\Form;

use App\Dto\PasswordResetRequestDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PasswordResetRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('method', ChoiceType::class, [
                'choices' => [
                    'Email' => 'email',
                    'Téléphone' => 'telephone',
                ],
                'expanded' => true,
                'multiple' => false,
                'label' => 'Méthode de récupération',
                'data' => 'email',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Votre email'],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Numéro de téléphone',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => '+216...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PasswordResetRequestDTO::class,
            'validation_groups' => function(\Symfony\Component\Form\FormInterface $form) {
                $data = $form->getData();
                if ($data->method === 'telephone') {
                    return ['Default', 'telephone'];
                }
                return ['Default', 'email'];
            },
        ]);
    }
}
