<?php

namespace App\Form;

use App\Entity\Language;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\LocaleType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class LanguageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
            ])
            ->add('code', LocaleType::class, [
                'label' => 'Code',
            ])
            ->add('dir', ChoiceType::class, [
                'choices' => [
                    'ltr' => 'ltr',
                    'rtl' => 'rtl',
                ],
                'label' => 'Direction',
            ])
            ->add('isDefault', CheckboxType::class, [
                'label' => 'Par défaut',
                'required' => false,
            ])

            ->add('isEnabled', CheckboxType::class, [
                'label' => 'Activé',
                'required' => false,
            ])
           
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Language::class,
        ]);
    }
}
