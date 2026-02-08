<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Services;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollectors;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowDataCompletion;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowDataCompletions;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions\WPFlowQuestion;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowStep;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowSteps;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Steps\InternalDBWPFlowStep;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Steps\InternalDBWPFlowSteps;
use App\Infrastructure\Services\AppService;
use DDD\Domain\Base\Entities\Entity;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Services\Service;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Base\BaseConstants;
use ReflectionException;

/**
 * Class WPFlowStepsService
 */
class WPFlowStepsService extends Service
{
    public const DEFAULT_ENTITY_CLASS = WPFlowStep::class;

    /** @var WPFlowCollectorsService $wpFlowCollectorsService The collectors service */
    protected WPFlowCollectorsService $wpFlowCollectorsService;

    /** @var WPFlowDataCompletionsService $wpFlowDataCompletionsService The data completions service */
    protected WPFlowDataCompletionsService $wpFlowDataCompletionsService;

    /**
     * WPFlowStepsService constructor.
     * @param WPFlowCollectorsService $wpFlowCollectorsService
     * @param WPFlowDataCompletionsService $wpFlowDataCompletionsService
     */
    public function __construct(
        WPFlowCollectorsService $wpFlowCollectorsService,
        WPFlowDataCompletionsService $wpFlowDataCompletionsService
    )
    {
        $this->wpFlowCollectorsService = $wpFlowCollectorsService;
        $this->wpFlowDataCompletionsService = $wpFlowDataCompletionsService;
    }

    /**
     * Create a new step
     * @param WPFlowStep $entity
     * @return Entity
     * @throws BadRequestException
     * @throws Exception
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function update(WPFlowStep $entity): Entity
    {
        return (new InternalDBWPFlowStep())->update($entity);
    }

    /**
     * Get step by id
     * @param int $stepId
     * @param bool $loadLazyloadProperties
     * @return WPFlowStep|null
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     * @noinspection PhpExpressionResultUnusedInspection
     */
    public function getStepById(int $stepId, bool $loadLazyloadProperties = false): ?WPFlowStep
    {
        $stepsRepo = new InternalDBWPFlowStep();
        $step = $stepsRepo->find($stepId, false);
        if($loadLazyloadProperties) {
            $step->completions;
            $step->questions;
            //$step->step;
        }
        return $step;
    }

    /**
     * Get step by id
     * @param string $stepName
     * @return WPFlowStep|null
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getStepByName(string $stepName): ?WPFlowStep
    {
        return (new InternalDBWPFlowStep())->getStepByName($stepName);
    }

    /**
     * Get step by id
     * @param int $priority
     * @return WPFlowSteps|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getPreviousSteps(WPFlowStep $step): ?WPFlowSteps
    {
        $priority = $step->priority;
        return (new InternalDBWPFlowSteps())->getStepsLowestByPriority($priority);
    }

    /**
     * Generate all steps
     * @throws ORMException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws MappingException
     * @throws BadRequestException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws ForbiddenException
     * @throws ReflectionException
     * @throws InternalErrorException
     */
    public function generateSteps(bool $withCompletions = false, bool $withCurrentStep = false): WPFlowSteps
    {
        // get all steps
        $stepsRepo = new InternalDBWPFlowSteps();
        $steps = $stepsRepo->getAllSteps(false);

        $collectors = $this->wpFlowCollectorsService->getCollectors();

        if(!$collectors?->isEmpty()) {
            // @Ionut. Sry, this is the most dummy implementation ever, but it works
            // This allows us to completely remove businessWebsiteUrl from the $wpdb->prefix . rankingcoach_setup_steps table
            // And it doesn't force us to add businessEmailAddress to $wpdb->prefix . rankingcoach_setup_steps
            // And also we can optimize the AI prompt too, removing all businessWebsiteUrl rules
            $this->collectAllRequirementsFromCollectors($collectors);
            
            foreach ($steps->getElements() as $step) {
                if($step->active === false || $step->completed === true) {
                    continue;
                }
                // For this task, it is partially solved by the requirements collector above
                $this->collectStepRequirementsFromCollectors($step, $collectors);
            }
        }

        // fill with lazyload collectors for each steps completions
        if($withCompletions) {
           $this->prefetchCompletionsWithCollectors($steps);
        }

        // set current question for each step
        if($withCurrentStep) {
            $this->prefetchCurrentQuestions($steps);
        }

        // Translate steps
        foreach ($steps->getElements() as $step) {
            $step->translate();
        }

        return $steps;
    }


    /**
     * Gather requirements from collectors
     * @param WPFlowStep $step
     * @param WPFlowCollectors $collectors
     * @return void
     * @throws ORMException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws OptimisticLockException
     * @throws MappingException
     * @throws BadRequestException
     * @throws NonUniqueResultException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws ForbiddenException
     * @throws ReflectionException
     * @throws InternalErrorException
     * @throws MissingMappingDriverImplementation
     */
    private function collectStepRequirementsFromCollectors(WPFlowStep $step, ?WPFlowCollectors $collectors = null): void
    {
        $requiresFunctions = array_map('trim', explode(',', $step->requirements));
        $this->collectRequirementsFromCollectors($collectors, $requiresFunctions, $step);
    }

