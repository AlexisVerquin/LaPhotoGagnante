<?php

namespace App\Form;

use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<\App\Entity\Contact>
 */
class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => 'Message',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            // Pot de miel anti-spam : champ invisible pour un humain, que les robots remplissent.
            // Nom volontairement banal pour attirer les robots.
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Ne pas remplir ce champ',
                'attr' => [
                    'autocomplete' => 'off',
                    'tabindex' => '-1',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}
