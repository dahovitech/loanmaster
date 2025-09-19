<?php
/*
 *
 * (c) Prudence D. ASSOGBA <jprud67@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
namespace App\Annotation;

use ReflectionClass;
use Gedmo\Mapping\Annotation\Slug;

class SlugableAttributeReader
{
    public function isSlugable(string $entity,$field): bool
    {
        $reflection = new ReflectionClass($entity);
        $slugable = false;

        foreach ($reflection->getProperties() as $property) {
            if ($property->getAttributes(Slug::class)) {
                $slugable = ($property->getName()==$field)??true;
            }
        }
        return $slugable;
    }
}