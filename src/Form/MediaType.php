<?php

namespace App\Form;

use App\Entity\Media;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class MediaType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class,[
                'constraints' => array(
                    new File(array(
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'application/pdf', // Ajout du type MIME pour les PDF
                        ],
                    )),
                ),
                'label' => false,
                "attr"=>[
                    'class'=>"dropify"
                ]
              ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Media::class,
        ]);
    }
}
