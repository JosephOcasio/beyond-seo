<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\Domains;

use DDD\Domain\Base\Entities\EntitySet;

/**
 * @property Domain[] $elements;
 * @method Domain getByUniqueKey(string $uniqueKey)
 * @method Domain[] getElements()
 * @method Domain first()
 */
class Domains extends EntitySet
{

}
