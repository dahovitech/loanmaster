<?php

namespace App\Form;

use App\Entity\Language;
use App\Entity\ServiceTranslation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

class ServiceTranslationType extends AbstractType
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('locale', ChoiceType::class, [
                'choice_loader' => new CallbackChoiceLoader(function () {
                    $languages = $this->entityManager->getRepository(Language::class)->findByPublish();
                    $choices = [];
                    foreach ($languages as $language) {
                        $choices[$language->getName()] = $language->getCode();
                    }
                    return $choices;
                }),
                'label' => 'Language',
            ])
            ->add('field', ChoiceType::class, [
                'choices' => [
                    'nom' => 'name',
                    'résumé' => 'resume',
                    'Description' => 'description',
                ],
                'label' => 'Field',
            ])
            ->add('content', CKEditorType::class, [
                'label' => 'Contenu',
                'config_name' => 'default',
                'attr' => [
                    'rows' => 4,
                ],
                'required' => true
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ServiceTranslation::class,
        ]);
    }
}
