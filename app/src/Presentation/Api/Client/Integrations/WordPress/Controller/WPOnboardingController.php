<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Controller;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Utils\RCApiOperations;
use App\Domain\Common\Services\WPCategoriesService;
use App\Domain\Integrations\WordPress\Plugin\Services\WPPluginService;
use App\Domain\Integrations\WordPress\Seo\Services\WPSeoOptimiserService;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements\WPRequirement;
use App\Domain\Integrations\WordPress\Setup\Services\WPFlowDataCompletionsService;
use App\Domain\Integrations\WordPress\Setup\Services\WPFlowStepsService;
use App\Domain\Integrations\WordPress\Setup\Services\WPRequirementsService;
use App\Domain\Integrations\WordPress\Setup\Repo\RC\Location\RCLocationSuggestions;
use App\Infrastructure\Traits\ResponseErrorTrait;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Categories\WPCategoriesGetRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Categories\WPCategoriesGetResponseDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Location\WPLocationSuggestionsGetRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Location\WPLocationSuggestionsGetResponseDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Flow\WPFlowPostSaveDataCompletionRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Flow\WPFlowStepAndNextStepResponseDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Flow\WPFlowStepResponseDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Flow\WPFlowStepsResponseDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\OnboardingDataResponseDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\OnboardingRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Requirements\WPRequirementPostRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Requirements\WPRequirementPutRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Requirements\WPRequirementResponseDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Requirements\WPRequirementsResponseDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\WPFlowStepCompletionRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Extracts\WPSetupExtractAutoResponseDto;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Presentation\Base\Controller\HttpController;
use DDD\Presentation\Base\OpenApi\Attributes\Summary;
use DDD\Presentation\Base\Router\Routes\Get;
use DDD\Presentation\Base\Router\Routes\Post;
use DDD\Presentation\Base\Router\Routes\Put;
use DDD\Presentation\Base\Router\Routes\Route;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;
use Throwable;

/**
 * Class WPOnboardingController
 */
#[Route('onboarding')]
class WPOnboardingController extends HttpController
{
    use ResponseErrorTrait;

    /**
     * Save onboarding data
     * @param WPPluginService $wpPluginService
     * @param WPRequirementsService $wpRequirementsService
     * @param WPSeoOptimiserService $wpSeoOptimiserService
     * @return OnboardingDataResponseDto
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ReflectionException
     */
    #[Post('/submitOnboarding')]
    #[Summary('Save onboarding data on completion')]
    public function submitOnboarding(
        WPPluginService $wpPluginService,
        WPRequirementsService $wpRequirementsService,
        WPSeoOptimiserService $wpSeoOptimiserService,
    ): OnboardingDataResponseDto
    {
        $response = new OnboardingDataResponseDto();

        RCLoad::$logRCCalls = true;
        $repeat = 1;
        $onboardingResponse = null;

        try {
            // we want to retry twice the onboarding processing in case of failure
            $wpPluginService->retryOnboardingProcessing($repeat, function () use ($wpRequirementsService, &$onboardingResponse) {
                // save the plugin onboarding data
                $onboardingResponse = $wpRequirementsService->syncRequirementsToRemote();
            });
            $response->setupData = $wpPluginService->finalizeOnboardingState($onboardingResponse);
        } catch (Exception $error) {
            $this->log_json([
                'operation_type' => 'onboarding_processing',
                'operation_status' => 'error',
                'api_calls' => RCApiOperations::getExecutedRCCalls(),
                'context_entity' => 'onboarding',
                'context_id' => null,
                'content_type' => null,
                'execution_time' => null,
                'error_details' => [
                    'exception_message' => $error->getMessage(),
                    'exception_code' => $error->getCode(),
                    'exception_file' => $error->getFile(),
                    'exception_line' => $error->getLine(),
                    'exception_trace' => $error->getTraceAsString()
                ],
                'metadata' => [
                    'retry_count' => $repeat,
                    'onboarding_response' => $onboardingResponse ?? null,
                    'requirements' => $wpRequirementsService->getAllRequirementsValues(false) ?? null
                ]
            ], 'save_onboarding');
            return $this->processException($error, OnboardingDataResponseDto::class);
        }
        
        // Log successful onboarding processing
        $this->log_json([
            'operation_type' => 'onboarding_processing',
            'operation_status' => 'success',
            'api_calls' => RCApiOperations::getExecutedRCCalls(),
            'context_entity' => 'onboarding',
            'context_id' => null,
            'content_type' => null,
            'execution_time' => null,
            'metadata' => [
                'retry_count' => $repeat,
                'onboarding_response' => $onboardingResponse ?? null,
                'setup_data' => $response->setupData ?? null,
                'requirements' => $wpRequirementsService->getAllRequirementsValues(false) ?? null
            ]
        ], 'save_onboarding');
        
        RCLoad::$logRCCalls = false;

        // Consider that in this point the onboarding data was saved successfully.
        // Now we can run the seo optimization process for the most important pages and posts
        try {
            ModuleManager::instance()->initialize()->linkAnalyzer()->hooksComponent->scanAllPosts();
            $wpSeoOptimiserService->runSeoOptimizationForImportantPagesAndPosts();
        } catch (Throwable $e) {
            // nothing to do here
        }


        // Mark onboarding as complete in flow state
        $store = new OptionStore();
        $store->updateFlowState(function($flowState) {
            $flowState->registered = true;
            $flowState->emailVerified = true;
            $flowState->activated = true;
            $flowState->onboarded = true;
            return $flowState;
        });

        // Auto-disable FlowGuard now that onboarding is complete
        OptionStore::disableFlowGuard();

        return $response;
    }

