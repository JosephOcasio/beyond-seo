<?php

declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Completions;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBFlowCompletionsModel;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowDataCompletion;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowEvaluateData;
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
 * @method WPFlowDataCompletion find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = true, array $initiatorClasses = [])
 * @property InternalDBFlowCompletionsModel $ormInstance
 */
#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBWPFlowCompletion extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPFlowDataCompletion::class;
    public const BASE_ORM_MODEL = InternalDBFlowCompletionsModel::class;


    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPFlowDataCompletion
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): WPFlowDataCompletion {

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
     * @param int $stepId
     * @param int|null $collectorId
     * @param int|null $questionId
     * @param bool $useCache
     * @return WPFlowDataCompletion|null
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getCompletionByStepAndCollectorAndQuestion(int $stepId, ?int $collectorId = null, ?int $questionId = null, bool $useCache = true): ?WPFlowDataCompletion
    {
        $queryBuilder = self::createQueryBuilder();
        $queryBuilder
            ->select('c')
            ->from(InternalDBFlowCompletionsModel::class, 'c')
            ->where('c.stepId = :stepId')
            ->andWhere($collectorId === null ?
                'c.collectorId IS NULL' :
                'c.collectorId = :collectorId')
            ->andWhere($questionId === null ?
                'c.questionId IS NULL' :
                'c.questionId = :questionId')
            ->setParameter('stepId', $stepId);

        if ($collectorId !== null) {
            $queryBuilder->setParameter('collectorId', $collectorId);
        }
        if ($questionId !== null) {
            $queryBuilder->setParameter('questionId', $questionId);
        }

        return $this->find($queryBuilder, $useCache);
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return WPFlowDataCompletion
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): WPFlowDataCompletion
    {
        /** @var WPFlowDataCompletion $completion */
        $completion = parent::mapToEntity($useEntityRegistryCache);

        $completion->id = $this->ormInstance->id;
        $completion->stepId = $this->ormInstance->stepId;
        if($this->ormInstance->collectorId) {
            $completion->collectorId = $this->ormInstance->collectorId;
        }
        if($this->ormInstance->questionId) {
            $completion->questionId = $this->ormInstance->questionId;
        }
        $completion->answer = $this->ormInstance->answer;

        $data = new WPFlowEvaluateData();
        $dbData = isset($this->ormInstance->data) ? json_decode($this->ormInstance->data) : null;
        if($dbData) {
            $data->isEvaluated = $dbData->isEvaluated;
            $data->evaluationResult = (bool)$dbData->evaluationResult ?? false;
            $data->evaluationFeedback = $dbData->evaluationFeedback ?? '';
            $data->evaluationRawAIResult = $dbData->evaluationRawAIResult ?? '';
            $data->evaluationRawAIPrompt = $dbData->evaluationRawAIPrompt ?? '';
            $data->metadata = (array)$dbData->metadata ?? [];
        }
        $completion->data = $data;

        $completion->timeOfCompletion = $this->ormInstance->timeOfCompletion;
        $completion->isCompleted = $this->ormInstance->isCompleted;

        return $completion;
    }

    /**
     * @param int|string|null $id
     * @return WPFlowDataCompletion|null
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getCompletionById(int|string|null $id): ?WPFlowDataCompletion
    {
        return $this->find($id);
    }

    /**
     * @param Entity $entity
     * @return bool
     * @throws ReflectionException
     */
    protected function mapToRepository(Entity &$entity): bool
    {
        /** @var WPFlowDataCompletion $entity */
        parent::mapToRepository($entity);
        $ormInstance = $this->ormInstance;

        if($entity->id) {
            $ormInstance->id = (int)$entity->id ?? null;
        }
        if(property_exists($entity,'stepId')) {
            $ormInstance->stepId = $entity->stepId;
        }
        if(property_exists($entity,'collectorId')) {
            $ormInstance->collectorId = $entity->collectorId;
        }
        if(property_exists($entity,'questionId')) {
            $ormInstance->questionId = $entity->questionId;
        }
        if(property_exists($entity,'answer')) {
            $ormInstance->answer = $entity->answer;
        }
        if(!property_exists($entity,'data') || !$entity->data) {
            $data = new WPFlowEvaluateData();
        }
        else {
            $data = $entity->data;
        }
        $ormInstance->data = json_encode($data);
        $ormInstance->timeOfCompletion = time();
        if(property_exists($entity,'isCompleted')) {
            $ormInstance->isCompleted = $entity->isCompleted;
        }

        $this->ormInstance = $ormInstance;
        return true;
    }
}
