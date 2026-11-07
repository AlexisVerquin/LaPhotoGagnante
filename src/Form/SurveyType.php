<?php

namespace App\Form;

use App\Entity\Survey;
use App\Entity\SurveyChoice;
use App\Repository\SurveyChoiceRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<\App\Entity\Survey>
 */
class SurveyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('dateStart', DateTimeType::class, [
                'label' => 'Date de debut',
            ])
            ->add('dateEnd', DateTimeType::class, [
                'label' => 'Date de fin',
            ])
            ->add('surveyChoices', EntityType::class, [
                'class' => SurveyChoice::class,
                'choice_label' => 'name',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'label' => 'Choix des themes',
                'query_builder' => function (SurveyChoiceRepository $er) {
                    return $er->createQueryBuilder('sc')
                        ->where('sc.survey IS NULL');
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Survey::class,
        ]);
    }
}
