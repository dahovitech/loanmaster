<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Translatable\Entity\MappedSuperclass\AbstractPersonalTranslation;

#[ORM\Entity]
#[ORM\Table(name: 'post_category_translations')]
#[ORM\Index(name: 'post_category_translation_idx', columns: ['locale', 'object_id', 'field'])]
class PostCategoryTranslation extends AbstractPersonalTranslation
{
    #[ORM\ManyToOne(targetEntity: PostCategory::class, inversedBy: 'translations')]
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
