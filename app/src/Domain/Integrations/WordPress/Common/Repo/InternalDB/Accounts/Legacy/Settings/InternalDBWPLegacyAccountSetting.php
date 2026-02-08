<?php

namespace App\Domain\Integrations\WordPress\Common\Repo\InternalDB\Accounts\Legacy\Settings;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBUserMetaModel;
use App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy\Settings\WPLegacyAccountSetting;
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
 * @method WPLegacyAccountSetting find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = true, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 * @property InternalDBUserMetaModel $ormInstance
 */
class InternalDBWPLegacyAccountSetting extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPLegacyAccountSetting::class;
    public const BASE_ORM_MODEL = InternalDBUserMetaModel::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPLegacyAccountSetting|null
     * @throws ReflectionException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): ?WPLegacyAccountSetting {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * Maps the repository to the entity.
     *
     * @param bool $useEntityRegistryCache
     * @return WPLegacyAccountSetting|null
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = false): ?WPLegacyAccountSetting
    {
        /** @var WPLegacyAccountSetting $entity */
        $entity = parent::mapToEntity($useEntityRegistryCache);
        $ormInstance = $this->ormInstance;

        $entity->umetaId = $ormInstance->umeta_id;
        $entity->accountId = $ormInstance->user_id;
        $entity->metaKey = $ormInstance->meta_key;
        $entity->metaValue = (object)get_user_meta($ormInstance->user_id, $ormInstance->meta_key);

        return $entity;
    }

    /**
     * @param WPLegacyAccountSetting|Entity $entity
     * @return bool
     * @throws ReflectionException
     */
    protected function mapToRepository(WPLegacyAccountSetting|Entity &$entity): bool
    {
        parent::mapToRepository($entity);
        return true;
    }
}