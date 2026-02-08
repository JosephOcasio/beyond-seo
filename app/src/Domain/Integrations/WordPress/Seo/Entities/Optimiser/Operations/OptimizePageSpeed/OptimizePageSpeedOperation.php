<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\OptimizePageSpeed;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser\RCWebPageUrlLoadingSpeedCheck;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use ReflectionException;
use Throwable;

/**
 * Class OptimizePageSpeedOperation
 *
 * This class is responsible for analyzing the webpage loading speed and providing recommendations
 * for optimization.
 */
#[SeoMeta(
    name: 'Optimize Page Speed',
    weight: WeightConfiguration::WEIGHT_OPTIMIZE_PAGE_SPEED_OPERATION,
    description: 'Analyzes page load speed using internal metrics and optional external APIs. Generates a performance score and identifies elements slowing the site down, suggesting caching or optimization techniques to achieve faster loading.',
)]
class OptimizePageSpeedOperation extends Operation implements OperationInterface
{
    // Performance thresholds
    private const GOOD_PERFORMANCE_THRESHOLD = 0.9;
    private const AVERAGE_PERFORMANCE_THRESHOLD = 0.5;

    /** @var RCWebPageUrlLoadingSpeedCheck|null $pageSpeedChecker
     * The page speed checker instance used to analyze the webpage loading speed.
     */
    #[HideProperty]
    public ?RCWebPageUrlLoadingSpeedCheck $pageSpeedChecker = null;

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
        $this->pageSpeedChecker = new RCWebPageUrlLoadingSpeedCheck();
        parent::__construct($key, $name, $weight);
    }

    /**
     * Performs page speed check for the given post-ID.
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
        
        // Check if external API calls are disabled via feature flag
        if (!$this->getFeatureFlag('external_api_call')) {
            return [
                'success' => false,
                'message' => __('Page speed check is disabled by feature flag', 'beyond-seo'),
                'status' => [],
                'recommendations' => []
            ];
        }
        
        // Set up the page speed checker
        $this->pageSpeedChecker->pageUrl = $pageUrl;
        $this->pageSpeedChecker->strategy = RCWebPageUrlLoadingSpeedCheck::STRATEGY_DESKTOP;
        
        // Call external API to check page speed
        $pageSpeedResults = $this->checkPageSpeed();
        
        if (empty($pageSpeedResults)) {
            return [
                'success' => false,
                'message' => __('Failed to fetch page speed data from API', 'beyond-seo'),
                'status' => [],
                'recommendations' => []
            ];
        }
        
        // Extract performance metrics
        $performanceScore = $pageSpeedResults['performanceScore'] ?? 0;
        $loadingTime = $pageSpeedResults['loadingTime'] ?? 0;
        $firstContentfulPaint = $pageSpeedResults['firstContentfulPaint'] ?? 0;
        $largestContentfulPaint = $pageSpeedResults['largestContentfulPaint'] ?? 0;
        $totalBlockingTime = $pageSpeedResults['totalBlockingTime'] ?? 0;
        $cumulativeLayoutShift = $pageSpeedResults['cumulativeLayoutShift'] ?? 0;
        
        return [
            'success' => true,
            'message' => __('Page speed data fetched successfully', 'beyond-seo'),
            'status' => [
                'performanceScore' => $performanceScore,
                'loadingTime' => $loadingTime,
                'firstContentfulPaint' => $firstContentfulPaint,
                'largestContentfulPaint' => $largestContentfulPaint,
                'totalBlockingTime' => $totalBlockingTime,
                'cumulativeLayoutShift' => $cumulativeLayoutShift,
                'url' => $pageUrl
            ],
            'raw_response' => $pageSpeedResults
        ];
    }

    /**
     * Fetch page speed data from external API
     *
     * @return array Page speed data
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    private function checkPageSpeed(): array {
        $rcRepo = new RCWebPageUrlLoadingSpeedCheck();
        $rcRepo->setParent($this);
        $rcRepo->fromEntity($this->pageSpeedChecker);
        $rcRepo->rcLoad(false, false);
        
        return $this->pageSpeedChecker->analyse ?? [];
    }

    /**
     * Evaluate the operation value based on page speed metrics.
     *
     * @return float A score based on the page speed performance
     */
    public function calculateScore(): float
    {
        // If feature flag is enabled to disable API, return a neutral score
        if (!$this->getFeatureFlag('external_api_call')) {
            return 0.5;
        }
        
        $statusData = $this->value['status'] ?? [];
        
        if (empty($statusData)) {
            return 0;
        }
        
        // Get the performance score from the API response
        $performanceScore = $statusData['performanceScore'] ?? 0;
        
        // Apply a more nuanced scoring based on thresholds
        if ($performanceScore >= self::GOOD_PERFORMANCE_THRESHOLD) {
            // Good performance - full score
            return 1.0;
        } elseif ($performanceScore >= self::AVERAGE_PERFORMANCE_THRESHOLD) {
            // Average performance - scaled score between 0.5 and 0.9
            return 0.5 + (($performanceScore - self::AVERAGE_PERFORMANCE_THRESHOLD) / 
                         (self::GOOD_PERFORMANCE_THRESHOLD - self::AVERAGE_PERFORMANCE_THRESHOLD) * 0.4);
        } else {
            // Poor performance - scaled score between 0 and 0.5
            return ($performanceScore / self::AVERAGE_PERFORMANCE_THRESHOLD) * 0.5;
        }
    }

    /**
     * Generate suggestions based on page speed analysis
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        // If feature flag is enabled to disable API, return no suggestions
        if (!$this->getFeatureFlag('external_api_call')) {
            return [];
        }
        
        $activeSuggestions = []; // Will hold all identified issue types
        
        // Get page speed data
        $statusData = $this->value['status'] ?? [];
        $rawResponse = $this->value['raw_response'] ?? [];
        
        if (empty($statusData)) {
            return $activeSuggestions;
        }
        
        // Get performance metrics
        $performanceScore = $statusData['performanceScore'] ?? 0;
        $loadingTime = $statusData['loadingTime'] ?? 0;
        $firstContentfulPaint = $statusData['firstContentfulPaint'] ?? 0;
        $largestContentfulPaint = $statusData['largestContentfulPaint'] ?? 0;
        $totalBlockingTime = $statusData['totalBlockingTime'] ?? 0;
        $cumulativeLayoutShift = $statusData['cumulativeLayoutShift'] ?? 0;
        
        // Check overall performance based on thresholds
        if ($performanceScore < self::AVERAGE_PERFORMANCE_THRESHOLD) {
            // Critical performance issue - high priority suggestion
            $activeSuggestions[] = Suggestion::PAGE_SPEED_OVERALL_SLOW;
        } elseif ($performanceScore < self::GOOD_PERFORMANCE_THRESHOLD) {
            // Moderate performance issue - medium priority suggestion
            $activeSuggestions[] = Suggestion::PAGE_SPEED_OVERALL_SLOW;
        }
        
        // Check specific metrics and add relevant suggestions
        if ($loadingTime > 3000) { // 3 seconds threshold
            $activeSuggestions[] = Suggestion::PAGE_SPEED_SLOW_LOADING;
        }
        
        if ($firstContentfulPaint > 1800) { // 1.8 seconds threshold
            $activeSuggestions[] = Suggestion::PAGE_SPEED_SLOW_FCP;
        }
        
        if ($largestContentfulPaint > 2500) { // 2.5 seconds threshold
            $activeSuggestions[] = Suggestion::PAGE_SPEED_SLOW_LCP;
        }
        
        if ($totalBlockingTime > 300) { // 300ms threshold
            $activeSuggestions[] = Suggestion::PAGE_SPEED_HIGH_BLOCKING_TIME;
        }
        
        if ($cumulativeLayoutShift > 0.1) { // 0.1 threshold
            $activeSuggestions[] = Suggestion::PAGE_SPEED_HIGH_CLS;
        }
        
        // Check for specific issues from raw response
        $hasLargeImages = $rawResponse['hasLargeImages'] ?? false;
        $hasUnoptimizedImages = $rawResponse['hasUnoptimizedImages'] ?? false;
        $hasRenderBlockingResources = $rawResponse['hasRenderBlockingResources'] ?? false;
        $hasUnminifiedResources = $rawResponse['hasUnminifiedResources'] ?? false;
        $hasBrowserCachingIssues = $rawResponse['hasBrowserCachingIssues'] ?? false;
        
        if ($hasLargeImages) {
            $activeSuggestions[] = Suggestion::PAGE_SPEED_LARGE_IMAGES;
        }
        
        if ($hasUnoptimizedImages) {
            $activeSuggestions[] = Suggestion::PAGE_SPEED_UNOPTIMIZED_IMAGES;
        }
        
        if ($hasRenderBlockingResources) {
            $activeSuggestions[] = Suggestion::PAGE_SPEED_RENDER_BLOCKING;
        }
        
        if ($hasUnminifiedResources) {
            $activeSuggestions[] = Suggestion::PAGE_SPEED_UNMINIFIED_RESOURCES;
        }
        
        if ($hasBrowserCachingIssues) {
            $activeSuggestions[] = Suggestion::PAGE_SPEED_BROWSER_CACHING;
        }
        
        return $activeSuggestions;
    }
}
