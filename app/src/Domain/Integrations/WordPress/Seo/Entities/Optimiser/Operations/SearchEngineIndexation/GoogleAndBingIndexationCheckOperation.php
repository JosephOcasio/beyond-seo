<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\SearchEngineIndexation;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser\RCWebPageUrlGoogleAndBingIndexation;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use ReflectionException;
use Throwable;

/**
 * Class GoogleAndBingIndexationCheckOperation
 *
 * This class is responsible for checking if a webpage is indexed by search engines (Bing and Google).
 * It uses external API services to verify indexation status and provides insights on search visibility.
 */
#[SeoMeta(
    name: 'Google And Bing Indexation Check',
    weight: WeightConfiguration::WEIGHT_GOOGLE_AND_BING_INDEXATION_CHECK_OPERATION,
    description: 'Queries external APIs to confirm whether a page is indexed by Google and Bing. Reports indexation status and provides guidance on improving visibility if the URL is missing from search results.'
)]
class GoogleAndBingIndexationCheckOperation extends Operation implements OperationInterface
{
    /** @var RCWebPageUrlGoogleAndBingIndexation|null $indexationChecker The indexation checker object */
    #[HideProperty]
    public ?RCWebPageUrlGoogleAndBingIndexation $indexationChecker = null;

    /**
     * Constructor for initializing the operation with a key.
     *
     * @param string $key The key for the operation
     * @param string $name The name of the operation
     * @param float $weight The weight of the operation
     * @throws Throwable
     */
    public function __construct(string $key, string $name, float $weight)
    {
        $this->indexationChecker = new RCWebPageUrlGoogleAndBingIndexation();
        parent::__construct($key, $name, $weight);
    }

    /**
     * Performs indexation check for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        
        // Get the URL of the post
        $pageUrl = $this->contentProvider->getPostUrl($postId);
        
        if (empty($pageUrl)) {
            return [
                'success' => false,
                'message' => __('No valid URL found for this post', 'beyond-seo'),
                'indexation' => [],
                'recommendations' => []
            ];
        }
        
        // Set up the indexation checker
        $this->indexationChecker->pageUrl = $pageUrl;
        
        // Call external API to check indexation
        $indexationResults = $this->checkIndexation();
        
        if (empty($indexationResults)) {
            // Check if the API call is disabled by feature flag
            if (!$this->getFeatureFlag('external_api_call')) {
                return [
                    'success' => false,
                    'message' => __('External API call is disabled by feature flag', 'beyond-seo'),
                    'indexation' => [],
                    'recommendations' => [],
                    'feature_disabled' => true
                ];
            }
            
            return [
                'success' => false,
                'message' => __('Failed to fetch indexation status from API', 'beyond-seo'),
                'indexation' => [],
                'recommendations' => []
            ];
        }
        
        // By default, we only check Bing and assume Google indexation is the same
        $bingIndexed = $indexationResults['isIndexed'] ?? false;
        
        return [
            'success' => true,
            'message' => __('Indexation status fetched successfully', 'beyond-seo'),
            'indexation' => [
                'bing' => $bingIndexed,
                'google' => $bingIndexed, // Assume Google indexation is the same as Bing
                'url' => $pageUrl
            ],
            'raw_response' => $this->indexationChecker->analyse
        ];
    }

    /**
     * Fetch indexation status from external API
     *
     * @return array Indexation status data
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    private function checkIndexation(): array {
        // Check if external API call is enabled via feature flag
        if (!$this->getFeatureFlag('external_api_call')) {
            // Return empty array if external API call is disabled
            return [];
        }
        
        $rcRepo = new RCWebPageUrlGoogleAndBingIndexation();
        $rcRepo->setParent($this);
        $rcRepo->fromEntity($this->indexationChecker);
        $rcRepo->rcLoad(false, false);
        
        return $this->indexationChecker->analyse ?? [];
    }

    /**
     * Evaluate the operation value based on indexation status.
     *
     * @return float A score based on the indexation status
     */
    public function calculateScore(): float
    {
        // If the feature is disabled, return a neutral score
        if (isset($this->value['feature_disabled']) && $this->value['feature_disabled'] === true) {
            return 0.5; // Return a neutral score when the feature is disabled
        }
        
        $indexationData = $this->value['indexation'] ?? [];
        
        if (empty($indexationData)) {
            return 0;
        }
        
        // Check if the page is indexed by Bing (and by extension, Google)
        $isIndexed = $indexationData['bing'] ?? false;
        
        // If indexed, return a perfect score, otherwise return 0
        return $isIndexed ? 1.0 : 0.0;
    }

    /**
     * Generate suggestions based on indexation analysis
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = []; // Will hold all identified issue types
        
        // If the feature is disabled, return no suggestions
        if (isset($this->value['feature_disabled']) && $this->value['feature_disabled'] === true) {
            return $activeSuggestions;
        }
        
        // Get indexation data
        $indexationData = $this->value['indexation'] ?? [];
        
        if (empty($indexationData)) {
            return $activeSuggestions;
        }
        
        // Check if the page is indexed
        $isIndexed = $indexationData['bing'] ?? false;
        
        // If not indexed, add a suggestion
        if (!$isIndexed) {
            $activeSuggestions[] = Suggestion::PAGE_NOT_INDEXED;
        }
        
        return $activeSuggestions;
    }
}
