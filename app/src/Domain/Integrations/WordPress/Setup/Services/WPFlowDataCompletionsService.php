<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Services;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Utils\RCApiOperations;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollector;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowDataCompletion;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Completions\WPFlowEvaluateData;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Questions\WPFlowQuestion;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements\WPRequirement;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements\WPRequirements;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowStep;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowSteps;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\WPFlowRequirements;
use App\Domain\Integrations\WordPress\Setup\Exceptions\InvalidQuestionException;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Completions\InternalDBWPFlowCompletion;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Questions\InternalDBWPFlowQuestion;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Steps\InternalDBWPFlowStep;
use App\Domain\Integrations\WordPress\Setup\Repo\RC\Flow\RCFlowDataCompletion;
use App\Infrastructure\Services\AppService;
use DDD\Domain\Base\Entities\Entity;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ExceptionDetail;
use DDD\Infrastructure\Exceptions\ExceptionDetails;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Services\Service;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use ReflectionException;

/**
 * Class WPFlowDataCompletionsService
 */
class WPFlowDataCompletionsService extends Service
{
    use RcLoggerTrait;

    public const DEFAULT_ENTITY_CLASS = WPFlowDataCompletion::class;

    /**
     * Get completion by step and collector and question
     * @param WPFlowDataCompletion $entity
     * @return WPFlowDataCompletion
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
    public function update(WPFlowDataCompletion $entity): Entity
    {
        $repo = new InternalDBWPFlowCompletion();
        return $repo->update($entity);
    }

    /**
     * Sanitize completion data to avoid overly long answers
     *
     * @param WPFlowDataCompletion $completion
     * @return void
     */
    private function sanitizeCompletionData(WPFlowDataCompletion $completion): void
    {
        // Avoid overly long answers; this is also handled by the React app
        if(strlen($completion->answer) > 255) {
            $completion->answer = substr($completion->answer, 0, 255);
        }
    }

    /**
     * Update the step completion status based on save count
     *
     * @param WPFlowStep $step
     * @return void
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     */
    private function updateStepCompletionStatus(WPFlowStep $step): void
    {
        $newStep = $step->userSaveCount + 1;
        if($newStep >= WPFlowStep::STEP_MAX_COUNT_COMPLETION) {
            $step->completed = true;
        }
        $step->userSaveCount = $newStep;
        $step->update();
    }

    /**
     * Handle API failure counter
     *
     * @param bool $failApi
     * @return int
     */
    private function handleApiFailureCounter(bool $failApi): int
    {
        if($failApi) {
            $failOrErrorCount = get_option(BaseConstants::OPTION_SETUP_AI_FAIL_ERROR_COUNT, 0);
            $failOrErrorCount = $failOrErrorCount + 1;
            update_option(BaseConstants::OPTION_SETUP_AI_FAIL_ERROR_COUNT, $failOrErrorCount);

            // If the failure counter reaches 2 (two failures), stop trying the AI for this step
            // and let the user move on. The next step is still determined even if $failApi is true
            // (see getNextStepIfApplicable). Reset the counter so future steps or sessions can try again.
            if($failOrErrorCount > 1) {
                update_option(BaseConstants::OPTION_SETUP_AI_FAIL_ERROR_COUNT, 0);
            }
        }
        return $failOrErrorCount ?? 0;
    }

    /**
     * Get API failure count
     *
     * @return int
     */
    private function getApiFailureCount(): int
    {
        return (int)get_option(BaseConstants::OPTION_SETUP_AI_FAIL_ERROR_COUNT, 0);
    }

