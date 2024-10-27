<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Media;
use App\Form\MediaType;
use Symfony\Component\Form\AbstractType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class UserProKycFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('companyName', TextType::class, [
                'label' => 'form.companyName.label',
                'required' => true,
            ])
            ->add('companyAddress', TextType::class, [
                'label' => 'form.companyAddress.label',
                'required' => true,
            ])
            ->add('companyCountry', TextType::class, [
                'label' => 'form.companyCountry.label',
                'required' => true,
            ])
            ->add('companyCity', TextType::class, [
                'label' => 'form.companyCity.label',
                'required' => true,
            ])
            ->add('companyZipcode', TextType::class, [
                'label' => 'form.companyZipcode.label',
                'required' => true,
            ])
            ->add('companyEmail', TextType::class, [
                'label' => 'form.companyEmail.label',
                'required' => true,
            ])
            ->add('companyTelephone', TextType::class, [
                'label' => 'form.companyTelephone.label',
                'required' => true,
            ])
            ->add('companyLegalStatus', ChoiceType::class, [
                'label' => 'form.companyLegalStatus.label',
                'choices' => [
                    'form.companyLegalStatus.soleProprietorship' => 'soleProprietorship',
                    'form.companyLegalStatus.limitedLiabilityCompany' => 'limitedLiabilityCompany',
                    'form.companyLegalStatus.corporation' => 'corporation',
                    'form.companyLegalStatus.partnership' => 'partnership',
                    'form.companyLegalStatus.nonProfit' => 'nonProfit',
                 
                ],
                'required' => true,
            ])
            ->add('registrationNumber', TextType::class, [
                'label' => 'form.registrationNumber.label',
                'required' => true,
            ])
            ->add('businessLicense', MediaType::class, [
                'label' => 'form.businessLicense.label',
                'required' => true,
            ])
            ->add('businessRegistration', MediaType::class, [
                'label' => 'form.businessRegistration.label',
                'required' => true,
            ])
            ->add('taxCertificate', MediaType::class, [
                'label' => 'form.taxCertificate.label',
                'required' => true,
            ])
            ->add('companyProfessionalExperience', CKEditorType::class, [
                'label' => 'form.companyProfessionalExperience.label',
                'config_name' => 'basic',
                'attr' => [
                    'rows' => 3,
                ],
                'required' => true
            ])
            ->add('idDocumentType', ChoiceType::class, [
                'label' => 'form.idDocumentType.label',
                'choices' => [
                    'form.idDocumentType.idCard' => 'idCard',
                    'form.idDocumentType.passport' => 'passport',
                    'form.idDocumentType.driverLicense' => 'driverLicense',
                ],
                'required' => true,
            ])
            ->add('idDocumentFront', MediaType::class, [
                'label' => 'form.idDocumentFront.label',
                'required' => true,
            ])
            ->add('idDocumentBack', MediaType::class, [
                'label' => 'form.idDocumentBack.label',
                'required' => true,
            ])
            ->add('proofOfAddressType', ChoiceType::class, [
                'label' => 'form.proofOfAddressType.label',
                'choices' => [
                    'form.proofOfAddressType.electricityBill' => 'electricityBill',
                    'form.proofOfAddressType.gasBill' => 'gasBill',
                    'form.proofOfAddressType.phoneBill' => 'phoneBill',
                    'form.proofOfAddressType.bankStatement' => 'bankStatement',
                    'form.proofOfAddressType.leaseAgreement' => 'leaseAgreement',
                ],
                'required' => true,
            ])
            ->add('proofOfAddress', MediaType::class, [
                'label' => 'form.proofOfAddress.label',
                'required' => true,
            ])
            ->add('integrityDocumentType', ChoiceType::class, [
                'label' => 'form.integrityDocumentType.label',
                'choices' => [
                    'form.integrityDocumentType.goodConductCertificate' => 'goodConductCertificate',
                    'form.integrityDocumentType.nonBankruptcyCertificate' => 'nonBankruptcyCertificate',
                    'form.integrityDocumentType.nonConvictionCertificate' => 'nonConvictionCertificate',
                    'form.integrityDocumentType.professionalReferences' => 'professionalReferences',
                ],
                'required' => true,
            ])
            ->add('integrityDocument', MediaType::class, [
                'label' => 'form.integrityDocument.label',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
