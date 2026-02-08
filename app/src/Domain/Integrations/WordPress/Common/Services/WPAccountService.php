<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Common\Services;

use App\Domain\Integrations\WordPress\Common\Entities\Accounts\WPAccount;
use DDD\Domain\Base\Services\EntitiesService;

/**
 * Service for WPAccounts entities.
 *
 * @method static WPAccount getEntityClassInstance()
 */
class WPAccountService extends EntitiesService
{
    /** @var string DEFAULT_ENTITY_CLASS The default entity class. */
    public const DEFAULT_ENTITY_CLASS = WPAccount::class;
    
}