    /**
     * Refresh step state
     *
     * @param WPFlowStep $step
     * @return void
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    private function refreshStepState(WPFlowStep &$step): void
    {
        // Refresh the step to get the latest data
        $step = $step->refresh();

        // Check if the step is completed and has a current question
        $step->hasCurrentQuestion();
    }

    /**
     * Get next step if applicable
     *
     * @param WPFlowStep $step
     * @param bool $failApi
     * @return WPFlowStep|null
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
    private function getNextStepIfApplicable(WPFlowStep $step, bool $failApi): ?WPFlowStep
    {
        $nextStep = null;
        if ($step->completed || $failApi) {
            $nextStep = $this->determineNextStep($step);
            $nextStep?->hasCurrentQuestion();
        }
        return $nextStep;
    }


    /**
     * @param WPFlowDataCompletion $savedCompletion
     * @return void
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function populateCompletionMetadata(WPFlowDataCompletion $savedCompletion): void
    {
        /** @var WPRequirementsService $wpRequirementService */
        $wpRequirementService = AppService::instance()->getService(WPRequirementsService::class);
        // Set the current language in the completion data
        $savedCompletion->data->language = WordpressHelpers::current_language_code_helper();
        // Fill the metadata with the current step context
        if (isset($savedCompletion->data->metadata) && is_array($savedCompletion->data->metadata)) {
            $savedCompletion->data->metadata['currentStep'] = $this->generateAIContextForCurrentStep($savedCompletion);
            $savedCompletion->data->metadata['previousSteps'] = $this->generateAIContextForPreviousStepsByStep($savedCompletion->stepId);
            $savedCompletion->data->metadata['collectedRequirements'] = $wpRequirementService->getAllRequirementsValues();
            // Remove the postal address from the collected requirements
            if (isset($savedCompletion->data->metadata['collectedRequirements']['businessGeoAddress'])) {
                unset($savedCompletion->data->metadata['collectedRequirements']['businessGeoAddress']);
            }
        }
    }

    /**
     * Process a step completion and determine the next step if applicable
     *
     * @param WPFlowDataCompletion $completion The completion data
     * @return array The processed step and next step (if applicable)
     * @throws BadRequestException
     * @throws Exception
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function handleAnswerSubmission(WPFlowDataCompletion $completion): array
    {
        $this->sanitizeCompletionData($completion);

        /** @var WPFlowStep $step */
        $step = $this->resolveStepFromCompletion($completion);

        /** @var WPFlowDataCompletion $savedCompletion */
        $savedCompletion = $this->saveCompletion($completion);
        $savedCompletion->step = $step;

        // Populate Metadata after saving and before send to evaluation
        $this->populateCompletionMetadata($savedCompletion);

        // Update Step Status
        $this->updateStepCompletionStatus($savedCompletion->step);

        $failApi = false;
        $evaluationSucceeded = $this->tryEvaluateCompletionWithAI($savedCompletion, $failApi);

        // Handle API failure counter
        $failOrErrorCount = $this->handleApiFailureCounter($failApi);

        // Refresh the step to get the latest data & Check if the step is completed and has a current question
        $this->refreshStepState($savedCompletion->step);

        // Determine the next step if applicable
        $nextStep = $this->getNextStepIfApplicable($savedCompletion->step, $failApi);

        return [
            'step' => $savedCompletion->step,
            'nextStep' => $nextStep,
            'evaluationSucceeded' => $evaluationSucceeded,
            'failOrErrorAPICallOnResult' => $failApi,
            'failOrErrorAPICount' => $failOrErrorCount,
        ];
    }

    /**
     * Validate and retrieve the step associated with the completion
     *
     * @param WPFlowDataCompletion $completion
     * @return WPFlowStep
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    private function resolveStepFromCompletion(WPFlowDataCompletion $completion): WPFlowStep
    {
        /** @var WPFlowStepsService $wpFlowStepsService */
        $wpFlowStepsService = AppService::instance()->getService(WPFlowStepsService::class);

        /** @var WPFlowStep $step */
        $step = $wpFlowStepsService->getStepById($completion->stepId);

        // Validate question if a questionId is provided
        if ($completion->questionId && !$wpFlowStepsService->isQuestionInStep($completion->questionId, $step)) {
            throw new InvalidQuestionException('The specified question was not found in the current step.');
        }

        return $step;
    }

    /**
     * Attempt to evaluate the completion, with error handling
     *
     * @return void
     * @throws BadRequestException
     * @throws Exception
     * @throws ForbiddenException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    private function tryEvaluateCompletionWithAI(WPFlowDataCompletion $savedCompletion, bool &$failApi = false): bool
    {
        try {
            $this->evaluateCompletion($savedCompletion);
            // Check if the API call failed, or return another JSON response
            // $failApi = !$savedCompletion->data->isEvaluated;
        } catch (InternalErrorException $e) {
            if($e->getMessage() === 'Invalid API' || $e->getMessage() === 'Error API') {
                $failApi = true;
            }
            $this->handleEvaluationError($e);
            return false;
        }
        return true;
    }

    /**
     * Handle evaluation errors with appropriate fallback behavior
     *
     * @param InternalErrorException $e
     * @return void
     */
    private function handleEvaluationError(InternalErrorException $e): void
    {
        $content = $e->exceptionDetails ?? null;

        if ($content instanceof ExceptionDetails && !$content->isEmpty()) {
            /** @var ExceptionDetail|null $exception */
            $exception = $content->first();

            if ($exception && str_contains($exception->message, '500 Internal Server Error')) {
                // fallback behavior for API evaluation failures
                // TODO: Implement fallback completion creation and saving
                $this->log('API onboarding evaluation failed, with message: ' . $exception->message, 'ERROR');
            }
        }
    }

    /**
     * Determine the next step if the current step is completed
     *
     * @param WPFlowStep $step
     * @return WPFlowStep|null
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
    private function determineNextStep(WPFlowStep $step): ?WPFlowStep
    {
        $nextStep = $step->getNextStep();
        if ($nextStep) {
            /** @noinspection PhpExpressionResultUnusedInspection */
            $nextStep->questions;
        }

        return $nextStep;
    }

    /**
     * Saves step data completion
     * @param mixed $data
     * @param WPFlowStep $step
     * @param WPFlowCollector|null $collector
     * @param WPFlowQuestion|null $question
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
    public function saveDataCompletion(string $data, WPFlowStep $step, ?WPFlowCollector $collector = null, ?WPFlowQuestion $question = null): void
    {
        if(!$data) {
            return;
        }
        $completionRepo = new InternalDBWPFlowCompletion();
        /** @var WPFlowDataCompletion $completion */
        $completion = $completionRepo->getCompletionByStepAndCollectorAndQuestion($step->id, $collector->id ?? null, $question->id ?? null);
        if(!$completion) {
            $completion = new WPFlowDataCompletion();
            $completion->stepId = $step->id;
            $completion->collectorId = $collector->id;
            $completion->questionId = $question->id;
        }
        $completion->answer = $data;

        $completionData = new WPFlowEvaluateData();

        $completion->data = $completionData;
        $completion->timeOfCompletion = time();
        $completionRepo->update($completion);
    }

    /**
     * Evaluate the completion
     *
     * @param WPFlowDataCompletion $completion
     * @return Entity|null
     * @throws BadRequestException
     * @throws Exception
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function evaluateCompletion(WPFlowDataCompletion $completion): ?WPFlowDataCompletion
    {
        try {
            RCLoad::$logRCCalls = true;

            $rcRepo = new RCFlowDataCompletion();
            $rcRepo->fromEntity($completion);

            RCLoad::$deactivateRCCache = true;
            $rcRepo->rcLoad(false, false, false, false);
            RCLoad::$deactivateRCCache = false;

            /** @var WPFlowDataCompletion $evaluatedCompletion */
            $evaluatedCompletion = $rcRepo->toEntity();

            $this->log_json([
                'operation_type' => 'ai_evaluation',
                'operation_status' => 'success',
                'api_calls' => RCApiOperations::getExecutedRCCalls(),
                'context_entity' => 'flow_step',
                'context_id' => $completion->stepId,
                'context_type' => null,
                'execution_time' => null,
                'error_details' => null,
                'metadata' => [
                    'step_id' => $completion->stepId,
                    'question_id' => $completion->questionId,
                    'collector_id' => $completion->collectorId,
                    'evaluation_type' => 'chat_onboarding',
                    'answer_length' => strlen($completion->answer ?? ''),
                    'has_evaluation_data' => !empty($evaluatedCompletion->data)
                ]
            ], 'save_onboarding_step');
            RCLoad::$logRCCalls = false;

            // Avoid saving the completion if it's empty or null
            if(!$evaluatedCompletion || !$evaluatedCompletion->answer) {
                // Return the original completion if evaluation failed to produce a valid result
                // But the feeling is bad for customers if their answer just disappears and remains the same question
                // TODO: needs some UX improvements here, eventually we can inform the user that the evaluation failed and they can try again
                return $completion;
            }

            // Handle the evaluation data comes from the AI
            $this->handleEvaluationData($evaluatedCompletion);

            // Save the evaluated completion
            $this->saveCompletion($evaluatedCompletion);

        } catch (InternalErrorException $e) {
            $exceptionDetails = $e->exceptionDetails;
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new InternalErrorException($e->getMessage(), $exceptionDetails);
        }

        return $evaluatedCompletion;
    }

    /**
     * Assign existing ID to completion if present
     *
     * @param WPFlowDataCompletion $completion
     * @param InternalDBWPFlowCompletion $repo
     * @return void
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    private function assignExistingIdIfPresent(WPFlowDataCompletion $completion, InternalDBWPFlowCompletion $repo): void
    {
        $existingCompletion = $repo->getCompletionByStepAndCollectorAndQuestion(
            $completion->stepId,
            $completion->collectorId ?? null,
            $completion->questionId ?? null
        );

        if ($existingCompletion) {
            $completion->id = $existingCompletion->id;
        }
    }

    /**
     * Save the completion
     *
     * @param WPFlowDataCompletion $completionToSave
     * @param bool $shouldCheckForExisting
     * @return Entity|null
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
    public function saveCompletion(WPFlowDataCompletion $completionToSave, bool $shouldCheckForExisting = true): ?WPFlowDataCompletion
    {
        $repo = new InternalDBWPFlowCompletion();

        if ($shouldCheckForExisting) {
            $this->assignExistingIdIfPresent($completionToSave, $repo);
        }

        // Persist the data (Update or Insert based on ID presence)
        $savedEntity = $repo->update($completionToSave);

        // Return the fresh entity from DB to ensure we have the latest state (timestamps, triggers, etc.)
        return $repo->getCompletionById($savedEntity->id);
    }

    /**
     * Generate AI context for the current step
     *
     * @param WPFlowDataCompletion $completion
     * @return array
     */
    public function generateAIContextForCurrentStep(WPFlowDataCompletion $completion): array
    {
        // Default empty return values
        $context = [
            'question' => '',
            'answer' => '',
            'requirement' => '',
        ];

        // Skip processing if required metadata is missing
        if (!isset($completion->data->metadata) || !is_array($completion->data->metadata)) {
            return $context;
        }

        // Extract required data directly without cloning
        $step = $completion->step ?? null;
        $question = $completion->question ?? null;

        // Populate context if data is available
        if ($step !== null) {
            $context['requirement'] = $step->requirements ?? '';
        }
        if ($question !== null) {
            $context['question'] = $question->question ?? '';
        }
        $context['answer'] = $completion->answer ?? '';
        $context['number'] = $step->priority ?? '';

        return $context;
    }

    /**
     * Generate full context for a step
     *
     * @param int $stepId
     * @return array
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function generateAIContextForPreviousStepsByStep(int $stepId): array
    {
        $context = [];

        /** @var WPFlowStepsService $wpFlowStepsService */
        $wpFlowStepsService = AppService::instance()->getService(WPFlowStepsService::class);
        /** @var WPFlowStep $step */
        $step = $wpFlowStepsService->getStepById($stepId);
        /** @var WPFlowSteps $previousSteps */
        $previousSteps = $wpFlowStepsService->getPreviousSteps($step);

        if(!$previousSteps->isEmpty()) {
            foreach ($previousSteps->getElements() as $previousStep) {
                $stepContext = $wpFlowStepsService->generateAIContextForStep($previousStep);
                if(!empty($stepContext)) {
                    $context['stepID_' . $previousStep->id] = $stepContext;
                }
            }
        }

        return $context;
    }

    /**
     * Handle the evaluation data
     *
     * @param WPFlowDataCompletion $evaluatedCompletion
     * @return void
     * @throws BadRequestException
     * @throws Exception
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    public function handleEvaluationData(WPFlowDataCompletion $evaluatedCompletion): void
    {
        // Handle the evaluation data
        /** @var WPFlowEvaluateData $evaluatedData */
        $evaluatedData = $evaluatedCompletion->data ?? null;

        // Handle extracted values from the evaluation data
        $this->processExtractedRequirements($evaluatedData);

        // Handle next step question value from the evaluation data
        $this->processNextStepGeneratedQuestion($evaluatedCompletion);

        if(!empty($evaluatedData->evaluationResult) && $evaluatedData->evaluationResult === true) {
            // Mark the completion as successful, AI evaluated a good answer
            $this->markCompletionAsSuccessful($evaluatedCompletion);
        }

        $this->saveCompletion($evaluatedCompletion);
    }

    /**
     * Process extracted requirements from evaluation data
     *
     * @param WPFlowEvaluateData|null $evaluatedData
     * @return void
     * @throws BadRequestException
     * @throws Exception
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @use $wpdb WordPress database object.
     */
    protected function processExtractedRequirements(?WPFlowEvaluateData $evaluatedData): void
    {
        if (empty($evaluatedData->metadata) || empty($evaluatedData->metadata['currentStep']->requirementExtracted)) {
            return;
        }

        $requirementExtracted = $evaluatedData->metadata['currentStep']->requirementExtracted ?? [];

        if (empty($requirementExtracted)) {
            return;
        }
        if(is_object($requirementExtracted)) {
            $requirementExtracted = json_decode(json_encode($requirementExtracted), true);
        }

        // Extract the postal address from the evaluation data
        $postalAddress = $evaluatedData->postalAddress ?? null;
        if($postalAddress) {
            $requirementExtracted['businessGeoAddress'] = $postalAddress;
        }

        // Validate the extracted keywords
        if(!empty($requirementExtracted['businessKeywords'])) {
            $validatedKeywords = $this->validateKeywordsInput($requirementExtracted['businessKeywords'], 'en');
            $requirementExtracted['businessKeywords'] = $validatedKeywords['valid'] ?? [];
            if(!empty($validatedKeywords['errors'])) {
                $this->log('Errors on validating keywords: ' . json_encode($validatedKeywords['errors']), 'ERROR');
            }
        }

        // business requirements update with site title if not present
        if (empty($requirementExtracted['businessName'])) {
            $siteTitle = trim(get_bloginfo('name'));
            if ($siteTitle) {
                /** @var WPFlowStepsService $wpFlowStepsService */
                $wpFlowStepsService = AppService::instance()->getService(WPFlowStepsService::class);
                $businessNameStep = $wpFlowStepsService->getStepByName('SETUP_STEP_BUSINESS_NAME');
                $isBusinessNameStepCompleted = $businessNameStep && $businessNameStep->completed;

                /** @var WPRequirementsService $wpRequirementService */
                $wpRequirementService = AppService::instance()->getService(WPRequirementsService::class);
                $businessNameRequirement = $wpRequirementService->getRequirement('businessName');
                $isBusinessNameInDBEmpty = empty($businessNameRequirement) || empty($businessNameRequirement->value);

                if ($isBusinessNameStepCompleted && $isBusinessNameInDBEmpty) {
                    $requirementExtracted['businessName'] = $siteTitle;
                }
            }
        }

        // requirements always overwrite the previous values
        $this->updateRequirements($requirementExtracted);
    }

    /**
     * Validates an array of keywords.
     *
     * @param array $keywords The keywords to validate.
     * @return array An array containing the valid keywords and the errors.
     */
    public function validateKeywordsInput(array $keywords, string $language = 'en'): array
    {
        $validated = [];
        $errors = [];
        $stopWordsDir = RANKINGCOACH_PLUGIN_DIR . 'inc/Core/Helpers/StopWords/';

        // Accepts any Unicode letters/numbers, spaces, hyphens and underscores
        $pattern = '/^[\p{L}\p{N}\s\-_]+$/u';

        // unique keywords
        $keywords = array_unique($keywords);

        // Basic blackList - can be expanded for multiple languages
        $blacklistFile = $stopWordsDir . 'stopwords-' . $language . '.json';
        if (file_exists($blacklistFile)) {
            $blacklist = json_decode(file_get_contents($blacklistFile), true);
        }

        foreach ($keywords as $kwdRaw) {
            $kwd = trim($kwdRaw);

            if ($kwd === '') {
                $errors[] = 'Empty keyword was ignored.';
                continue;
            }

            if (mb_strlen($kwd) < 3) {
                $errors[] = "Keyword too short: '$kwd'. Minimum 3 characters required.";
                continue;
            }

            if (mb_strlen($kwd) > 50) {
                $errors[] = "Keyword too long: '$kwd'. Maximum allowed is 50 characters.";
                continue;
            }

            if (!preg_match($pattern, $kwd)) {
                $errors[] = "Keyword contains invalid characters: '$kwd'. Only letters, numbers, spaces, hyphens, and underscores are allowed.";
                continue;
            }

            if (isset($blacklist) && in_array(mb_strtolower($kwd), $blacklist)) {
                $errors[] = "Keyword is not allowed: '$kwd'.";
                continue;
            }

            $validated[] = $kwd;
        }

        return [
            'valid' => $validated,
            'errors' => $errors
        ];
    }

    /**
     * Process the next step generated question
     *
     * @param WPFlowDataCompletion $evaluatedCompletion
     * @return void
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
    protected function processNextStepGeneratedQuestion(WPFlowDataCompletion $evaluatedCompletion): void
    {
        // Handle the evaluation data
        /** @var WPFlowEvaluateData $evaluatedData */
        $evaluatedData = $evaluatedCompletion->data ?? null;

        if (empty($evaluatedData->metadata)) {
            return;
        }

        $nextStepQuestion = $evaluatedData->metadata['currentStep']?->nextStepQuestion ?? '';
        if (empty($nextStepQuestion)) {
            return;
        }

        $questionRepo = new InternalDBWPFlowQuestion();
        $stepsRepo = new InternalDBWPFlowStep();
        $nextStep = $stepsRepo->getNextStepByCurrentStepId($evaluatedCompletion->stepId);
        if($nextStep) {
            $nextStepQuestions = $questionRepo->getQuestionsByStep($nextStep->id);

            $newQuestion = new WPFlowQuestion();
            $newQuestion->stepId = $nextStep->id;
            $newQuestion->parentId = $evaluatedCompletion->questionId;
            $newQuestion->question = $nextStepQuestion;
            $newQuestion->sequence = count($nextStepQuestions) + 1;
            $newQuestion->isAiGenerated = true;
            $newQuestion->aiContext = $evaluatedData->evaluationFeedback ?? '';

            $question = $questionRepo->update($newQuestion);
            if ($question) {
                $evaluatedCompletion->questionId = $question->id;
            }
        }
    }

    /**
     * Mark completion as successful and update step status
     *
     * @param WPFlowDataCompletion $evaluatedCompletion
     * @return void
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ReflectionException
     */
    protected function markCompletionAsSuccessful(WPFlowDataCompletion $evaluatedCompletion): void
    {
        // Mark the completion as completed
        $evaluatedCompletion->isCompleted = true;
        $evaluatedCompletion->update();

        // Update the step complete status, based on evaluated completion
        /** @var WPFlowStep $step */
        $step = $evaluatedCompletion->step;
        $step->completed = $step->hasCompleted();
        $step->update();
    }

    /**
     * Update requirements in the database
     *
     * @use $wpdb
     * @param array $requirementExtracted
     * @return void
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
    public function updateRequirements(array $requirementExtracted): void
    {
        /** @var WPRequirementsService $wpRequirementService */
        $wpRequirementService = AppService::instance()->getService(WPRequirementsService::class);

        foreach ($requirementExtracted as $requirementName => $requirementItem) {
            // Convert arrays to JSON string
            $value = is_array($requirementItem) ? json_encode($requirementItem, JSON_THROW_ON_ERROR) : $requirementItem;
            // Convert boolean values to 'y' or 'n'
            $value = is_bool($value) ? ($value ? 'y' : 'n') : $value;

            if(empty($value)) {
                continue;
            }

            // special case for address to not save the address coordinates in DB
            if($requirementName === WPFlowRequirements::SETUP_REQUIREMENT_BUSINESS_ADDRESS && str_contains($value, ' [')) {
                $value = explode(' [', $value)[0];
            }

            // for the moment we don't handle the business service area requirement, all is "y"
            if($requirementName === WPFlowRequirements::SETUP_REQUIREMENT_BUSINESS_SERVICE_AREA) {
                $value = 'y';
            }

            $requirementEntity = new WPRequirement();
            $requirementEntity->setupRequirement = $requirementName;
            $requirementEntity->value = (string)$value;
            $requirementEntity->entityAlias = WPRequirements::$entityAliasBasedOnRequirement[$requirementName];
            unset($requirementEntity->objectType);
            try {
                $wpRequirementService->saveOrUpdateRequirement($requirementEntity);
            } catch (\Throwable $exception) {
                //echo $exception->getMessage(); die;
            }
        }
    }
}
