<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Steps;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBFlowStepsModel;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowStep;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Attributes\EntityCache;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Class InternalDBWPFlowCollector
 * @method WPFlowStep find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = true, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = true, array $initiatorClasses = [])
 * @property InternalDBFlowStepsModel $ormInstance
 */
#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBWPFlowStep extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPFlowStep::class;
    public const BASE_ORM_MODEL = InternalDBFlowStepsModel::class;


    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPFlowStep
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): WPFlowStep {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
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
     * @param string $stepName
     * @param bool $useCache
     * @return WPFlowStep|null
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getStepByName(string $stepName, bool $useCache = true): ?WPFlowStep
    {
        $qb = self::createQueryBuilder();
        $qb
            ->select('s')
            ->from(InternalDBFlowStepsModel::class, 's')
            ->where('s.step = :stepName')
            ->setParameter('stepName', $stepName);

        return $this->find($qb, $useCache);
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return WPFlowStep
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): WPFlowStep
    {
        /** @var WPFlowStep $step */
        $step = parent::mapToEntity($useEntityRegistryCache);

        $step->id = $this->ormInstance->id;
        $step->step = $this->ormInstance->step;
        $step->requirements = $this->ormInstance->requirements;
        $step->priority = $this->ormInstance->priority;
        $step->isFinalStep = $this->ormInstance->isFinalStep;
        $step->active = $this->ormInstance->active;
        $step->completed = $this->ormInstance->completed;
        $step->userSaveCount = $this->ormInstance->userSaveCount ?? 0;

        return $step;
    }

    /**
     * @param int $stepId
     * @return WPFlowStep|null
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getNextStepByCurrentStepId(int $stepId): ?WPFlowStep
    {
        $qb = self::createQueryBuilder();
        $qb
            ->select('s')
            ->from(InternalDBFlowStepsModel::class, 's')
            ->where('s.id = :stepId')
            ->setParameter('stepId', $stepId);

        $step = $this->find($qb, true);

        if ($step) {
            $qb = self::createQueryBuilder();
            $qb
                ->select('s')
                ->from(InternalDBFlowStepsModel::class, 's')
                ->where('s.priority > :priority')
                ->andWhere('s.active = 1')
                ->orderBy('s.priority', 'ASC')
                ->setMaxResults(1)
                ->setParameter('priority', $step->priority);

            return $this->find($qb, false);
        }

        return null;
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws ReflectionException
     */
    protected function mapToRepository(Entity &$entity): bool
    {
        /** @var WPFlowStep $entity */
        parent::mapToRepository($entity);
        $ormInstance = $this->ormInstance;

        if($entity->id) {
            $ormInstance->id = (int)$entity->id ?? null;
        }

        if(property_exists($entity, 'step')) {
            $ormInstance->step = $entity->step;
        }

        if(property_exists($entity,'requirements')) {
            $ormInstance->requirements = $entity->requirements;
        }

        if(property_exists($entity,'priority')) {
            $ormInstance->priority = $entity->priority;
        }

        if(property_exists($entity,'isFinalStep')) {
            $ormInstance->isFinalStep = $entity->isFinalStep ?? false;
        }

        if(property_exists($entity,'active')) {
            $ormInstance->active = $entity->active ?? true;
        }

        if(property_exists($entity,'completed')) {
            $ormInstance->completed = $entity->completed ?? false;
        }

        if(property_exists($entity, 'userSaveCount')) {
            $ormInstance->userSaveCount = $entity->userSaveCount ?? 0;
        }

        $this->ormInstance = $ormInstance;
        return true;
    }
}