<?php

namespace App\Form;

use App\Entity\User;
use App\Form\MediaType;
use Symfony\Component\Form\AbstractType;
use Gregwar\CaptchaBundle\Type\CaptchaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UserKycFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('idDocumentType', ChoiceType::class, [
                'label' => 'form.idDocumentType.label',
                'choices' => [
                    'form.idDocumentType.idCard' => 'idCard',
                    'form.idDocumentType.passport' => 'passport',
                  //  'form.idDocumentType.driverLicense' => 'driverLicense',
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
            ->add('captcha', CaptchaType::class,[
                'label' => false,
                "mapped"=>false,
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
