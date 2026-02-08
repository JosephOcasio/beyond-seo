<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBSeoFactorsModel;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\FactorStatus;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Repo\DB\Attributes\EntityCache;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Mapping\MappingException;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * @method Factor find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?InternalDBSeoFactorsModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 * @property InternalDBSeoFactorsModel $ormInstance
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBSeoFactor::class)]
#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBSeoFactor extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = Factor::class;
    public const BASE_ORM_MODEL = InternalDBSeoFactorsModel::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return Factor
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): Factor {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return Factor
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): Factor
    {
        /** @var Factor $factor */
        $factor = parent::mapToEntity($useEntityRegistryCache);
        $ormInstance = $this->ormInstance;

        $factor->id = $ormInstance->id;
        $factor->contextId = $ormInstance->contextId;
        $factor->factorKey = $ormInstance->factorKey;
        $factor->factorName = $ormInstance->factorName;
        $factor->description = $ormInstance->description;
        $factor->weight = (float) $ormInstance->weight;
        $factor->score = (float) $ormInstance->score;
        $factor->status = FactorStatus::fromScore($factor->score);
        $factor->fetchedData = $ormInstance->fetchedData;

        return $factor;
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws ReflectionException
     */
    protected function mapToRepository(Entity &$entity): bool
    {
        /** @var Factor $entity */
        parent::mapToRepository($entity);
        if(isset($this->ormInstance->id)) {
            $this->ormInstance->id = (int)$entity->id;
        }
        $this->ormInstance->contextId = (int)$entity->contextId;
        $this->ormInstance->factorKey = $entity->factorKey;
        $this->ormInstance->factorName = $entity->factorName;
        $this->ormInstance->description = $entity->description;
        $this->ormInstance->weight = (string)$entity->weight;
        $this->ormInstance->score = (string)$entity->score;
        $this->ormInstance->fetchedData = $entity->fetchedData;

        return true;
    }

    /**
     * Save the entity to the database.
     *
     * @param Factor $factor
     * @return Entity|null True on success, false on failure.
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws ForbiddenException
     * @throws Exception
     * @throws MappingException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws JsonException
     */
    public function save(Factor $factor): ?Entity
    {
        $operations = $factor->operations;
        /** @var Operation $operation */
        foreach ($operations->elements as $operation) {
            $operation->factorId = $factor->id;
            /** @var InternalDBSeoOperation $repo */
            $repo = $operation->getService()->getOperationRepository();
            $repo->update($operation);
        }

        return $factor;
    }
}
