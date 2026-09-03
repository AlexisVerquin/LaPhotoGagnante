<?php

namespace App\Form;

use App\Entity\SurveyChoice;
use App\Entity\Theme;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ThemeType extends AbstractType
{
    /**
     * @throws \DateMalformedStringException
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('themeChoice', EntityType::class, [
                'label' => 'Nom du theme :',
                'class' => SurveyChoice::class,
                'choice_label' => 'name',
            ])
            ->add('dateStart', DateType::class, [
                'label' => 'Date de début :',
                'data' => new \DateTime('now')->modify('first day of this month'),

            ])
            ->add('dateEnd', DateType::class, [
                'label' => 'Date de fin :',
                'data' => new \DateTime('now')->modify('last day of this month'),
            ])
            ->add('winner', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'name',
                'label' => 'Gagnant'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Theme::class,
        ]);
    }
}
