<?php

namespace App\Form;

use App\Entity\Theme;
use App\Service\Util;
use App\Entity\Setting;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use Symfony\Component\Security\Core\Security;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

class SettingFormType extends AbstractType
{
   
    public function __construct(
        private Util $util,
        private Security $security, 
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager
         )
     {
      
     }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('theme', ChoiceType::class, [
            'choice_loader' => new CallbackChoiceLoader(function () {
                $themes = $this->entityManager->getRepository(Theme::class)->findAll();
                $choices = [];
                foreach ($themes as $theme) {
                    $choices[$theme->getName()] = $theme->getSlug();
                }
                return $choices;
            }),
            'label' => $this->translator->trans('admin.page.setting.theme'),
            'required' => false,
        ])
            ->add('title')
            ->add('address')
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
            ->add('seoImage', ElFinderType::class, [
                'instance' => 'form',
                'enable' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ],
                'label' => $this->translator->trans('admin.page.setting.seoImage'),
            ])
            ->add('seoHomeTitle', TextType::class, [
                'label' => $this->translator->trans('admin.page.setting.seoHomeTitle'),
                'required' => false,
            ])
            ->add('seoHomeKeywords', TextType::class, [
                'label' => $this->translator->trans('admin.page.setting.seoHomeKeywords'),
                'required' => false,
            ])
            ->add('seoHomeDescription', TextareaType::class, [
                'label' => $this->translator->trans('admin.page.setting.seoHomeDescription'),
                'required' => false,
            ])
            ->add('seoAboutTitle', TextType::class, [
                'label' => $this->translator->trans('admin.page.setting.seoAboutTitle'),
                'required' => false,
            ])
            ->add('seoAboutKeywords', TextType::class, [
                'label' => $this->translator->trans('admin.page.setting.seoAboutKeywords'),
                'required' => false,
            ])
            ->add('seoAboutDescription', TextareaType::class, [
                'label' => $this->translator->trans('admin.page.setting.seoAboutDescription'),
                'required' => false,
            ])
            ->add('seoServiceTitle', TextType::class, [
                'label' => $this->translator->trans('admin.page.setting.seoServiceTitle'),
                'required' => false,
            ])
            ->add('seoServiceKeywords', TextType::class, [
                'label' => $this->translator->trans('admin.page.setting.seoServiceKeywords'),
                'required' => false,
            ])
            ->add('seoServiceDescription', TextareaType::class, [
                'label' => $this->translator->trans('admin.page.setting.seoServiceDescription'),
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
