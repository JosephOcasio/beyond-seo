<?php

namespace App\Domain\Common\Entities\Persons;

use DDD\Domain\Base\Entities\ObjectSet;

/**
 * @property Person[] $elements;
 * @method Person first()
 * @method Person getByUniqueKey(string $uniqueKey)
 * @method Person[] getElements()
 */
class Persons extends ObjectSet
{
}