    /**
     * Generate flow steps
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws MappingException
     * @throws BadRequestException
     * @throws NonUniqueResultException
     * @throws \Doctrine\DBAL\Exception
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws ForbiddenException
     * @throws ReflectionException
     * @throws InternalErrorException
     * @throws MissingMappingDriverImplementation
     */
    #[Post('/generateSteps')]
    #[Summary('Generate flow steps')]
    public function generateFlowSteps(
        OnboardingRequestDto $requestDto,
        WPFlowStepsService $wpFlowStepsService,
    ): WPFlowStepsResponseDto
    {
        $response = new WPFlowStepsResponseDto();

        $steps = $wpFlowStepsService->generateSteps(true, true);
        $response->steps = $steps;

        return $response;
    }

    /**
     * Extract onboarding information automatically
     * @param OnboardingRequestDto $requestDto
     * @param WPRequirementsService $wpRequirementsService
     * @param WPFlowDataCompletionsService $wpFlowDataCompletionsService
     * @return WPSetupExtractAutoResponseDto
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\Persistence\Mapping\MappingException
     */
    #[Post('/extractAuto')]
    #[Summary('Extract onboarding information automatically')]
    public function extractAuto(
        OnboardingRequestDto $requestDto,
        WPRequirementsService $wpRequirementsService,
        WPFlowDataCompletionsService $wpFlowDataCompletionsService
    ): WPSetupExtractAutoResponseDto
    {
        $wpRequirementsService->throwErrors = true;
        $wpFlowDataCompletionsService->throwErrors = true;

        $response = new WPSetupExtractAutoResponseDto();

        try {
            $countryCode = get_option(BaseConstants::OPTION_RANKINGCOACH_REGISTER_COUNTRY_CODE);
            if(empty($countryCode)) {
                // Fallback to default country code derived from locale or registered settings
                $defaultCountry = WordpressHelpers::getDefaultCountry();
                $countryCode = key($defaultCountry); // e.g., 'US' or 'FR'
            }

            $response->extracted = $wpRequirementsService->extractRequirementsAuto($countryCode);
            $requirements = (array)$response->extracted->requirements;
            if($requirements) {
                $wpFlowDataCompletionsService->updateRequirements($requirements);
            }
        } catch (InternalErrorException $e) {
            $exceptionDetails = $e->exceptionDetails ?? null;
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new InternalErrorException($e->getMessage(), $exceptionDetails);
        }
        return $response;
    }

