<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Websites;

use App\Domain\Integrations\WordPress\Plugin\Entities\WPPlugin;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Websites\InternalDBWPWebsite;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Websites\InternalDBWPWebsiteDatabase;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Websites\InternalDBWPWebsiteSettings;
use App\Domain\Integrations\WordPress\Seo\Services\WPWebsiteService;
use App\Domain\Seo\Entities\Website;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * @method WPPlugin getParent()
 * @property WPPlugin $parent;
 */
#[LazyLoadRepo(repoType:LazyLoadRepo::INTERNAL_DB, repoClass:InternalDBWPWebsite::class)]
class WPWebsite extends Website
{

    #============================================
    # region Properties
    #============================================
    /**
     * @var WPWebsiteSetting|null $options The site options
     */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPWebsiteSettings::class)]
    public ?WPWebsiteSetting $settings = null;

    /**
     * @var WPWebsiteDatabase|null $database The site database
     */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPWebsiteDatabase::class)]
    public ?WPWebsiteDatabase $database = null;

    # endregion
    #============================================

    /**
     * Saves the site.
     * @param WPWebsite $site
     * @return void
     */
    public function save(WPWebsite &$site): void
    {
        $service = new WPWebsiteService();
        $site = $service->save($this);
    }
}