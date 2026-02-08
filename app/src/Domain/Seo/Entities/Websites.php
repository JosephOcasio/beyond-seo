<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities;

use DDD\Domain\Base\Entities\EntitySet;

/**
 * @property Website[] $elements;
 * @method Website getByUniqueKey(string $uniqueKey)
 * @method Website[] getElements()
 */
class Websites extends EntitySet
{

}
