<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Settings;

use App\Domain\Common\Entities\Settings\Settings;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\Settings\InternalDBWPWebPageSettings;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * WordPress Account Settings
 * @property WPWebPageSetting[] $elements;
 * @method WPWebPageSetting getByUniqueKey(string $uniqueKey)
 * @method WPWebPageSetting[] getElements
 * @method WPWebPageSetting first
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPWebPageSettings::class)]
class WPWebPageSettings extends Settings
{

}