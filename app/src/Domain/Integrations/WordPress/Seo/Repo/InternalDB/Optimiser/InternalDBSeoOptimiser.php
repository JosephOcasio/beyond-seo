<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBSeoOptimisersModel;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\OptimiserContext;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\SeoOptimiser;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Attributes\EntityCache;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use Throwable;

/**
 * @method SeoOptimiser find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?InternalDBSeoOptimisersModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 * @property InternalDBSeoOptimisersModel $ormInstance
 */
#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBSeoOptimiser extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = SeoOptimiser::class;
    public const BASE_ORM_MODEL = InternalDBSeoOptimisersModel::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return SeoOptimiser
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): SeoOptimiser {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * Retrieves a SeoOptimiser entity by its ID.
     *
     * @param int $postId The ID of the post.
     * @return SeoOptimiser|null The SeoOptimiser entity or null if not found.
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws Exception
     * @throws MappingException
     */
    public function getOptimiserByPostId(int $postId) : ?SeoOptimiser
    {
        $queryBuilder = self::createQueryBuilder();
        $queryBuilder
            ->select('o')
            ->from(InternalDBSeoOptimisersModel::class, 'o')
            ->where('o.postId = :postId')
            ->setParameter('postId', $postId)
            ->orderBy('o.id', 'DESC')
            ->setMaxResults(1);

        return $this->find($queryBuilder);
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return SeoOptimiser
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): SeoOptimiser
    {
        /** @var SeoOptimiser $optimiser */
        $optimiser = parent::mapToEntity($useEntityRegistryCache);
        $ormInstance = $this->ormInstance;

        $optimiser->id = $ormInstance->id;
        $optimiser->postId = $ormInstance->postId;
        $optimiser->score = (float) $ormInstance->overallScore;
        $optimiser->analysisDate = $ormInstance->analysisDate;

        return $optimiser;
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws ReflectionException
     */
    protected function mapToRepository(Entity &$entity): bool
    {
        /** @var SeoOptimiser $entity */
        parent::mapToRepository($entity);

        $this->ormInstance->id = (int)$entity->id;
        $this->ormInstance->postId = (int)$entity->postId;
        $this->ormInstance->overallScore = (string)$entity->score;
        $this->ormInstance->analysisDate = $entity->analysisDate;

        return true;
    }

    /**
     * Save the entity to the database.
     *
     * @param SeoOptimiser $optimiser
     * @return Entity|null True on success, false on failure.
     * @throws Throwable
     */
    public function save(SeoOptimiser $optimiser): ?Entity
    {
        // save the entity to the database
        $this->update($optimiser, 0);

        // retrieve the new ID from the database
        $optimiserTmp = $this->getOptimiserByPostId($optimiser->postId);

        // update only the ID of the entity
        $optimiser->id = $optimiserTmp->id;

        $contexts = $optimiser->contexts;
        foreach ($contexts as $context) {
            /** @var OptimiserContext $context */
            if($optimiser->id) {
                $context->analysisId = $optimiser->id;
            }
        }
        $repo = new InternalDBSeoContexts();
        $repo->save($contexts);

        return $optimiser;
    }

    /**
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws BadRequestException
     * @throws ReflectionException
     * @throws NonUniqueResultException
     * @throws InternalErrorException
     * @throws Exception
     */
    public function deleteByPostId(int $postId): bool
    {
        $optimiser = $this->getOptimiserByPostId($postId);
        if (!$optimiser) {
            return false;
        }
        return $this->delete($optimiser);
    }
}