    /**
     * Save answer data from a step's question
     * @param WPFlowStepCompletionRequestDto $requestDto
     * @param WPFlowDataCompletionsService $wpFlowDataCompletionsService
     * @param WPFlowStepsService $wpFlowStepsService
     * @return WPFlowStepAndNextStepResponseDto
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     */
    #[Post('/submitStepAnswer')]
    #[Summary('Save completion data from a specific step')]
    public function submitStepAnswer(
        WPFlowStepCompletionRequestDto $requestDto,
        WPFlowDataCompletionsService   $wpFlowDataCompletionsService,
        WPFlowStepsService $wpFlowStepsService
    ): WPFlowStepAndNextStepResponseDto
    {
        $response = new WPFlowStepAndNextStepResponseDto();
        $response->completion = $requestDto->completion;

        try {
            $result = $wpFlowDataCompletionsService->handleAnswerSubmission(
                $requestDto->completion
            );

            $evaluationSucceeded        = $result['evaluationSucceeded'] ?? false;
            $failedAPICallFromResult    = $result['failOrErrorAPICallOnResult'] ?? false;
            $failOrErrorAPICount        = $result['failOrErrorAPICount'] ?? 0;

            // Determine if all steps are completed
            // If the API fails repeatedly (threshold > 1), we force completion to avoid blocking the user
            $allStepsCompleted = $wpFlowStepsService->hasAllStepsCompleted() ?? false;
            if($failOrErrorAPICount > 1 && $failedAPICallFromResult) {
                $allStepsCompleted = true;
            }

            $response->step = $result['step'];
            $response->nextStep = $result['nextStep'] ?? null;
            $response->allStepsCompleted = $allStepsCompleted;
            /* translators: %s is the JSON response data */
            $response->evaluationSucceeded = sprintf(__('The API call (JSON response) is valid: %s', 'beyond-seo'), json_encode($evaluationSucceeded));
            /* translators: %s is the failed API call data */
            $response->failedAPICallFromResult = sprintf(__('The API call (text/html or error property on JSON): %s', 'beyond-seo'), json_encode($failedAPICallFromResult));

        } catch (Exception $e) {
            return $this->processException($e, WPFlowStepAndNextStepResponseDto::class);
        }

        return $response;
    }

    /**
     * Retrieves a step by its unique ID.
     *
     * @param WPFlowPostSaveDataCompletionRequestDto $requestDto
     * @param WPFlowStepsService $wpFlowStepsService
     * @return WPFlowStepResponseDto
     * @throws BadRequestException
     * @throws Exception
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    #[Post('/getStep')]
    #[Summary('Get a step by step id')]
    public function getStepById(
        WPFlowPostSaveDataCompletionRequestDto $requestDto,
        WPFlowStepsService $wpFlowStepsService
    ): WPFlowStepResponseDto
    {
        $response = new WPFlowStepResponseDto();
        $response->step = $wpFlowStepsService->getStepById($requestDto->stepId, true);
        return $response;
    }

    /**
     * Retrieves all prefilled requirements
     *
     * @param OnboardingRequestDto $requestDto
     * @param WPRequirementsService $wpRequirementsService
     * @return WPRequirementsResponseDto
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ReflectionException
     */
    #[Get('/requirements')]
    #[Summary('Get all prefilled requirements')]
    public function getRequirements(
        OnboardingRequestDto $requestDto,
        WPRequirementsService $wpRequirementsService
    ): WPRequirementsResponseDto
    {
        $response = new WPRequirementsResponseDto();
        $response->requirements = $wpRequirementsService->getRequirements();
        return $response;
    }

    /**
     * Create requirement
     *
     * @param WPRequirementPostRequestDto $wpRequirementRequest
     * @param WPRequirementsService $wpRequirementsService
     * @return WPRequirementResponseDto
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ReflectionException
     * @throws \Doctrine\DBAL\Exception|\Doctrine\Persistence\Mapping\MappingException
     */
    #[Post('/requirements')]
    #[Summary('create requirement')]
    public function createRequirement(
        WPRequirementPostRequestDto $wpRequirementRequest,
        WPRequirementsService $wpRequirementsService,
    ): WPRequirementResponseDto {
        $response = new WPRequirementResponseDto();
        /** @var WPRequirement $requirement */
        $requirement = $wpRequirementsService->saveOrUpdateRequirement($wpRequirementRequest->requirement);
        $response->requirement = $requirement;
        return $response;
    }

