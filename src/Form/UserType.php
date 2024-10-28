<?php

namespace App\Form;

use App\Entity\User;
use App\Service\Util;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserType extends AbstractType
{
    public function __construct(
        private Util $util,
        private TranslatorInterface $translator
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('accountType', ChoiceType::class, [
                'choices' => [
                    'form.accountType.choice.individual' => 'individual',
                    'form.accountType.choice.professional' => 'professional',
                ],
                'label' => 'form.accountType.label',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('locale', ChoiceType::class, [
                'choices' => $this->util->getLocalesName(),
                'label' => 'form.locale',
            ])
            ->add('email', EmailType::class, [
                'label' => 'form.user.email',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 255]),
                    new Email(),
                ],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'form.user.lastname',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 190]),
                ],
            ])
            ->add('firstname', TextType::class, [
                'label' => 'form.user.firstname',
                'constraints' => [
                    new NotBlank(),
                    new Length(['max' => 190]),
                ],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'form.user.telephone',
                'constraints' => [
                    new Length(['max' => 255]),
                ],
            ])
            ->add('civility', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    "form.select" => null,
                    "form.mister" => "mister",
                    "form.mrs" => "mrs",
                    "form.miss" => "miss",
                ],
                'label' => "form.civility"
            ])
            ->add('birthdate', DateType::class, [
                'label' => 'form.user.birthdate',
                'widget' => 'single_text',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('nationality', CountryType::class, [
                'required' => true,
                'label' => "form.nationality"
            ])
            ->add('country', CountryType::class, [
                'label' => 'form.user.country',
            ])
            ->add('city', TextType::class, [
                'label' => 'form.user.city',
                'constraints' => [
                    new Length(['max' => 190]),
                ],
            ])
            ->add('zipcode', TextType::class, [
                'label' => 'form.user.zipcode',
                'constraints' => [
                    new Length(['max' => 10]),
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'form.user.address',
                'constraints' => [
                    new Length(['max' => 190]),
                ],
            ])
            ->add('professionnalSituation', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    "form.select" => null,
                    "form.interim" => "Intérim",
                    "CDD" => "CDD",
                    "CDI" => "CDI",
                    "form.housewife" => "Femme au foyer",
                    "form.student" => "Etudiant",
                    "form.retirement" => "Retraité",
                    "form.profession" => "Profession libérale / Travailleur Indépendant",
                    "form.no_job" => "Sans emploi",
                ],
                'label' => "form.professionnalSituation"
            ])
            ->add('monthlyIncome', NumberType::class, [
                'required' => true,
                'label' => $this->translator->trans("form.monthlyIncome",['%devise%'=> $this->util->getSetting()->getDevise()])
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'form.user.password.mismatch',
                'first_options' => [
                    'label' => 'form.user.password',
                    'constraints' => [
                        new NotBlank(),
                        new Length(['min' => 6, 'max' => 4096]),
                    ],
                ],
                'second_options' => [
                    'label' => 'form.user.passwordConfirm',
                    'constraints' => [
                        new NotBlank(),
                        new Length(['min' => 6, 'max' => 4096]),
                    ],
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
