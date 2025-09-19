<?php

namespace App\Form;

use App\Entity\Bank;
use App\Entity\Loan;
use Symfony\Component\Form\AbstractType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class LoanPayFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        ->add('price', NumberType::class, [
            'label' => 'Frais de traitement de dossier *',
            'required' => true
        ])
        ->add('priceContract', NumberType::class, [
            'label' => 'Frais de contrat *',
            'required' => true
        ])
        ->add('bankInfo', CKEditorType::class, [
            'label' => 'Informations bancaires pour recevoir les paiements de cette demande uniquement *',
            'config_name' => 'default',
            'attr' => [
                'rows' => 3,
            ],
            'required' => true
        ])
        ->add('bank', EntityType::class, [
            'class' => Bank::class,
            'choice_label' => 'name',
        ])
        ->add('save', SubmitType::class, [
            'label' => 'loan.form.saveButton',
            'attr' => ['class' => 'btn btn-primary']
        ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Loan::class,
        ]);
    }
}
