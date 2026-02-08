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
use App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser\RCWebPageUrlSafeBrowsingCheck;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use ReflectionException;
use Throwable;

/**
 * Class SafeBrowsingCheckOperation
 *
 * This class is responsible for checking if a webpage is flagged by Google Safe Browsing.
 * It uses external API services to verify safe browsing status and provides insights on security issues.
 */
#[SeoMeta(
    name: 'Safe Browsing Check',
    weight: WeightConfiguration::WEIGHT_SAFE_BROWSING_CHECK_OPERATION,
    description: 'Uses Google`s Safe Browsing API to determine whether a page is flagged for malware or phishing. Reports potential security issues and advises remediation to protect visitors and preserve search engine trust.',
)]
class SafeBrowsingCheckOperation extends Operation implements OperationInterface
{
    /** @var RCWebPageUrlSafeBrowsingCheck|null $safeBrowsingChecker The safe browsing checker object */
    #[HideProperty]
    public ?RCWebPageUrlSafeBrowsingCheck $safeBrowsingChecker = null;

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
        $this->safeBrowsingChecker = new RCWebPageUrlSafeBrowsingCheck();
        parent::__construct($key, $name, $weight);
    }

    /**
     * Performs safe browsing check for the given post-ID.
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
                'status' => [],
                'recommendations' => []
            ];
        }

        // Set up the safe browsing checker
        $this->safeBrowsingChecker->pageUrl = $pageUrl;

        // Call external API to check safe browsing status
        $safeBrowsingResults = $this->checkSafeBrowsing();

        if (empty($safeBrowsingResults)) {
            // Check if the API call is disabled by feature flag
            if (!$this->getFeatureFlag('external_api_call')) {
                return [
                    'success' => false,
                    'message' => __('External API call is disabled by feature flag', 'beyond-seo'),
                    'status' => [
                        'safe' => true, // Default to safe when API is disabled
                        'url' => $pageUrl
                    ],
                    'feature_disabled' => true
                ];
            }

            return [
                'success' => false,
                'message' => __('Failed to fetch safe browsing status from API', 'beyond-seo'),
                'status' => [],
                'recommendations' => []
            ];
        }

        // Check if the URL is safe according to Google Safe Browsing
        $safe = $safeBrowsingResults['isSafeBrowsing'] ?? false;

        return [
            'success' => true,
            'message' => __('Safe browsing status fetched successfully', 'beyond-seo'),
            'status' => [
                'safe' => $safe,
                'url' => $pageUrl
            ],
            'raw_response' => $safeBrowsingResults
        ];
    }

    /**
     * Fetch safe browsing status from external API
     *
     * @return array Safe browsing status data
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    private function checkSafeBrowsing(): array {
        // Check if external API call is enabled via feature flag
        if (!$this->getFeatureFlag('external_api_call')) {
            // Return empty array if external API call is disabled
            return [];
        }

        $rcRepo = new RCWebPageUrlSafeBrowsingCheck();
        $rcRepo->setParent($this);
        $rcRepo->fromEntity($this->safeBrowsingChecker);
        $rcRepo->rcLoad(false, false);

        return $this->safeBrowsingChecker->analyse ?? [];
    }

    /**
     * Evaluate the operation value based on safe browsing status.
     *
     * @return float A score based on the safe browsing status
     */
    public function calculateScore(): float
    {
        // If the feature is disabled, return a neutral score
        if (isset($this->value['feature_disabled']) && $this->value['feature_disabled'] === true) {
            return 0.5; // Return a neutral score when the feature is disabled
        }

        $statusData = $this->value['status'] ?? [];

        if (empty($statusData)) {
            return 0;
        }

        // Check if the page is safe according to Google Safe Browsing
        $safe = $statusData['safe'] ?? false;

        // If safe, return a perfect score, otherwise return 0
        return $safe ? 1.0 : 0.0;
    }

    /**
     * Generate suggestions based on safe browsing analysis
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

        // Get safe browsing data
        $statusData = $this->value['status'] ?? [];

        if (empty($statusData)) {
            return $activeSuggestions;
        }

        // Check if the page is safe
        $safe = $statusData['safe'] ?? false;

        // If not safe, add a suggestion
        if (!$safe) {
            $activeSuggestions[] = Suggestion::SAFE_BROWSING_ISSUE;
        }

        return $activeSuggestions;
    }
}
