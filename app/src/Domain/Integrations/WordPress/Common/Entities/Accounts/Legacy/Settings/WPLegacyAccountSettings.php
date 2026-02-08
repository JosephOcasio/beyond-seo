<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy\Settings;

use App\Domain\Common\Entities\Settings\Settings;
use App\Domain\Integrations\WordPress\Common\Repo\InternalDB\Accounts\Legacy\Settings\InternalDBWPLegacyAccountSettings;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * WordPress Account Settings
 * @property WPLegacyAccountSetting[] $elements;
 * @method WPLegacyAccountSetting getByUniqueKey(string $uniqueKey)
 * @method WPLegacyAccountSetting[] getElements
 * @method WPLegacyAccountSetting first
 */
#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, InternalDBWPLegacyAccountSettings::class)]
class WPLegacyAccountSettings extends Settings
{
    public object $settings;
}