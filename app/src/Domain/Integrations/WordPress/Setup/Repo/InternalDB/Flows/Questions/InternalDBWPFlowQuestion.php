<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Questions;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBFlowQuestionsModel;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions\WPFlowQuestion;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Repository for WordPress flow questions
 * Class InternalDBWPFlowQuestion
 *
 * @method WPFlowQuestion find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = true, array $initiatorClasses = [])
 * @method save(WPFlowQuestion $question)
 * @property InternalDBFlowQuestionsModel $ormInstance
 */
class InternalDBWPFlowQuestion extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPFlowQuestion::class;
    public const BASE_ORM_MODEL = InternalDBFlowQuestionsModel::class;

    /**
     * Lazy loads a question entity
     *
     * @param DefaultObject $initiatingEntity The entity requesting the lazy load
     * @param LazyLoad $lazyloadAttributeInstance The LazyLoad attribute
     * @return WPFlowQuestion The loaded question entity
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): WPFlowQuestion {
        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * Maps the ORM instance to an entity
     *
     * @param bool $useEntityRegistryCache Whether to use the registry cache
     * @return WPFlowQuestion The mapped entity
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): WPFlowQuestion
    {
        /** @var WPFlowQuestion $question */
        $question = parent::mapToEntity($useEntityRegistryCache);

        $question->id = $this->ormInstance->id;
        $question->parentId = $this->ormInstance->parentId;
        $question->stepId = $this->ormInstance->stepId;
        $question->question = $this->ormInstance->question;
        $question->sequence = $this->ormInstance->sequence;
        $question->aiContext = $this->ormInstance->aiContext;
        $question->isAiGenerated = $this->ormInstance->isAiGenerated;

        return $question;
    }

    /**
     * Maps an entity to the ORM instance
     *
     * @param Entity $entity The entity to map
     * @return bool True if mapping was successful
     * @throws ReflectionException
     */
    protected function mapToRepository(Entity &$entity): bool
    {
        /** @var WPFlowQuestion $entity */
        parent::mapToRepository($entity);
        $ormInstance = $this->ormInstance;

        if($entity->id) {
            $ormInstance->id = (int)$entity->id ?? null;
        }

        if (property_exists($entity,'parentId')) {
            $ormInstance->parentId = $entity->parentId;
        }

        if (property_exists($entity,'stepId')) {
            $ormInstance->stepId = $entity->stepId;
        }

        if (property_exists($entity,'question')) {
            $ormInstance->question = $entity->question;
        }

        if (property_exists($entity,'sequence')) {
            $ormInstance->sequence = $entity->sequence;
        }

        if (property_exists($entity,'aiContext')) {
            $ormInstance->aiContext = $entity->aiContext;
        }

        if (property_exists($entity,'isAiGenerated')) {
            $ormInstance->isAiGenerated = $entity->isAiGenerated;
        }

        $this->ormInstance = $ormInstance;
        return true;
    }

    /**
     * Finds the latest question for a parent question with optional filtering
     *
     * @param int $parentQuestionId The parent question ID
     * @param bool $aiGeneratedOnly Whether to return only AI generated questions
     * @param bool $useCache Whether to use cache
     * @return WPFlowQuestion|null The found question or null
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws NonUniqueResultException
     * @throws ReflectionException
     */
    public function findLatestQuestionByParentId(
        int $parentQuestionId,
        bool $aiGeneratedOnly = false,
        bool $useCache = true
    ): ?WPFlowQuestion
    {
        $queryBuilder = self::createQueryBuilder();
        $queryBuilder
            ->select('q')
            ->from(InternalDBFlowQuestionsModel::class, 'q')
            ->where('q.parentId = :parentId')
            ->setParameter('parentId', $parentQuestionId);

        if ($aiGeneratedOnly) {
            $queryBuilder->andWhere('q.isAiGenerated = true');
        }

        $queryBuilder
            ->orderBy('q.id', 'DESC')
            ->setMaxResults(1);

        if ($useCache) {
            return $this->find($queryBuilder, true);
        } else {
            return $queryBuilder->getQuery()->getOneOrNullResult();
        }
    }

    /**
     * Retrieves all questions for a step
     *
     * @param int $stepId The step ID
     * @return WPFlowQuestion[] The found questions
     */
    public function getQuestionsByStep(int $stepId): array
    {
        $queryBuilder = self::createQueryBuilder();
        $queryBuilder
            ->select('q')
            ->from(InternalDBFlowQuestionsModel::class, 'q')
            ->where('q.stepId = :stepId')
            ->setParameter('stepId', $stepId);

        return $queryBuilder->getQuery()->getResult();
    }
}