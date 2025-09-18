<?php

namespace App\Form;

use App\Entity\PageTranslation;
use App\Entity\Language;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PageTranslationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Titre de la page'
                ],
                'constraints' => [
                    new Assert\Length(['max' => 255])
                ]
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 10,
                    'placeholder' => 'Contenu de la page'
                ]
            ])
            ->add('metaDescription', TextareaType::class, [
                'label' => 'Meta Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Description pour les moteurs de recherche'
                ],
                'constraints' => [
                    new Assert\Length(['max' => 500])
                ]
            ]);

        if ($options['show_language_field']) {
            $builder->add('language', EntityType::class, [
                'class' => Language::class,
                'choice_label' => 'name',
                'label' => 'Langue',
                'required' => true,
                'attr' => [
                    'class' => 'form-select'
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PageTranslation::class,
            'show_language_field' => false
        ]);
    }
}
