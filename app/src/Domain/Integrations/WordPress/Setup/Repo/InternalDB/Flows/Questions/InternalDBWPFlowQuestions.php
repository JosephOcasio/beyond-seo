<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Questions;

use App\Domain\Common\Repo\InternalDB\InternalDBEntitySet;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBFlowQuestionsModel;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions\WPFlowQuestions;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowStep;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Repository for collections of WordPress flow questions
 *
 * @method WPFlowQuestions find(DoctrineQueryBuilder $queryBuilder = null, $useEntityRegistryCache = true)
 */
class InternalDBWPFlowQuestions extends InternalDBEntitySet
{
    public const BASE_REPO_CLASS = InternalDBWPFlowQuestion::class;
    public const BASE_ENTITY_SET_CLASS = WPFlowQuestions::class;

    /**
     * Lazy loads a question collection
     *
     * @param DefaultObject $initiatingEntity The entity requesting the lazy load
     * @param LazyLoad $lazyloadPropertyInstance The LazyLoad attribute
     * @return WPFlowQuestions|null The loaded questions collection
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadPropertyInstance
    ): ?WPFlowQuestions {
        if ($initiatingEntity instanceof WPFlowStep && $initiatingEntity->id !== null) {
            return $this->getByStep($initiatingEntity, $lazyloadPropertyInstance);
        }

        return $this->getAllQuestions($lazyloadPropertyInstance->useCache);
    }

    /**
     * Gets all questions
     *
     * @param bool $useCache Whether to use cache
     * @return WPFlowQuestions The collection of all questions
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws ReflectionException
     * @throws MappingException
     * @throws InvalidArgumentException
     */
    public function getAllQuestions(bool $useCache = true): WPFlowQuestions
    {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder->select('flow_question');
        return $this->find($queryBuilder, $useCache);
    }

    /**
     * Gets questions by step ID
     *
     * @param WPFlowStep $step The step entity
     * @param LazyLoad $lazyloadAttributeInstance The LazyLoad attribute
     * @return WPFlowQuestions|null The collection of questions for the step
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function getByStep(
        WPFlowStep &$step,
        LazyLoad &$lazyloadAttributeInstance
    ): ?WPFlowQuestions {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder
            ->select('flow_question')
            ->where('flow_question.stepId = :stepId')
            ->setParameter('stepId', $step->id)
            ->orderBy('flow_question.sequence', 'ASC');
        return $this->find($queryBuilder, $lazyloadAttributeInstance->useCache);
    }

    /**
     * Finds questions by step ID (direct method)
     *
     * @param int $stepId The step ID
     * @param bool $useCache Whether to use cache
     * @return WPFlowQuestions|null The collection of questions for the step
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function findByStepId(int $stepId, bool $useCache = true): ?WPFlowQuestions
    {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder
            ->select('flow_question')
            ->from(InternalDBFlowQuestionsModel::class, 'flow_question')
            ->where('flow_question.stepId = :stepId')
            ->setParameter('stepId', $stepId)
            ->orderBy('flow_question.sequence', 'ASC');

        return $this->find($queryBuilder, $useCache);
    }

    /**
     * Gets all questions for a specific step
     *
     * @param WPFlowStep $step
     * @param LazyLoad $lazyloadAttributeInstance
     * @return WPFlowQuestions|null Collection of questions
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getQuestionsByStepId(
        WPFlowStep &$step,
        LazyLoad &$lazyloadAttributeInstance
    ): ?WPFlowQuestions
    {
        return $this->findByStepId($step->id, $lazyloadAttributeInstance->useCache);
    }

    /**
     * Finds follow-up questions for a parent question
     *
     * @param int $parentQuestionId The parent question ID
     * @param bool $useCache Whether to use cache
     * @return WPFlowQuestions The collection of follow-up questions
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function findFollowUpQuestions(int $parentQuestionId, bool $useCache = true): WPFlowQuestions
    {
        $queryBuilder = self::createQueryBuilder();
        $queryBuilder
            ->select('flow_question')
            ->from(InternalDBFlowQuestionsModel::class, 'flow_question')
            ->where('flow_question.parentId = :parentId')
            ->orderBy('flow_question.id', 'ASC')
            ->setParameter('parentId', $parentQuestionId);

        return $this->find($queryBuilder, $useCache);
    }

    /**
     * Finds all active questions for a step
     *
     * @param int $stepId
     * @return array
     */
    public function findActiveQuestionsByStepId(int $stepId): array
    {
        $qb = self::createQueryBuilder()
            ->select('q')
            ->from(InternalDBFlowQuestionsModel::class, 'q')
            ->where('q.stepId = :stepId')
            ->andWhere('q.isAiGenerated = false')
            ->orderBy('q.sequence', 'ASC')
            ->setParameter('stepId', $stepId);
        return $qb
            ->getQuery()
            ->getResult();
    }
}
