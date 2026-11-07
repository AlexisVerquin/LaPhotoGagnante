<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * @extends AbstractType<\App\Entity\User>
 */
class RegistrationFormType extends AbstractType
{
    public function __construct(
        #[Autowire('%app.min_age%')]
        private int $minAge,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom d\'utilisateur',
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => 'form.label.agree_terms',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(
                        message: 'alert.aggree-terms-not-checked',
                    ),
                ],
            ])
            // Les CGU réservent l'inscription aux personnes ayant l'âge minimum
            ->add('ageConfirmation', CheckboxType::class, [
                'label' => sprintf('J\'ai au moins %d ans', $this->minAge),
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: sprintf('L\'inscription est réservée aux personnes de %d ans et plus.', $this->minAge)),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'form.label.password',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'data-action' => 'change->password#strength',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter a password',
                    ),
                    new Length(
                        min: 8,
                        max: 4096,
                        // max length allowed by Symfony for security reasons
                        minMessage: 'Your password should be at least {{ limit }} characters',
                    ),
                    new PasswordStrength(),
                    new NotCompromisedPassword(),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'attr' => [
                'data-controller' => 'password',
            ],
        ]);
    }
}
