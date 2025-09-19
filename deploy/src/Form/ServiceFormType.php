<?php

namespace App\Form;

use App\Entity\Service;
use App\Entity\ServiceCategory;
use Symfony\Component\Form\AbstractType;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class ServiceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icon',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('category', EntityType::class, [
                'class' => ServiceCategory::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Choisir une catégorie',
                'label' => 'Catégorie',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('resume', TextareaType::class, [
                'label' => 'Resumé',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])

            ->add('description', CKEditorType::class, [
                'label' => 'Description',
                'config_name' => 'default',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('image', ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
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
            'data_class' => Service::class,
        ]);
    }
}
