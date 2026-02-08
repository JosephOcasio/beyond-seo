<?php
declare( strict_types=1 );

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Websites;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Integrations\WordPress\Seo\Entities\Websites\Settings\WPWebsiteDiscussionSetting;
use App\Domain\Integrations\WordPress\Seo\Entities\Websites\Settings\WPWebsiteGeneralSetting;
use App\Domain\Integrations\WordPress\Seo\Entities\Websites\Settings\WPWebsiteReadingSetting;
use App\Domain\Integrations\WordPress\Seo\Entities\Websites\WPWebsiteSetting;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Attributes\EntityCache;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Represents a WordPress site options entity.
 * @method WPWebsiteSetting find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = true, array $initiatorClasses = [])
 */
#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBWPWebsiteSettings extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPWebsiteSetting::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPWebsiteSetting|null
     * @throws ReflectionException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): ?WPWebsiteSetting {
        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * MapToEntity
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = false): WPWebsiteSetting
    {
        /** @var WPWebsiteSetting $siteOptions */
        $siteOptions = parent::mapToEntity($useEntityRegistryCache);

        $siteOptions->discussion = new WPWebsiteDiscussionSetting();
        $siteOptions->discussion->commentModeration = get_option('comment_moderation');
        $siteOptions->discussion->moderationKeys = get_option('moderation_keys');
        $siteOptions->discussion->defaultCommentStatus = get_option('default_comment_status');

        $siteOptions->general = new WPWebsiteGeneralSetting();
        $siteOptions->general->timezone = get_option('timezone_string')?: date_default_timezone_get();
        $siteOptions->general->startOfWeek = get_option('start_of_week');
        $siteOptions->general->dateFormat = get_option('date_format');
        $siteOptions->general->timeFormat = get_option('time_format');

        $siteOptions->reading = new WPWebsiteReadingSetting();
        $siteOptions->reading->showOnFront = get_option('show_on_front');
        $siteOptions->reading->pageOnFront = get_option('page_on_front') ? get_the_title(get_option('page_on_front')) : null;
        $siteOptions->reading->pageForPosts = get_option('page_for_posts') ? get_the_title(get_option('page_for_posts')) : null;
        $siteOptions->reading->postsPerPage = get_option('posts_per_page');

        return $siteOptions;
    }
}