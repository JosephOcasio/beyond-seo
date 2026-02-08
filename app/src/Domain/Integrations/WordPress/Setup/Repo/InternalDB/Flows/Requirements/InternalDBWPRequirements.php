<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Requirements;

use App\Domain\Common\Repo\InternalDB\InternalDBEntitySet;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements\WPRequirements;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Repository for onboarding requirements
 *
 * @method WPRequirements find(DoctrineQueryBuilder $queryBuilder = null, $useEntityRegistryCache = false)
 */
class InternalDBWPRequirements extends InternalDBEntitySet
{
    public const BASE_REPO_CLASS = InternalDBWPRequirement::class;
    public const BASE_ENTITY_SET_CLASS = WPRequirements::class;

    /**
     * @param bool $useCache
     * @return WPRequirements|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getRequirements(bool $useCache = false): ?WPRequirements
    {
        $queryBuilder = static::createQueryBuilder();
        return $this->find($queryBuilder, $useCache);
    }


}
