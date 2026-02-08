<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Common\Entities\Accounts;

use App\Domain\Common\Entities\Accounts\Accounts;
use App\Domain\Common\Repo\InternalDB\Accounts\InternalDBAccounts;
use App\Domain\Integrations\WordPress\Common\Services\WPAccountService;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * WordPress Accounts
 * @method WPAccount[] getElements()
 * @method WPAccount|null first()
 * @method WPAccount|null getByUniqueKey(string $uniqueKey)
 * @property WPAccount[] $elements
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBAccounts::class)]
class WPAccounts extends Accounts
{
    public const ENTITY_CLASS = WPAccount::class;
    public const SERVICE_NAME = WPAccountService::class;
}