<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserInfoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

            ->add('name', TextType::class, [
                'label' => 'form.label.name',
            ])
            ->add('city', TextType::class, [
                'label' => 'form.label.city',
                'required' => false,
            ])
            ->add('email', TextType::class, [
                'label' => 'form.label.email',

            ])
            ->add('password', PasswordType::class, [
                'label' => 'form.label.password',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => '••••••••',
                ]
            ])
            ->add('new_password', PasswordType::class, [
                'label' => 'form.label.new_password',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => '••••••••',
                ],
            ])
            ->add('password_confirm', PasswordType::class, [
                'label' => 'form.label.confirmation_password',
                'required' => false,
                'attr' => [
                    'placeholder' => '••••••••',
                ],
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
