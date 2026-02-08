<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Collectors;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBFlowCollectorsModel;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollector;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Attributes\EntityCache;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use Symfony\Component\Validator\Exception\MappingException;

/**
 * Class InternalDBWPFlowCollector
 * @method WPFlowCollector find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = true, array $initiatorClasses = [])
 * @property InternalDBFlowCollectorsModel $ormInstance
 */
#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBWPFlowCollector extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPFlowCollector::class;
    public const BASE_ORM_MODEL = InternalDBFlowCollectorsModel::class;


    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPFlowCollector
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): WPFlowCollector {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return WPFlowCollector
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): WPFlowCollector
    {
        /** @var WPFlowCollector $collector */
        $collector = parent::mapToEntity($useEntityRegistryCache);

        $collector->id = $this->ormInstance->id;
        $collector->collector = $this->ormInstance->collector;
        $collector->settings = $this->ormInstance->settings ? json_decode($this->ormInstance->settings, true) : [];
        $collector->className = $this->ormInstance->className;
        $collector->priority = $this->ormInstance->priority;
        $collector->active = $this->ormInstance->active;

        return $collector;
    }

    /**
     * @param DoctrineQueryBuilder $queryBuilder
     * @return bool
     */
    public static function applyUpdateRightsQuery(DoctrineQueryBuilder &$queryBuilder): bool
    {
        return true;
    }

    /**
     * @param int $id
     * @return WPFlowCollector|null
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getCollectorById(int $id): ?WPFlowCollector
    {
        $queryBuilder = self::createQueryBuilder();
        $queryBuilder
            ->where('flow_collector.id = :id')
            ->setParameter('id', $id);
        return $this->find($queryBuilder);
    }
}