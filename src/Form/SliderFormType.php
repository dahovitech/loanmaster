<?php

namespace App\Form;

use App\Entity\Slider;
use Symfony\Component\Form\AbstractType;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class SliderFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'Subtitle',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])

            ->add('description', CKEditorType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control'
                ],
                'required' => false,
            ])
            ->add('image', ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('btnText', TextType::class, [
                'label' => 'Btn Text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('btnUrl', TextType::class, [
                'label' => 'Btn Url',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('isEnabled', CheckboxType::class, [
                'label' => 'Enabled',
                'required' => false,
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Slider::class,
        ]);
    }
}
