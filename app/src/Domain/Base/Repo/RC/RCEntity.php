<?php

declare(strict_types=1);

namespace App\Domain\Base\Repo\RC;

use App\Domain\Base\Repo\RC\Traits\RCTrait;
use DDD\Domain\Base\Entities\Entity;

class RCEntity extends Entity
{
    use RCTrait;
}