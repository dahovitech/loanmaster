<?php

namespace App\Form;

use App\Entity\Bank;
use Symfony\Component\Form\AbstractType;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class BankFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name' , null, [
                'label' => "Nom de la banque"
            ])
            ->add('managerName' , null, [
                'label' => "Nom du directeur de la banque"
            ])
            ->add('logo', ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                "attr" => [
                    "class" => "form-control"
                ]
            ])
            ->add('signBank', ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                "attr" => [
                    "class" => "form-control"
                ],
                'label' => "Image png de signature du directeur de la banque"
            ])
            ->add('signNotary', ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                "attr" => [
                    "class" => "form-control"
                ],
                'label' => "Image png de signature du notaire"
            ])
            ->add('url', null, [
                'label' => "Lien de la banque"
            ])
            ->add('notary', null, [
                'label' => "Nom et prénoms du notaire"
            ])
            ->add('address', null, [
                'label' => "Adresse de la banque"
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Bank::class,
        ]);
    }
}
