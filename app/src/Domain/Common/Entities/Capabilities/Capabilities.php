<?php

namespace App\Domain\Common\Entities\Capabilities;

use DDD\Domain\Base\Entities\Attributes\NoRecursiveUpdate;
use DDD\Domain\Base\Entities\EntitySet;

/**
 * @property App\Domain\Common\Entities\Capabilities\Capability[] $elements;
 * @method Capability getByUniqueKey(string $uniqueKey)
 * @method Capability[] getElements()
 */
#[NoRecursiveUpdate]
class Capabilities extends EntitySet
{

    /**
     * @var Capability[]
     */
    public array $elements = [];

    /**
     * Check if a capability exists in the set.
     */
    public function hasCapability(string $capability): bool
    {
        foreach ($this->elements as $element) {
            if ($element->getName() === $capability) {
                return true;
            }
        }
        return false;
    }
}