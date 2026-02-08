<?php
declare( strict_types=1 );

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\Settings;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBContentMetaModel;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Settings\WPWebPageSetting;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Represents a single item of WordPress content meta.
 * @method WPWebPageSetting find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = true, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = false, array $initiatorClasses = [])
 * @property InternalDBContentMetaModel $ormInstance
 */
class InternalDBWPWebPageSetting extends InternalDBEntity
{
	public const BASE_ENTITY_CLASS = WPWebPageSetting::class;
	public const BASE_ORM_MODEL = InternalDBContentMetaModel::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPWebPageSetting|null
     * @throws ReflectionException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): ?WPWebPageSetting {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return WPWebPageSetting
     * @throws ReflectionException
     */
    public function mapToEntity( bool $useEntityRegistryCache = false ): WPWebPageSetting
    {
        /** @var WPWebPageSetting $entity */
        $entity = parent::mapToEntity( $useEntityRegistryCache );
        $ormInstance = $this->ormInstance;

        $entity->cmetaId = $ormInstance->meta_id;
        $entity->contentId = $ormInstance->post_id;
        $entity->metaKey = $ormInstance->meta_key;
        $entity->metaValue = (object)get_post_meta($ormInstance->post_id, $ormInstance->meta_key);

        return $entity;
    }


	/**
	 * @param WPWebPageSetting|Entity $entity
	 * @return bool
	 * @throws ReflectionException
	 */
	protected function mapToRepository(WPWebPageSetting|Entity &$entity): bool
	{
		parent::mapToRepository($entity);
		return true;
	}
}