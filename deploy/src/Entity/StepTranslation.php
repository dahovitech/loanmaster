<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Translatable\Entity\MappedSuperclass\AbstractPersonalTranslation;

#[ORM\Entity]
#[ORM\Table(name: 'step_translations')]
#[ORM\Index(name: 'step_translation_idx', columns: ['locale', 'object_id', 'field'])]
class StepTranslation extends AbstractPersonalTranslation
{
    #[ORM\ManyToOne(targetEntity: Step::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(name: 'object_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    protected $object;

    /**
     * Constructeur
     *
     * @param string|null $locale
     * @param string|null $field
     * @param string|null $value
     */
    public function __construct(string $locale = null, string $field = null, string $value = null)
    {
        $this->setLocale($locale);
        $this->setField($field);
        $this->setContent($value);
    }
}
