<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBContentModel;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\WPWebPageContent;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\WPWebPageContentMetaTags;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\WPWebPage;
use App\Domain\Seo\Entities\WebPages\ContentElements\MainContent;
use App\Domain\Seo\Entities\WebPages\ContentElements\MetaDescription;
use App\Domain\Seo\Entities\WebPages\ContentElements\Title;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Attributes\EntityCache;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * @method WPWebPage find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?InternalDBContentModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 * @property InternalDBContentModel $ormInstance
 */
#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBWPWebPage extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPWebPage::class;
    public const BASE_ORM_MODEL = InternalDBContentModel::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPWebPage|null
     * @throws ReflectionException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): ?WPWebPage {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * Applies update/delete restrictions based on Auth::instance()->getAccount
     *
     * @param DoctrineQueryBuilder $queryBuilder
     *
     * @return bool
     * @noinspection PhpMissingParentCallCommonInspection*/
    public static function applyUpdateRightsQuery(DoctrineQueryBuilder &$queryBuilder): bool
    {
        return false;
    }

    /**
     * @param bool $useEntityRegistryCache
     *
     * @return WPWebPage
     * @throws ReflectionException
     * @throws Exception
     * @noinspection PhpExpressionResultUnusedInspection
     */
    public function mapToEntity( bool $useEntityRegistryCache = false ): WPWebPage
    {
        /** @var WPWebPage $entity */
        $entity = parent::mapToEntity( $useEntityRegistryCache );
        $ormInstance = $this->ormInstance;

        $entity->id = $ormInstance->ID;
        $entity->authorId = $ormInstance->post_author;

        $entity->webPageContent = $ormInstance->post_content;

        // Content collection
        $entity->content = new WPWebPageContent($ormInstance->ID);
        // Main content
        $entity->content->mainContent = new MainContent();
        $entity->content->mainContent->content = $ormInstance->post_content;
        // Title
        $entity->content->title = new Title();
        $entity->content->title->content = $ormInstance->post_title;
        // Meta Description
        $entity->content->metaDescription = new MetaDescription();
        $entity->content->metaDescription->content = $ormInstance->post_excerpt;

        // MetaTags collection
        $entity->metaTags = new WPWebPageContentMetaTags($ormInstance->ID);
        // Lazyload properties
        $entity->metaTags->titleMetaTag;
        $entity->metaTags->descriptionMetaTag;
        $entity->metaTags->keywordsMetaTag;

        $entity->dateCreated = $ormInstance->post_date;
        $entity->dateCreatedGmt = $ormInstance->post_date_gmt;
        $entity->excerpt = $ormInstance->post_excerpt;
        $entity->status = $ormInstance->post_status;
        $entity->commentStatus = $ormInstance->comment_status;
        $entity->pingStatus = $ormInstance->ping_status;
        $entity->password = $ormInstance->post_password;
        $entity->slug = $ormInstance->post_name;
        $entity->toPing = $ormInstance->to_ping;
        $entity->pinged = $ormInstance->pinged;
        $entity->dateModified = $ormInstance->post_modified;
        $entity->dateModifiedGmt = $ormInstance->post_modified_gmt;
        $entity->contentFiltered = $ormInstance->post_content_filtered;
        $entity->parentId = $ormInstance->post_parent;
        $entity->guid = $ormInstance->guid;
        $entity->menuOrder = $ormInstance->menu_order;
        $entity->postType = $ormInstance->post_type;
        $entity->mimeType = $ormInstance->post_mime_type;
        $entity->commentCount = $ormInstance->comment_count;
        $entity->uniqueKey = $entity->uniqueKey();

        // Lazyload properties
        /** @noinspection PhpExpressionResultUnusedInspection */
        $entity->postMeta;

        return $entity;
    }

    /**
     * @param WPWebPage|Entity $entity
     * @return bool
     * @throws ReflectionException
     */
    protected function mapToRepository(WPWebPage|Entity &$entity): bool
    {
        parent::mapToRepository($entity);
        $model = $this->ormInstance;

        $model->ID = $entity->id;
        $model->post_author = $entity->authorId;

        // Main content + Title
        $model->post_content = $entity->content->mainContent->content;
        $model->post_title = $entity->content->title->content;

        $model->post_date = $entity->dateCreated;
        $model->post_date_gmt = $entity->dateCreatedGmt;
        $model->post_excerpt = $entity->excerpt;
        $model->post_status = $entity->status;
        $model->comment_status = $entity->commentStatus;
        $model->ping_status = $entity->pingStatus;
        $model->post_password = $entity->password;
        $model->post_name = $entity->slug;
        $model->to_ping = $entity->toPing;
        $model->pinged = $entity->pinged;
        $model->post_modified = $entity->dateModified;
        $model->post_modified_gmt = $entity->dateModifiedGmt;
        $model->post_content_filtered = $entity->contentFiltered;
        $model->post_parent = $entity->parentId;
        $model->guid = $entity->guid;
        $model->menu_order = $entity->menuOrder;
        $model->post_type = $entity->postType;
        $model->post_mime_type = $entity->mimeType;
        $model->comment_count = $entity->commentCount;

        return true;
    }
}