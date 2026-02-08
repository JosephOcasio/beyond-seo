<?php
declare(strict_types=1);

namespace App\Domain\Seo\Services;

use App\Domain\Seo\Entities\CMSTypes\CMSType;
use App\Domain\Seo\Entities\Website;
use DDD\Domain\Base\Services\EntitiesService;

/**
 * Service for WPAccounts entities.
 *
 * @method static Website getEntityClassInstance()
 */
class WebsiteService extends EntitiesService
{
    /** @var string DEFAULT_ENTITY_CLASS The default entity class. */
    public const DEFAULT_ENTITY_CLASS = Website::class;

    /**
     * Retrieves a Website entity by its ID.
     *
     * @param Website $website
     * @param string $cmsType
     * @return void The Website entity.
     */
    public function setWebsiteCMSType(Website &$website, string $cmsType): void
    {
        $cms = new CMSType();

        switch (strtolower($cmsType)) {
            case 'wordpress':
                $cms = $this->setWordPressCMS($website, $cms);
                break;
            case 'joomla':
                //$this->setJoomlaCMS($website, $cms);
                break;
            case 'drupal':
                //$this->setDrupalCMS($website, $cms);
                break;
            default:
                //$this->setUnknownCMS($website, $cms);
                break;
        }

        $website->cmsType = $cms;
    }

    /**
     * Set WordPress CMS type
     *
     * @return CMSType
     */
    private function setWordPressCMS(): CMSType
    {
        global $wp_version;

        $cms = new CMSType();
        $cms->name = 'WordPress';
        $cms->displayName = 'WordPress CMS';
        $cms->priority = 1;
        $cms->active = true;
        $cms->img = 'wordpress-logo.png';
        $cms->alias = 'wp';
        $cms->class = 'cms-wordpress';
        $cms->version = $wp_version;
        $cms->showOnPublic = true;
        $cms->isOnlineShop = class_exists('WooCommerce');
        $cms->cmsDetectionName = 'wordpress';

        return $cms;
    }
}