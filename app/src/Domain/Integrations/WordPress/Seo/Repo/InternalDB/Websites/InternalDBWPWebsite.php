<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Websites;

use App\Domain\Common\Repo\InternalDB\Models\InternalDBOptionModel;
use App\Domain\Integrations\WordPress\Seo\Entities\Websites\WPWebsite;
use App\Domain\Seo\Entities\Domains\Domain;
use App\Domain\Seo\Repo\InternalDB\InternalDBWebsite;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Settings\SettingsManager;
use ReflectionException;

/**
 * InternalDBWPWebsite class
 */
class InternalDBWPWebsite extends InternalDBWebsite
{
    public const BASE_ENTITY_CLASS = WPWebsite::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPWebsite
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): WPWebsite {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * Retrieves a WPWebsite entity by its ID.
     *
     * @return mixed The WPWebsite entity.
     */
    public function getWebsiteOptions(): mixed
    {
        $queryBuilder = self::createQueryBuilder();
        $queryBuilder
            ->select('o')
            ->from(InternalDBOptionModel::class, 'o');
        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return WPWebsite
     * @throws ReflectionException
     * @noinspection PhpExpressionResultUnusedInspection
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): WPWebsite
    {
        /** @var WPWebsite $website */
        $website = parent::mapToEntity($useEntityRegistryCache);

        // Lazyload properties
        $website->settings;
        /**
         * @TODO Implement database lazy loading if needed.
         */
//        $website->database;

        $domain = new Domain();
        $domain->setName(get_site_url());
        $domain->setPath(wp_parse_url(get_site_url(), PHP_URL_PATH) ?? '/');
        $domain->needsHttps = is_ssl();
        $website->domain = $domain;
        $website->addChildren($domain);

        $website->setCMSType('WordPress');

        $theme = wp_get_theme();

        $website->settings->siteUrl = get_site_url();
        $website->settings->homeUrl = get_home_url();
        $website->settings->blogName = get_bloginfo('name');
        $website->settings->blogDescription = get_bloginfo('description');
        $website->settings->adminEmail = get_bloginfo('admin_email');
        $website->settings->siteLanguage = get_locale();
        $website->settings->isMultisite = is_multisite();
        $website->settings->activePlugins = json_encode(get_option('active_plugins') ?? '');
        $website->settings->stylesheet = get_stylesheet();
        $website->settings->theme = $theme->get('Name');
        $website->settings->themeVersion = $theme->get('Version');
        $website->settings->themeAuthor = $theme->get('Author');
        $website->settings->permalinkStructure = get_option('permalink_structure');
        
        // Get allowed countries from plugin settings
        $settingsManager = SettingsManager::instance();
        $allowedCountries = $settingsManager->allowed_countries ?? [];
        $website->settings->allowedCountries = (array) $allowedCountries;

        return $website;
    }
}
