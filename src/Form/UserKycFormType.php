<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Media;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Form\MediaType;

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
                    'form.idDocumentType.driverLicense' => 'driverLicense',
                ],
                'required' => true,
            ])
            ->add('idDocument', MediaType::class, [
                'label' => 'form.idDocument.label',
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