    /**
     * Collect requirements from collectors
     * @param WPFlowCollectors|null $collectors
     * @param array $requireFunctions
     * @param WPFlowStep|null $step
     * @return void
     * @throws ORMException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws OptimisticLockException
     * @throws MappingException
     * @throws BadRequestException
     * @throws NonUniqueResultException
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws ForbiddenException
     * @throws ReflectionException
     * @throws InternalErrorException
     * @throws MissingMappingDriverImplementation
     */
    private function collectRequirementsFromCollectors(?WPFlowCollectors $collectors, array $requireFunctions, ?WPFlowStep $step = null): void
    {
        foreach ($collectors?->getElements() as $collector) {
            if($collector->active === false) {
                continue;
            }
            $collectorsInstance = new $collector->className($collector->id, $collector->settings);
            foreach ($requireFunctions as $requireFunction) {
                if(!method_exists($collectorsInstance, $requireFunction)) {
                    continue;
                }
                /** @var string|null $data */
                $data = $collectorsInstance->{$requireFunction}();
                if($data && $collector->saveCollectedData) {
                    $this->wpFlowDataCompletionsService->updateRequirements([
                        $requireFunction => $data
                    ]);
                    if($step !== null) {
                        /** @var WPFlowQuestion|null $question */
                        $question = $step->getLastQuestion();
                        $this->wpFlowDataCompletionsService->saveDataCompletion($data, $step, $collector, $question);
                        break;
                    }
                }
            }
        }
        if ($step !== null && !$step->completed) {
            $step->completed = $step->hasCompleted();
            $step->update();
        }
    }

    /**
     * Collect all requirements from collectors
     * @param WPFlowCollectors $collectors
     * @throws BadRequestException
     * @throws Exception
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws MissingMappingDriverImplementation
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function collectAllRequirementsFromCollectors( WPFlowCollectors $collectors): void
    {
        /** @var WPRequirementsService $requirementService */
        $requirementService = AppService::instance()->getService(WPRequirementsService::class);
        $allRequirements = $requirementService->getRequirementsAsArrayNames(false);

        $this->collectRequirementsFromCollectors($collectors, $allRequirements, null);

        // Set the option to true, so we don't collect data again
        if (false === (bool)get_option(BaseConstants::OPTION_ONBOARDING_COLLECT_DATE)) {
            update_option(BaseConstants::OPTION_ONBOARDING_COLLECT_DATE, 1);
        }
    }

    /**
     * Prefetch steps completions with collectors
     * @param WPFlowSteps $steps
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function prefetchCompletionsWithCollectors(WPFlowSteps $steps): void
    {
        /** @var WPFlowStep $step */
        foreach ($steps->getElements() as $step) {
            /** @var WPFlowDataCompletions $completions */
            $completions = $step->completions;
            if(!$completions->isEmpty()) {
                /** @var WPFlowDataCompletion $completion */
                array_map(function($completion) {
                    if($completion->collectorId) {
                        $completion->collector = $this->wpFlowCollectorsService->getCollectorById($completion->collectorId);
                    }
                }, $completions->getElements());
            }
        }
    }

    /**
     * Prefetch current questions for all steps
     * @param WPFlowSteps $steps
     */
    public function prefetchCurrentQuestions(WPFlowSteps $steps): void
    {
        /** @var WPFlowStep $step */
        foreach ($steps->getElements() as $step) {
            $step->hasCurrentQuestion();
        }
    }

    /**
     * Check if a question exists in a specific step
     *
     * @param int $questionId
     * @param WPFlowStep $step
     * @return bool
     */
    public function isQuestionInStep(int $questionId, WPFlowStep $step): bool
    {
        $questions = $step->questions;
        if (!$questionId || !$questions || $questions->isEmpty()) {
            return false;
        }

        $matchingQuestions = array_filter(
            $questions->getElements(),
            fn($question) => $question->id === $questionId
        );

        return !empty($matchingQuestions);
    }

    /**
     * Generate full context for a step
     *
     * @param WPFlowStep $step
     * @return array
     */
    public function generateAIContextForStep(WPFlowStep $step): array
    {
        $context = [
            'completions' => []
        ];

        $stepCompletion = $step->completions;
        if(!$stepCompletion->isEmpty()) {
            foreach($stepCompletion as $completion) {
                // these completions are injected on table by automatic collector, we don't need them on processing
                if($completion->collectorId) {
                    continue;
                }
                $question = $completion->question;
                $context['completions'][] = [
                    'question' => $question->question,
                    'answer' => $completion->answer,
                    'requirements' => $step->requirements
                ];
            }
        }

        return $context;
    }

    /**
     * Check if all steps are completed
     *
     * @return bool
     * @throws BadRequestException
     * @throws Exception
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ORMException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function hasAllStepsCompleted(): bool
    {
        $steps = $this->generateSteps();
        $completedSteps = array_filter(
            $steps->getElements(),
            static fn($step) => $step->completed === true
        );

        return count($completedSteps) === count($steps->getElements());
    }
}
