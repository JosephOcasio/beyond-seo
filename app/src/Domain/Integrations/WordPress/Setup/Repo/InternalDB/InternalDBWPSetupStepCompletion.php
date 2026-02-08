<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\InternalDB;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Integrations\WordPress\Setup\Entities\SetupSteps\SetupStepCompletions\WPSetupStepCompletion;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

#use DDD\Domain\Base\Repo\DB\Attributes\EntityCache;
#use DDD\Infrastructure\Cache\Cache;

/**
 * Class InternalDBWPSetupStepCompletion
 * @method WPSetupStepCompletion find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = true, array $initiatorClasses = [])
 */
//#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBWPSetupStepCompletion extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPSetupStepCompletion::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPSetupStepCompletion
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): WPSetupStepCompletion {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return WPSetupStepCompletion
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): WPSetupStepCompletion
    {
        /** @var WPSetupStepCompletion $setupStepCompletion */
        $setupStepCompletion = parent::mapToEntity($useEntityRegistryCache);

        return $setupStepCompletion;
    }
}
