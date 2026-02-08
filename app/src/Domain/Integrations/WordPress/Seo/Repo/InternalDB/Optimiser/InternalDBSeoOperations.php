<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser;

use App\Domain\Common\Repo\InternalDB\InternalDBEntitySet;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operations;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Class InternalDBSeoOperations
 *
 * This class is responsible for managing a collection of SEO operations.
 * @method Operations find(DoctrineQueryBuilder $queryBuilder = null, $useEntityRegistryCache = false)
 */
class InternalDBSeoOperations extends InternalDBEntitySet
{
    public const BASE_REPO_CLASS = InternalDBSeoOperation::class;
    public const BASE_ENTITY_SET_CLASS = Operations::class;

    /**
     * @throws MappingException
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws ReflectionException
     * @throws InternalErrorException
     */
    public function lazyload(
        Factor   $factor,
        LazyLoad $lazyloadPropertyInstance
    ): ?Operations {
        return $this->getByFactorId($factor->id, $lazyloadPropertyInstance->useCache);
    }

    /**
     * Get an operation by its unique key
     * @param int $factorId
     * @param bool $useCache
     * @return Operations|null The operation if found, null otherwise
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getByFactorId(int $factorId, bool $useCache = true): ?Operations
    {
        $queryBuilder = self::createQueryBuilder();
        $queryBuilder->where('seo_operation.factorId = :factorId')
            ->setParameter('factorId', $factorId);

        return $this->find($queryBuilder, $useCache);
    }
}
