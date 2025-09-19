<?php
/*
 *
 * (c) Prudence D. ASSOGBA <jprud67@gmail.com>
 *
 * Pour les informations complètes sur les droits d'auteur et la licence, veuillez consulter le fichier LICENSE
 * qui a été distribué avec ce code source.
 *
 */

namespace App\Annotation;

use ReflectionClass;
use Gedmo\Mapping\Annotation\Translatable;


class TranslatableAnnotationReader
{

    public function getTranslatableField(string $entity): array
    {

        $reflection = new ReflectionClass($entity);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes(Translatable::class)) {
                $properties[] = $property->getName();
            }
        }

        return $properties;
    }
}
