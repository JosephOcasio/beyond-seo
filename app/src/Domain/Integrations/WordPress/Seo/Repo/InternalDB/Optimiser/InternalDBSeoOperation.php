<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBSeoOperationsModel;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Repo\DB\Attributes\EntityCache;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * @method Operation find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?InternalDBSeoOperationsModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 * @property InternalDBSeoOperationsModel $ormInstance
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBSeoOperation::class)]
#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBSeoOperation extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = Operation::class;
    public const BASE_ORM_MODEL = InternalDBSeoOperationsModel::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return Operation
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): Operation {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return Operation
     * @throws ReflectionException
     * @throws JsonException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): Operation
    {
        /** @var Operation $operation */
        $operation = parent::mapToEntity($useEntityRegistryCache);
        $ormInstance = $this->ormInstance;

        $operation->id = $ormInstance->id;
        $operation->factorId = $ormInstance->factorId;
        $operation->operationKey = $ormInstance->operationKey;
        $operation->operationName = $ormInstance->operationName;
        $operation->score = (float) $ormInstance->score;
        $operation->weight = (float) $ormInstance->weight;
        $operation->value = $ormInstance->value;
        $operation->suggestions = $ormInstance->suggestions ? json_decode($ormInstance->suggestions, true, 512, JSON_THROW_ON_ERROR) : [];
        return $operation;
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws ReflectionException
     * @throws JsonException
     */
    protected function mapToRepository(Entity &$entity): bool
    {
        /** @var Operation $entity */
        parent::mapToRepository($entity);
        if(isset($this->ormInstance->id)) {
            $this->ormInstance->id = (int)$entity->id;
        }
        $this->ormInstance->factorId = (int)$entity->factorId;
        $this->ormInstance->operationKey = $entity->operationKey;
        $this->ormInstance->operationName = $entity->operationName;
        $this->ormInstance->weight = (string) $entity->weight;
        $this->ormInstance->score = (string)$entity->score;
        $this->ormInstance->value = $entity->value;
        $this->ormInstance->suggestions = json_encode($entity->suggestions, JSON_THROW_ON_ERROR);

        return true;
    }
}
