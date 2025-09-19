<?php

namespace App\Form;

use App\Entity\Contact;
use App\Entity\Service;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;

class ContactFormType extends AbstractType
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullname', TextType::class, [
                'label' => 'contact.fullname',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'contact.email',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ],
            ])

            ->add('subject', ChoiceType::class, [
                'choice_loader' => new CallbackChoiceLoader(function () {
                    $services = $this->entityManager->getRepository(Service::class)->findByPublish();
                    $choices = [];
                    foreach ($services as $service) {
                        $choices[$service->getName()] = $service->getName();
                    }
                    return $choices;
                }),
                'label' => 'contact.subject',
                'constraints' => [
                    new Assert\NotBlank(),
                ],
            ])
            ->add('message', CKEditorType::class, [
                'label' => 'contact.message',
                'config_name' => 'basic',
                'attr' => [
                    'rows' => 4,
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                ],
                'required' => true
            ])
            ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}
