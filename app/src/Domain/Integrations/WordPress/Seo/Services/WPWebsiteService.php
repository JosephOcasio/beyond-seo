<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Services;

use App\Domain\Integrations\WordPress\Plugin\Entities\WPPlugin;
use App\Domain\Integrations\WordPress\Seo\Entities\Websites\WPWebsite;
use DDD\Domain\Base\Services\EntitiesService;

/**
 * Service for WPWebsite entities.
 *
 * @method static WPWebsite getEntityClassInstance()
 */
class WPWebsiteService extends EntitiesService
{
    /** @var string DEFAULT_ENTITY_CLASS The default entity class. */
    public const DEFAULT_ENTITY_CLASS = WPWebsite::class;

    /**
     * Retrieves a WPWebsite entity by its ID.
     *
     * @return WPWebsite The site entity.
     */
    public function getSiteOptions(): WPWebsite
    {
        $plugin = new WPPlugin();
        return $plugin->website;
    }
}