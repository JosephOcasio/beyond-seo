<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy;

use App\Domain\Common\Entities\Accounts\Accounts;
use App\Domain\Integrations\WordPress\Common\Repo\InternalDB\Accounts\Legacy\InternalDBWPLegacyAccounts;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * WordPress Accounts
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPLegacyAccounts::class)]
class WPLegacyAccounts extends Accounts
{

}