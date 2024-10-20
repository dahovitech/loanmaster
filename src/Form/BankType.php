<?php

namespace App\Form;

use App\Entity\Bank;
use Symfony\Component\Form\AbstractType;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class BankType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('text1')
            ->add('logo',ElFinderType::class,[
                'instance' => 'form',
                'enable'=>true,
                "attr"=>[
                    "class"=>"form-control"
                ]
            ])
            ->add('image',ElFinderType::class,[
                'instance' => 'form',
                'enable'=>true,
                "attr"=>[
                    "class"=>"form-control"
                ]
            ])
            ->add('layout',ChoiceType::class,[
                "choices"=>[
                    "center"=>"center",
                    "right"=>"right",
                    "left"=>"left"
                ]
            ])
            ->add('textcolor',ColorType::class)
            ->add('bgcolor',ColorType::class)
            ->add('enabled')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Bank::class,
        ]);
    }
}
