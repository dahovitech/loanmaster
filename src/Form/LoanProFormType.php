<?php

namespace App\Form;

use App\Entity\Loan;
use Symfony\Component\Form\AbstractType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class LoanProFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('loanType', ChoiceType::class, [
                'label' => 'loan.form.loanType',
                'choices' => [
                    'loan.form.type.business_real_estate' => 'business_real_estate',
                    'loan.form.type.equipment_vehicle' => 'equipment_vehicle',
                    'loan.form.type.franchise' => 'franchise',
                    'loan.form.type.stock' => 'stock',
                    'loan.form.type.business_goodwill' => 'business_goodwill',
                    'loan.form.type.cash_flow' => 'cash_flow',
                    'loan.form.type.business_takeover' => 'business_takeover',
                    'loan.form.type.development_innovation' => 'development_innovation',
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
            ->add('projectName', TextType::class, [
                'label' => 'loan.form.projectName',
                'required' => true,
                'attr' => ['placeholder' => 'loan.form.projectNamePlaceholder']
            ])
            ->add('projectDescription', TextareaType::class, [
                'label' => 'loan.form.projectDescription',
                'required' => true,
                'attr' => ['placeholder' => 'loan.form.projectDescriptionPlaceholder']
            ])
            ->add('projectStartDate', DateType::class, [
                'label' => 'loan.form.projectStartDate',
                'required' => true,
                'widget' => 'single_text',
                'attr' => ['placeholder' => 'loan.form.projectStartDatePlaceholder']
            ])
            ->add('projectEndDate', DateType::class, [
                'label' => 'loan.form.projectEndDate',
                'required' => true,
                'widget' => 'single_text',
                'attr' => ['placeholder' => 'loan.form.projectEndDatePlaceholder']
            ])
            ->add('projectBudget', NumberType::class, [
                'label' => 'loan.form.projectBudget',
                'required' => true,
                'scale' => 2,
                'attr' => ['placeholder' => 'loan.form.projectBudgetPlaceholder']
            ])
            ->add('projectLocation', TextType::class, [
                'label' => 'loan.form.projectLocation',
                'required' => true,
                'attr' => ['placeholder' => 'loan.form.projectLocationPlaceholder']
            ])
            ->add('projectManager', TextType::class, [
                'label' => 'loan.form.projectManager',
                'required' => true,
                'attr' => ['placeholder' => 'loan.form.projectManagerPlaceholder']
            ])
            ->add('projectTeam', CKEditorType::class, [
                'label' => 'loan.form.projectTeam',
                'config_name' => 'basic',
                'attr' => [
                    'rows' => 3,
                ],
                'required' => true,
                'attr' => ['placeholder' => 'loan.form.projectTeamPlaceholder']
            ])
            ->add('projectMilestones', CKEditorType::class, [
                'label' => 'loan.form.projectMilestones',
                'config_name' => 'basic',
                'attr' => [
                    'rows' => 3,
                ],
                'required' => true,
                'attr' => ['placeholder' => 'loan.form.projectMilestonesPlaceholder']
            ])
            ->add('projectRisks', CKEditorType::class, [
                'label' => 'loan.form.projectRisks',
                'config_name' => 'basic',
                'attr' => [
                    'rows' => 3,
                ],
                'required' => true,
                'attr' => ['placeholder' => 'loan.form.projectRisksPlaceholder']
            ])
            ->add('projectBenefits', CKEditorType::class, [
                'label' => 'loan.form.projectBenefits',
                'config_name' => 'basic',
                'attr' => [
                    'rows' => 3,
                ],
                'required' => true,
                'attr' => ['placeholder' => 'loan.form.projectBenefitsPlaceholder']
            ])

            ->add('save', SubmitType::class, [
                'label' => 'loan.form.saveButton',
                'attr' => ['class' => 'btn btn-primary']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Loan::class,
        ]);
    }
}
