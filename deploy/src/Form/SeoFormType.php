<?php

namespace App\Form;

use App\Entity\Seo;
use App\Service\Util;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use FM\ElfinderBundle\Form\Type\ElFinderType;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class SeoFormType extends AbstractType
{

    public function __construct(
        private Util $util,
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder

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
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Seo::class,
        ]);
    }
}
