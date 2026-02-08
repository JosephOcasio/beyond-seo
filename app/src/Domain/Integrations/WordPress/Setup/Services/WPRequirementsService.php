<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Services;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Utils\RCApiOperations;
use App\Domain\Common\Services\WPCategoriesService;
use App\Domain\Integrations\WordPress\Common\Entities\Categories\WPCategories;
use App\Domain\Integrations\WordPress\Common\Entities\Categories\WPCategory;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords\WPKeywords;
use App\Domain\Integrations\WordPress\Setup\Entities\Extracts\WPSetupExtractAuto;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements\WPRequirement;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements\WPRequirements;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\WPFlowRequirements;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Requirements\InternalDBWPRequirement;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\Flows\Requirements\InternalDBWPRequirements;
use App\Domain\Integrations\WordPress\Setup\Repo\RC\Onboarding\RCOnboarding;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Onboarding\Onboarding;
use App\Domain\Integrations\WordPress\Setup\Repo\RC\Onboarding\RCOnboardingExtractAuto;
use App\Infrastructure\Services\AppService;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Services\Service;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Mapping\MappingException;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use ReflectionException;

/**
 * Class WPRequirementsService
 */
class WPRequirementsService extends Service
{
    use RcLoggerTrait;

    public const DEFAULT_ENTITY_CLASS = WPRequirement::class;

    /**
     * Extracts the requirements automatically
     * @param string|null $countryCode
     * @return WPSetupExtractAuto
     * @throws InternalErrorException
     * @throws ReflectionException
     * @throws \Exception
     */
    public function extractRequirementsAuto(?string $countryCode = null): WPSetupExtractAuto
    {
        RCLoad::$logRCCalls = true;

        $object = new WPSetupExtractAuto();
        $object->countryCode = $countryCode;

        // Instantiate the onboarding class
        $onboarding = new \RankingCoach\Inc\Core\AutoSetup\Onboarding\AutoSetupOnboarding();

        // Get the onboarding content
        $content = $onboarding->getOnboardingContent(true);

        // Avoid to call rC API with empty content or null
        if(empty($content)) {
            $this->log_json([
                'operation_type' => 'ai_extraction_auto_onboarding',
                'operation_status' => 'failed',
                'api_calls' => null,
                'context_entity' => 'setup_auto_onboarding',
                'context_id' => null,
                'context_type' => null,
                'execution_time' => null,
                'error_details' => 'Onboarding content is empty',
                'metadata' => $object
            ], 'extract_auto_onboarding');
            throw new InternalErrorException('Insufficient data for extraction');
        }

        $object->content = $content;

        $rcRepo = new RCOnboardingExtractAuto();
        $rcRepo->fromEntity($object);

        RCLoad::$deactivateRCCache = true;
        $rcRepo->rcLoad(false, false, false, false);
        RCLoad::$deactivateRCCache = false;

        /** @var WPSetupExtractAuto $parent */
        $parent = $rcRepo->toEntity();
        $object->requirements = $parent->getRequirements() ?? [];
        $object->prefillAddressRequirement = $parent->getPrefillAddressRequirement() ?? false;

        $this->log_json([
            'operation_type' => 'ai_extraction_auto_onboarding',
            'operation_status' => 'success',
            'api_calls' => RCApiOperations::getExecutedRCCalls(),
            'context_entity' => 'setup_auto_onboarding',
            'context_id' => null,
            'context_type' => null,
            'execution_time' => null,
            'error_details' => null,
            'metadata' => $object
        ], 'extract_auto_onboarding');
        RCLoad::$logRCCalls = false;

        return $object;
    }

    /**
     * Gets the requirements
     * @throws MappingException
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws ReflectionException
     * @throws InternalErrorException
     * @throws JsonException
     */
    public function getRequirements(bool $categoriesName = true): ?WPRequirements
    {
        $requirements = (new InternalDBWPRequirements())->getRequirements(false);
        foreach ($requirements as $requirement) {
            if ($categoriesName && $requirement->setupRequirement === WPFlowRequirements::SETUP_REQUIREMENT_BUSINESS_CATEGORIES && $requirement->value) {
                /** @var WPCategoriesService $categoriesServices */
                $categoriesServices = AppService::instance()->getService(WPCategoriesService::class);
                /** @var ?WPCategories $categories */
                $categories = $categoriesServices->getCategoriesByCategoryId(...json_decode($requirement->value, true, 512, JSON_THROW_ON_ERROR));
                $categoryNames = array_values(
                    array_map(
                        static fn(WPCategory $category) => $category->name,
                        $categories->getElements()
                    )
                );
                $requirement->value = json_encode($categoryNames, JSON_THROW_ON_ERROR);
            }
        }
        return $requirements;
    }

