<?php
declare(strict_types=1);

namespace App\Domain\Seo\Repo\InternalDB;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Seo\Entities\Website;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use ReflectionException;

/**
 * @method Website find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = true, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 */
class InternalDBWebsite extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = Website::class;

    /**
     * @param bool $useEntityRegistryCache
     * @return Website
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): Website
    {
        /** @var Website $website */
        $website = parent::mapToEntity();

        return $website;
    }

    /**
     * @param Website $entity
     * @return bool
     * @throws ReflectionException
     */
    protected function mapToRepository(Entity &$entity): bool
    {
        parent::mapToRepository($entity);
        return true;
    }
}
