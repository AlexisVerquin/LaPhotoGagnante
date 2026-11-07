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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('themeChoice', EntityType::class, [
                'label' => 'Nom du theme :',
                'class' => SurveyChoice::class,
                'choice_label' => 'name',
            ])
            // Les valeurs par défaut (mois en cours) sont posées dans le contrôleur pour un nouveau thème :
            // l'option 'data' écraserait les dates à chaque édition.
            ->add('dateStart', DateType::class, [
                'label' => 'Date de début :',
            ])
            ->add('dateEnd', DateType::class, [
                'label' => 'Date de fin :',
            ])
            ->add('winner', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'name',
                'label' => 'Gagnant',
                'required' => false,
                'placeholder' => '— Pas encore de gagnant —',
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
