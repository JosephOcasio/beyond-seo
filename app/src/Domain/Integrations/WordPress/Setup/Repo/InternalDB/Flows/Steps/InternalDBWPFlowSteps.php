<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Steps;

use App\Domain\Common\Repo\InternalDB\InternalDBEntitySet;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowSteps;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Class InternalDBWPFlowCollectors
 * @method WPFlowSteps find(DoctrineQueryBuilder $queryBuilder = null, $useEntityRegistryCache = true)
 */
class InternalDBWPFlowSteps extends InternalDBEntitySet
{
    public const BASE_REPO_CLASS = InternalDBWPFlowStep::class;
    public const BASE_ENTITY_SET_CLASS = WPFlowSteps::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadPropertyInstance
     * @return WPFlowSteps|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadPropertyInstance
    ): ?WPFlowSteps {
        return $this->getAllSteps($lazyloadPropertyInstance->useCache);
    }

    /**
     * @param bool $useCache
     * @return WPFlowSteps
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getAllSteps(bool $useCache = true): WPFlowSteps
    {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder
            ->select('flow_step')
            ->orderBy(
                'flow_step.priority',
                'ASC'
            );
        return $this->find($queryBuilder, $useCache);
    }

    /**
     * @param int $priority
     * @return WPFlowSteps|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getStepsLowestByPriority(int $priority): ?WPFlowSteps
    {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder
            ->select('flow_step')
            ->where('flow_step.priority < :priority')
            ->setParameter('priority', $priority)
            ->orderBy(
                'flow_step.priority',
                'ASC'
            );
        return $this->find($queryBuilder);
    }
}