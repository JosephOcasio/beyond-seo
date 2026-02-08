<?php

declare(strict_types=1);

namespace App\Domain\Common\Entities\Accounts;

use App\Domain\Common\Services\AccountsService;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\QueryOptions\QueryOptions;
use DDD\Domain\Base\Entities\QueryOptions\QueryOptionsTrait;

/**
 * @property Account[] $elements;
 * @method Account getByUniqueKey(string $uniqueKey)
 * @method Account first()
 * @method Account[] getElements()
 * @method static AccountsService getService()
 */
#[QueryOptions(top: 10)]
class Accounts extends EntitySet
{
    use QueryOptionsTrait;

    public const SERVICE_NAME = AccountsService::class;
}
