<?php

namespace App\Form;

use App\Entity\Post;
use App\Service\Util;

use App\Entity\PostCategory;
use Symfony\Component\Form\AbstractType;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\PostType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

class PostFormType extends AbstractType
{
    private $translator;
    private $util;


    public function __construct(TranslatorInterface $translator, Util $util)
    {
        $this->translator = $translator;
        $this->util = $util;
    }
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder

            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('category', EntityType::class, [
                'class' => PostCategory::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir une catégorie',
                'label' => 'Catégorie',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('isEnabled', CheckboxType::class, [
                'label' => 'Enabled',
                'required' => false,
            ])
            ->add('content', CKEditorType::class, [
                'label' => 'Contenu',
                'config_name' => 'default',
                'attr' => [
                    'rows' => 3,
                ],
                'required' => true
            ])
            ->add('resume', TextareaType::class, [
                'label' => 'Resumé',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])

            ->add('image', ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }
}