	/**
	 * Update a requirement
	 *
	 * @param WPRequirementPutRequestDto $requestDto
	 * @param WPRequirementsService $wpRequirementsService
	 *
	 * @return WPRequirementResponseDto
	 * @throws BadRequestException
	 * @throws ForbiddenException
	 * @throws InternalErrorException
	 * @throws InvalidArgumentException
	 * @throws JsonException
	 * @throws MappingException
	 * @throws ReflectionException
	 * @throws \Doctrine\DBAL\Exception
	 * @throws \Doctrine\Persistence\Mapping\MappingException
	 */
    #[Put('/requirements/{requirementId}')]
    #[Summary('Update a requirement')]
    public function updateRequirement(
        WPRequirementPutRequestDto $requestDto,
        WPRequirementsService $wpRequirementsService,
    ): WPRequirementResponseDto {
        $response = new WPRequirementResponseDto();
        /** @var WPRequirement $requirement */
        $requirement = $wpRequirementsService->saveOrUpdateRequirement($requestDto->requirement, $requestDto->requirementId);
        $response->requirement = $requirement;
        return $response;
    }

    /**
     * Search categories
     * @param WPCategoriesGetRequestDto $requestDto
     * @param WPCategoriesService $categoriesService
     * @return WPCategoriesGetResponseDto
     */
    #[Get('/categories')]
    #[Summary('Categories search')]
    public function getCategories(
        WPCategoriesGetRequestDto $requestDto,
        WPCategoriesService $categoriesService
    ): WPCategoriesGetResponseDto {
        $response = new WPCategoriesGetResponseDto();
        $response->categories = $categoriesService->searchCategories($requestDto->search);
        return $response;
    }

    /**
     * Get location suggestions based on address input
     * @param WPLocationSuggestionsGetRequestDto $requestDto
     * @return WPLocationSuggestionsGetResponseDto
     * @throws InternalErrorException
     */
    #[Post('/location/suggestions')]
    #[Summary('Get location suggestions for onboarding')]
    public function getLocationSuggestions(
        WPLocationSuggestionsGetRequestDto $requestDto
    ): WPLocationSuggestionsGetResponseDto {
        $response = new WPLocationSuggestionsGetResponseDto();
        
        try {
            $rcLocationSuggestions = new RCLocationSuggestions();
            $rcLocationSuggestions->address = $requestDto->address;
            $rcLocationSuggestions->countryShortCode = $requestDto->country;
            $rcLocationSuggestions->city = $requestDto->city;
            $rcLocationSuggestions->zip = $requestDto->zip;
            $rcLocationSuggestions->language = $requestDto->language ?? 'en';
            $rcLocationSuggestions->allowAnyLocationType = $requestDto->allowAnyLocationType ?? false;

            RCLoad::$logRCCalls = true;
            $rcLocationSuggestions->rcLoad(false, false);
            $this->log_json([
                'operation_type' => 'location_suggestions',
                'operation_status' => 'success',
                'api_calls' => RCApiOperations::getExecutedRCCalls(),
                'context_entity' => 'location_suggestions',
                'context_id' => null,
                'content_type' => null,
                'execution_time' => null,
                'metadata' => [
                    'address' => $rcLocationSuggestions->address,
                    'countryShortCode' => $rcLocationSuggestions->countryShortCode,
                    'city' => $rcLocationSuggestions->city,
                    'zip' => $rcLocationSuggestions->zip,
                    'language' => $rcLocationSuggestions->language,
                    'allowAnyLocationType' => $rcLocationSuggestions->allowAnyLocationType
                ]
            ], 'get_location_suggestions');
            RCLoad::$logRCCalls = false;
            
            $response->businessLocationMatches = $rcLocationSuggestions->businessLocationMatches;
            $response->success = $rcLocationSuggestions->success;
            $response->message = $rcLocationSuggestions->message;
            
        } catch (Exception $e) {
            return $this->processException($e, WPLocationSuggestionsGetResponseDto::class);
        }
        
        return $response;
    }

}
