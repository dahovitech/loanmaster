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

class ConfigurableAttributeReader
{
    public function isConfigurable(string $entity): bool
    {
        $reflection = new ReflectionClass($entity);
        return $reflection->getAttributes(Configurable::class) !== [];
    }
}