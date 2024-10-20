<?php

namespace App\Form;

use App\Entity\Theme;
use Symfony\Component\Form\AbstractType;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class ThemeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'theme.name',
            ])
            ->add('image',  ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ],
                'label' => 'theme.image',
            ])
            ->add('primaryColor', ColorType::class, [
                'label' => 'theme.primaryColor',
            ])
            ->add('secondaryColor', ColorType::class, [
                'label' => 'theme.secondaryColor',
            ])
            ->add('header', ChoiceType::class, [
                'choices' => [
                    'header1' => 'header1',
                    'header2' => 'header2',
                    'header2' => 'header2',
                    'header3' => 'header3',
                ],
                'label' => 'theme.header',
            ])
            ->add('slider', ChoiceType::class, [
                'choices' => [
                    'slider1' => 'slider1',
                    'slider2' => 'slider2',
                    'slider2' => 'slider2',
                    'slider3' => 'slider3',
                ],
                'label' => 'theme.slider'
            ])
            ->add('footer', ChoiceType::class, [
                'choices' => [
                    'footer1' => 'footer1',
                    'footer2' => 'footer2',
                    'footer2' => 'footer2',
                    'footer3' => 'footer3',
                ],
                'label' => 'theme.footer',
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
