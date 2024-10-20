<?php

namespace App\Form;

use App\Service\Util;
use App\Entity\Setting;
use App\Form\MediaType;
use Symfony\Component\Form\AbstractType;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class SettingType extends AbstractType
{
    private $translator;
    private $util;
    private $security;

    public function __construct(TranslatorInterface $translator, Util $util, Security $security)
    {
        $this->translator = $translator;
        $this->util = $util;
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title')
            ->add('logoDark',  ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('logoLight', ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('emailImg', ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('favicon', ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('email')
            ->add('telephone')
            ->add('devise')
            
            ->add('bankInfo', CKEditorType::class, [
                'label' => 'admin.page.payment.infoBank',
                'config_name' => 'default',
                'attr' => [
                    'rows' => 3,
                ],
                'required' => true
            ])
            ->add('payByCard', CheckboxType::class, [
                'label' => 'Paiement par carte',
                'required' => false,
            ])
            ->add('payByTransfer', CheckboxType::class, [
                'label' => 'Paiement par tranfert manuel',
                'required' => false,
            ]);

        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            $builder->add('emailSender');
        }
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Setting::class,
        ]);
    }
}
