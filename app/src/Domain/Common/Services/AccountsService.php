<?php

declare(strict_types=1);

namespace App\Domain\Common\Services;

use App\Domain\Common\Entities\Accounts\Account;
use DDD\Domain\Base\Entities\Entity;

/**
 * @method static Account getEntityClassInstance()
 * @method Account getAccountByEmail(string $email)
 * @method Account find(string|int|null $accountId)
 * @method Account update(Entity $entity)
 * @method Account getAuthAccount()
 */
class AccountsService extends \DDD\Domain\Common\Services\AccountsService
{
    public const DEFAULT_ENTITY_CLASS = Account::class;

}