    /**
     * Gets the requirements as array names
     * @return array
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getRequirementsAsArrayNames($overrideExistences = true): array
    {
        $requirements = (new InternalDBWPRequirements())->getRequirements(false);
        $response = [];
        foreach ($requirements as $requirement) {
            if(!$overrideExistences && !empty($requirement->value)) {
                continue;
            }
            $response[] = $requirement->setupRequirement;
        }
        return $response;
    }

    /**
     * Processes the requirements and saves the onboarding data to remote server
     * @return EntitySet|Entity|ValueObject|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function syncRequirementsToRemote(): EntitySet|Entity|ValueObject|null
    {
        $onboarding = new Onboarding();
        $onboarding->requirements = $this->getRequirements(false);;

        $rcRepo = new RCOnboarding();
        $rcRepo->fromEntity($onboarding);

        RCLoad::$deactivateRCCache = true;
        $rcRepo->rcLoad(false, false);
        RCLoad::$deactivateRCCache = false;

        return $rcRepo->toEntity();
    }

    /**
     * @throws MappingException
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws ReflectionException
     * @throws InternalErrorException
     * @throws JsonException
     */
    public function getAllRequirementsValues(bool $onlyWithValue = true): array
    {
        /**
         * We need to get all requirements except the business website URL
         * as this cannot be changed
         */
        $requirements = WPFlowRequirements::allRequirements();
        return $this->getRequirementsByName($requirements, $onlyWithValue);
    }

    /**
     * @throws MappingException
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws ReflectionException
     * @throws InternalErrorException
     * @throws Exception
     */
    public function getRequirement($requirementName): ?WPRequirement
    {
        return (new InternalDBWPRequirement())->findByRequirement($requirementName);
    }

    /**
     * @throws MappingException
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws InternalErrorException
     * @throws JsonException
     */
    public function getRequirementsByName(array $requirementsName, bool $onlyWithValue = true): array
    {
        $requirements = $this->getRequirements();
        $response = [];

        if (!$requirements) {
            return $response;
        }

        foreach ($requirements->getElements() as $requirement) {
            if ($onlyWithValue && !$requirement->value) {
                continue;
            }
            if (in_array($requirement->setupRequirement, $requirementsName, true)) {
                $response[$requirement->setupRequirement] = $requirement->value;
            }
        }
        return $response;
    }

    /**
     * @param WPRequirement $requirement
     * @param null $id
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
    public function saveOrUpdateRequirement(WPRequirement $requirement, $id = null): ?Entity
    {
        if (!WPFlowRequirements::hasRequirement($requirement->setupRequirement)) {
            throw new BadRequestException('Invalid requirement');
        }

        $repo = new InternalDBWPRequirement();
        $dbRequirement = $repo->findByRequirement($requirement->setupRequirement);

        /**
         * We want to make sure that we either update the existing requirement
         * via its ID or the requirement itself
         */
        if ($id || $dbRequirement->id) {
            $requirement->id = $id ?? $dbRequirement->id;
            if($dbRequirement->id) {
                $requirement->entityAlias = $dbRequirement->entityAlias;
            }
        }

        if ($requirement->setupRequirement === WPFlowRequirements::SETUP_REQUIREMENT_BUSINESS_CATEGORIES) {
            /** @var WPCategoriesService $categoriesServices */
            $categoriesServices = AppService::instance()->getService(WPCategoriesService::class);
            /** @var ?WPCategories $categories */
            $categories = $categoriesServices->getCategoriesByName(...json_decode($requirement->value, true, 512, JSON_THROW_ON_ERROR));
            $categoryIds = array_values(
                array_map(
                    static fn(WPCategory $category) => $category->categoryId,
                    $categories->getElements()
                )
            );
            $requirement->value = json_encode($categoryIds, JSON_THROW_ON_ERROR);
        }

        return $repo->update($requirement);
    }
}
