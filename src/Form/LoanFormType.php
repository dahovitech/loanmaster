<?php

namespace App\Form;

use App\Entity\Loan;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoanFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('loanType', ChoiceType::class, [
                'label' => 'loan.form.loanType',
                'choices' => [
                    'loan.form.type.personal' => 'personal',        // Prêt personnel
                    'loan.form.type.mortgage' => 'mortgage',        // Prêt immobilier
                    'loan.form.type.auto' => 'auto',                // Prêt automobile
                    'loan.form.type.student' => 'student',          // Prêt étudiant
                    'loan.form.type.renovation' => 'renovation',    // Prêt rénovation
                    'loan.form.type.vacation' => 'vacation',        // Prêt vacances
                    'loan.form.type.debtConsolidation' => 'debt_consolidation',  // Prêt consolidation de dettes
                    'loan.form.type.medical' => 'medical',          // Prêt médical
                    'loan.form.type.wedding' => 'wedding',          // Prêt mariage
                ],
                'required' => true,
                'placeholder' => 'loan.form.loanTypePlaceholder',
                'attr' => ['class' => 'form-select']
            ])
            ->add('amount', NumberType::class, [
                'label' => 'loan.form.amount',
                'required' => true,
                'scale' => 2,
                'attr' => ['placeholder' => 'loan.form.amountPlaceholder']
            ])
            ->add('durationMonths', NumberType::class, [
                'label' => 'loan.form.durationMonths',
                'required' => true,
                'attr' => ['placeholder' => 'loan.form.durationMonthsPlaceholder']
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'loan.form.notes',
                'required' => false,
                'attr' => ['placeholder' => 'loan.form.notesPlaceholder']
            ])
            ->add('save', SubmitType::class, [
                'label' => 'loan.form.saveButton',
                'attr' => ['class' => 'btn btn-primary w-100 mt-3']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Loan::class,
        ]);
    }
}
