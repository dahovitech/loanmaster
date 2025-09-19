<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Email;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
           
            ->add('currentPassword',null,[
                'required'=>true,
                'mapped'=>false,
                'label' => 'form.user.currentPassword',
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped'=>false,
                'first_options' => [
                    'label' => 'form.user.password',
                    'constraints' => [
                        new NotBlank([
                        ]),
                        new Length([
                            'min' => 6,
                            'max' => 4096,
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => 'form.user.passwordConfirm',
                    'constraints' => [
                        new NotBlank([
                        ]),
                        new Length([
                            'min' => 6,
                            'max' => 4096,
                        ]),
                    ],
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'btn.update',
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
