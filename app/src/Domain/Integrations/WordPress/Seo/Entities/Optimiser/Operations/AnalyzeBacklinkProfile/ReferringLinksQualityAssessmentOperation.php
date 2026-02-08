<?php /** @noinspection PhpComplexFunctionInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AnalyzeBacklinkProfile;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use App\Domain\Integrations\WordPress\Seo\Entities\WPSeoBacklinksItems;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser\RCBacklinksCheck;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use Throwable;

/**
 * Class ReferringLinksQualityAssessmentOperation
 *
 * This class is responsible for evaluating the quality of individual backlinks based on factors 
 * like anchor text, page relevance, and link attributes.
 */
#[SeoMeta(
    name: 'Referring Links Quality Assessment',
    weight: WeightConfiguration::WEIGHT_REFERRING_LINKS_QUALITY_ASSESSMENT_OPERATION,
    description: 'Analyzes the quality of referring links to a page, assessing anchor text distribution, link attributes, and domain ratings',
)]
class ReferringLinksQualityAssessmentOperation extends Operation implements OperationInterface
{
    // Thresholds for anchor text quality assessment
    private const MIN_BRANDED_ANCHORS_PERCENT = 10;
    private const GOOD_BRANDED_ANCHORS_PERCENT = 25;
    private const EXCELLENT_BRANDED_ANCHORS_PERCENT = 40;

    private const MIN_KEYWORD_ANCHORS_PERCENT = 5;
    private const GOOD_KEYWORD_ANCHORS_PERCENT = 15;
    private const EXCELLENT_KEYWORD_ANCHORS_PERCENT = 25;

    private const MAX_GENERIC_ANCHORS_PERCENT = 60;
    private const GOOD_GENERIC_ANCHORS_PERCENT = 40;
    private const EXCELLENT_GENERIC_ANCHORS_PERCENT = 30;

    private const MAX_EXACT_MATCH_ANCHORS_PERCENT = 15;
    private const GOOD_EXACT_MATCH_ANCHORS_PERCENT = 10;
    private const EXCELLENT_EXACT_MATCH_ANCHORS_PERCENT = 5;

    private const MIN_CONTEXTUAL_LINKS_PERCENT = 30;
    private const GOOD_CONTEXTUAL_LINKS_PERCENT = 50;
    private const EXCELLENT_CONTEXTUAL_LINKS_PERCENT = 70;

    private const MAX_NOFOLLOW_LINKS_PERCENT = 70;
    private const GOOD_NOFOLLOW_LINKS_PERCENT = 50;
    private const EXCELLENT_NOFOLLOW_LINKS_PERCENT = 30;

    private const MIN_DOMAIN_RATING = 20;
    private const GOOD_DOMAIN_RATING = 40;
    private const EXCELLENT_DOMAIN_RATING = 60;

    /**
     * @var WPSeoBacklinksItems|null $backlinks
     * The backlinks collection object.
     */
    #[HideProperty]
    public ?WPSeoBacklinksItems $backlinks = null;

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
        $this->backlinks = new WPSeoBacklinksItems();
        parent::__construct($key, $name, $weight);
    }

    /**
     * Performs the analysis of individual backlinks for the given post-ID.
     *
     * @return array|null The analysis results or null if the post-ID is invalid.
     * @throws InternalErrorException
     */
    public function run(): ?array
    {
        $postId = $this->postId;

        // Get the URL of the post and site
        $pageUrl = $this->contentProvider->getPostUrl($postId);

        // Get the 'from' parameter from request if available
        $fromUrl = 'google.com';
        if (isset($_REQUEST['from'])) {
            $fromUrl = sanitize_text_field($_REQUEST['from']);
        }

        if (empty($pageUrl)) {
            return [
                'success' => false,
                'message' => __('No valid URL found for this post', 'beyond-seo'),
                'status' => [],
                'recommendations' => []
            ];
        }

        // Fetch backlinks data from API
        $this->analyseBacklinks($pageUrl, $fromUrl);

        // Check if we have valid data
        if (empty($this->backlinks) || count($this->backlinks->getElements()) === 0) {
            // Check if the API call is disabled by feature flag
            if (!$this->getFeatureFlag('external_api_call')) {
                return [
                    'success' => false,
                    'message' => __('External API call is disabled by feature flag', 'beyond-seo'),
                    'status' => [
                        'page_url' => $pageUrl,
                        'from_url' => $fromUrl ?? '',
                        'total_backlinks' => 0,
                        'avg_domain_rating' => 0,
                        'anchor_text_distribution' => [],
                        'branded_anchors_percent' => 0,
                        'keyword_anchors_percent' => 0,
                        'generic_anchors_percent' => 0,
                        'exact_match_anchors_percent' => 0,
                        'contextual_links_percent' => 0,
                        'nofollow_links_percent' => 0,
                        'dofollow_links_percent' => 0,
                        'top_anchor_texts' => []
                    ],
                    'feature_disabled' => true
                ];
            }

            return [
                'success' => false,
                'message' => __('Failed to fetch backlinks data from API', 'beyond-seo'),
                'status' => [],
                'recommendations' => []
            ];
        }

        // Calculate key metrics
        $metrics = $this->calculateBacklinkQualityMetrics($this->backlinks->getElements());

        return [
            'success' => true,
            'message' => __('Referring links quality assessment completed successfully', 'beyond-seo'),
            'status' => [
                'page_url' => $pageUrl,
                'from_url' => $fromUrl ?? '',
                'total_backlinks' => $metrics['total_backlinks'],
                'avg_domain_rating' => $metrics['avg_domain_rating'],
                'anchor_text_distribution' => $metrics['anchor_text_distribution'],
                'branded_anchors_percent' => $metrics['branded_anchors_percent'],
                'keyword_anchors_percent' => $metrics['keyword_anchors_percent'],
                'generic_anchors_percent' => $metrics['generic_anchors_percent'],
                'exact_match_anchors_percent' => $metrics['exact_match_anchors_percent'],
                'contextual_links_percent' => $metrics['contextual_links_percent'],
                'nofollow_links_percent' => $metrics['nofollow_links_percent'],
                'dofollow_links_percent' => $metrics['dofollow_links_percent'],
                'top_anchor_texts' => $metrics['top_anchor_texts']
            ],
            'raw_data' => [
                'backlinks' => $this->backlinks->getElements()
            ]
        ];
    }

    /**
     * Evaluate the operation value based on the backlinks data.
     *
     * @return float The calculated score based on the analysis.
     */
    public function calculateScore(): float
    {
        $statusData = $this->value['status'] ?? [];

        if (empty($statusData)) {
            return 0;
        }

        // Check if the feature is disabled
        if (isset($this->value['feature_disabled']) && $this->value['feature_disabled'] === true) {
            return 0.5; // Return a neutral score when the feature is disabled
        }

        // Initialize component scores
        $domainQualityScore = 0;

        // Calculate anchor text quality score (50% weight)
        $brandedAnchorsPercent = $statusData['branded_anchors_percent'] ?? 0;
        $keywordAnchorsPercent = $statusData['keyword_anchors_percent'] ?? 0;
        $genericAnchorsPercent = $statusData['generic_anchors_percent'] ?? 0;
        $exactMatchAnchorsPercent = $statusData['exact_match_anchors_percent'] ?? 0;

        $brandedScore = 0;
        $keywordScore = 0;
        $genericScore = 0;
        $exactMatchScore = 0;

        // Branded anchors score
        if ($brandedAnchorsPercent >= self::EXCELLENT_BRANDED_ANCHORS_PERCENT) {
            $brandedScore = 1.0;
        } elseif ($brandedAnchorsPercent >= self::GOOD_BRANDED_ANCHORS_PERCENT) {
            $brandedScore = 0.8;
        } elseif ($brandedAnchorsPercent >= self::MIN_BRANDED_ANCHORS_PERCENT) {
            $brandedScore = 0.5;
        }

        // Keyword anchors score
        if ($keywordAnchorsPercent >= self::EXCELLENT_KEYWORD_ANCHORS_PERCENT) {
            $keywordScore = 1.0;
        } elseif ($keywordAnchorsPercent >= self::GOOD_KEYWORD_ANCHORS_PERCENT) {
            $keywordScore = 0.8;
        } elseif ($keywordAnchorsPercent >= self::MIN_KEYWORD_ANCHORS_PERCENT) {
            $keywordScore = 0.5;
        }

        // Generic anchors score (lower is better)
        if ($genericAnchorsPercent <= self::EXCELLENT_GENERIC_ANCHORS_PERCENT) {
            $genericScore = 1.0;
        } elseif ($genericAnchorsPercent <= self::GOOD_GENERIC_ANCHORS_PERCENT) {
            $genericScore = 0.8;
        } elseif ($genericAnchorsPercent <= self::MAX_GENERIC_ANCHORS_PERCENT) {
            $genericScore = 0.5;
        }

        // Exact match anchors score (lower is better for avoiding over-optimization)
        if ($exactMatchAnchorsPercent <= self::EXCELLENT_EXACT_MATCH_ANCHORS_PERCENT) {
            $exactMatchScore = 1.0;
        } elseif ($exactMatchAnchorsPercent <= self::GOOD_EXACT_MATCH_ANCHORS_PERCENT) {
            $exactMatchScore = 0.8;
        } elseif ($exactMatchAnchorsPercent <= self::MAX_EXACT_MATCH_ANCHORS_PERCENT) {
            $exactMatchScore = 0.5;
        }

        $anchorTextScore = ($brandedScore * 0.3) + ($keywordScore * 0.3) + ($genericScore * 0.2) + ($exactMatchScore * 0.2);

        // Calculate link attributes score (30% weight)
        $contextualLinksPercent = $statusData['contextual_links_percent'] ?? 0;
        $nofollowLinksPercent = $statusData['nofollow_links_percent'] ?? 0;

        $contextualScore = 0;
        $nofollowScore = 0;

        // Contextual links score
        if ($contextualLinksPercent >= self::EXCELLENT_CONTEXTUAL_LINKS_PERCENT) {
            $contextualScore = 1.0;
        } elseif ($contextualLinksPercent >= self::GOOD_CONTEXTUAL_LINKS_PERCENT) {
            $contextualScore = 0.8;
        } elseif ($contextualLinksPercent >= self::MIN_CONTEXTUAL_LINKS_PERCENT) {
            $contextualScore = 0.5;
        }

        // Nofollow links score (lower is better, but some nofollow is natural)
        if ($nofollowLinksPercent <= self::EXCELLENT_NOFOLLOW_LINKS_PERCENT) {
            $nofollowScore = 1.0;
        } elseif ($nofollowLinksPercent <= self::GOOD_NOFOLLOW_LINKS_PERCENT) {
            $nofollowScore = 0.8;
        } elseif ($nofollowLinksPercent <= self::MAX_NOFOLLOW_LINKS_PERCENT) {
            $nofollowScore = 0.5;
        }

        $linkAttributesScore = ($contextualScore * 0.6) + ($nofollowScore * 0.4);

        // Calculate domain quality score (20% weight)
        $avgDomainRating = $statusData['avg_domain_rating'] ?? 0;

        if ($avgDomainRating >= self::EXCELLENT_DOMAIN_RATING) {
            $domainQualityScore = 1.0;
        } elseif ($avgDomainRating >= self::GOOD_DOMAIN_RATING) {
            $domainQualityScore = 0.8;
        } elseif ($avgDomainRating >= self::MIN_DOMAIN_RATING) {
            $domainQualityScore = 0.5;
        }

        // Calculate final score with weights
        return ($anchorTextScore * 0.5) + ($linkAttributesScore * 0.3) + ($domainQualityScore * 0.2);
    }

    /**
     * Generate suggestions based on the analysis results.
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        $statusData = $this->value['status'] ?? [];

        if (empty($statusData)) {
            return $activeSuggestions;
        }

        // Check if the feature is disabled
        if (isset($this->value['feature_disabled']) && $this->value['feature_disabled'] === true) {
            return $activeSuggestions; // Return no suggestions when the feature is disabled
        }

        // Check for anchor text distribution issues
        $brandedAnchorsPercent = $statusData['branded_anchors_percent'] ?? 0;
        $keywordAnchorsPercent = $statusData['keyword_anchors_percent'] ?? 0;
        $genericAnchorsPercent = $statusData['generic_anchors_percent'] ?? 0;
        $exactMatchAnchorsPercent = $statusData['exact_match_anchors_percent'] ?? 0;

        // Only add the most important anchor text suggestion
        if ($brandedAnchorsPercent < self::MIN_BRANDED_ANCHORS_PERCENT && 
            $brandedAnchorsPercent < $keywordAnchorsPercent) {
            $activeSuggestions[] = Suggestion::MISSING_BRANDED_ANCHOR_TEXT;
        } elseif ($keywordAnchorsPercent < self::MIN_KEYWORD_ANCHORS_PERCENT) {
            $activeSuggestions[] = Suggestion::MISSING_KEYWORD_RICH_ANCHOR_TEXT;
        } elseif ($exactMatchAnchorsPercent > self::MAX_EXACT_MATCH_ANCHORS_PERCENT) {
            $activeSuggestions[] = Suggestion::OVER_OPTIMIZED_ANCHOR_TEXT;
        } elseif ($genericAnchorsPercent > self::MAX_GENERIC_ANCHORS_PERCENT) {
            $activeSuggestions[] = Suggestion::GENERIC_ANCHOR_TEXT;
        }

        // Check for link attribute issues - only add the most critical
        $contextualLinksPercent = $statusData['contextual_links_percent'] ?? 0;
        $nofollowLinksPercent = $statusData['nofollow_links_percent'] ?? 0;

        if ($contextualLinksPercent < self::MIN_CONTEXTUAL_LINKS_PERCENT) {
            $activeSuggestions[] = Suggestion::MISSING_CONTEXTUAL_BACKLINKS;
        } elseif ($nofollowLinksPercent > self::MAX_NOFOLLOW_LINKS_PERCENT) {
            $activeSuggestions[] = Suggestion::NOFOLLOW_BACKLINKS_OVERUSE;
        }

        // Check for domain quality issues
        $avgDomainRating = $statusData['avg_domain_rating'] ?? 0;

        if ($avgDomainRating < self::MIN_DOMAIN_RATING) {
            $activeSuggestions[] = Suggestion::LOW_QUALITY_BACKLINKS;
        }

        return $activeSuggestions;
    }

    /**
     * Fetch the backlinks data from the API.
     *
     * @param string $pageUrl The specific page URL
     * @param string $fromUrl The source URL for backlinks filtering
     * @return void
     * @throws InternalErrorException
     */
    private function analyseBacklinks(string $pageUrl, string $fromUrl = ''): void {
        // Check if external API call is enabled via feature flag
        if (!$this->getFeatureFlag('external_api_call')) {
            // Return empty array if external API call is disabled
            return;
        }

        // Remove the scheme from the URLs
        $pageUrl = preg_replace('/^https?:\/\//', '', $pageUrl);
        $fromUrl = !empty($fromUrl) ? preg_replace('/^https?:\/\//', '', $fromUrl) : '';

        $rcRepo = new RCBacklinksCheck();
        $rcRepo->url = $pageUrl; // Use the page URL as the main URL to analyze
        $rcRepo->count = 50; // Default to 50 results

        // Set the from parameter if provided
        if (!empty($fromUrl)) {
            $rcRepo->from = $fromUrl;
        }

        $rcRepo->setParent($this);
        $rcRepo->rcLoad(false, false);
    }

    /**
     * Calculate key backlink quality metrics from the backlinks data.
     *
     * @param array $backlinks The backlinks data
     * @return array Calculated metrics
     */
    private function calculateBacklinkQualityMetrics(array $backlinks): array
    {
        $totalBacklinks = count($backlinks);

        if ($totalBacklinks === 0) {
            return [
                'total_backlinks' => 0,
                'avg_domain_rating' => 0,
                'anchor_text_distribution' => [],
                'branded_anchors_percent' => 0,
                'keyword_anchors_percent' => 0,
                'generic_anchors_percent' => 0,
                'exact_match_anchors_percent' => 0,
                'contextual_links_percent' => 0,
                'nofollow_links_percent' => 0,
                'dofollow_links_percent' => 0,
                'top_anchor_texts' => []
            ];
        }

        // Initialize counters and aggregators
        $totalDomainRating = 0;
        $anchorTextCounts = [];
        $brandedAnchorsCount = 0;
        $keywordAnchorsCount = 0;
        $genericAnchorsCount = 0;
        $exactMatchAnchorsCount = 0;
        $contextualLinksCount = 0;
        $nofollowLinksCount = 0;
        $dofollowLinksCount = 0;

        // Extract site name for branded anchor detection
        $siteName = $this->contentProvider->getSiteName();
        $siteNameWords = explode(' ', strtolower($siteName));

        // Extract keywords for keyword anchor detection
        $keywords = $this->contentProvider->getKeywords($this->postId);
        $keywordPhrases = [];
        foreach ($keywords as $keyword) {
            $keywordPhrases[] = strtolower($keyword);
            $keywordWords = explode(' ', strtolower($keyword));
            foreach ($keywordWords as $word) {
                if (strlen($word) > 3) { // Only consider significant words
                    $keywordPhrases[] = $word;
                }
            }
        }

        // Generic anchor text patterns
        $genericAnchorPatterns = [
            'click here', 'read more', 'learn more', 'more info', 'website', 'link',
            'here', 'this', 'source', 'more', 'details', 'view', 'visit', 'check'
        ];

        // Process each backlink
        foreach ($backlinks as $backlink) {
            // Aggregate domain rating
            $totalDomainRating += $backlink->domain_rating;

            // Process anchor text
            $anchorText = strtolower($backlink->anchor);

            // Count anchor text occurrences
            if (!isset($anchorTextCounts[$anchorText])) {
                $anchorTextCounts[$anchorText] = 0;
            }
            $anchorTextCounts[$anchorText]++;

            // Categorize anchor text
            $isBranded = false;
            $isKeyword = false;
            $isGeneric = false;

            // Check if branded
            foreach ($siteNameWords as $brandWord) {
                if (strlen($brandWord) > 2 && str_contains($anchorText, strtolower($brandWord))) {
                    $isBranded = true;
                    $brandedAnchorsCount++;
                    break;
                }
            }

            // Check if keyword-rich (if not already categorized as branded)
            if (!$isBranded) {
                foreach ($keywordPhrases as $phrase) {
                    if (str_contains($anchorText, $phrase)) {
                        $isKeyword = true;
                        $keywordAnchorsCount++;

                        // Check if exact match
                        if (in_array($anchorText, $keywordPhrases)) {
                            $exactMatchAnchorsCount++;
                        }
                        break;
                    }
                }
            }

            // Check if generic (if not already categorized)
            if (!$isBranded && !$isKeyword) {
                foreach ($genericAnchorPatterns as $pattern) {
                    if (str_contains($anchorText, $pattern)) {
                        $isGeneric = true;
                        $genericAnchorsCount++;
                        break;
                    }
                }
            }

            // If not categorized yet, default to generic
            if (!$isBranded && !$isKeyword && !$isGeneric) {
                $genericAnchorsCount++;
            }

            // Process link attributes from API data if available
            if (isset($backlink->rel) && str_contains($backlink->rel, 'nofollow')) {
                $nofollowLinksCount++;
            } else {
                $dofollowLinksCount++;
            }

            // Process link placement from API data if available
            if (isset($backlink->placement) && $backlink->placement === 'content') {
                $contextualLinksCount++;
            }
        }

        // Calculate percentages
        $brandedAnchorsPercent = ($brandedAnchorsCount / $totalBacklinks) * 100;
        $keywordAnchorsPercent = ($keywordAnchorsCount / $totalBacklinks) * 100;
        $genericAnchorsPercent = ($genericAnchorsCount / $totalBacklinks) * 100;
        $exactMatchAnchorsPercent = ($exactMatchAnchorsCount / $totalBacklinks) * 100;
        $contextualLinksPercent = ($contextualLinksCount / $totalBacklinks) * 100;
        $nofollowLinksPercent = ($nofollowLinksCount / $totalBacklinks) * 100;
        $dofollowLinksPercent = ($dofollowLinksCount / $totalBacklinks) * 100;

        // Get top anchor texts
        arsort($anchorTextCounts);
        $topAnchorTexts = array_slice($anchorTextCounts, 0, 10, true);

        return [
            'total_backlinks' => $totalBacklinks,
            'avg_domain_rating' => $totalBacklinks > 0 ? $totalDomainRating / $totalBacklinks : 0,
            'anchor_text_distribution' => [
                'branded' => $brandedAnchorsCount,
                'keyword' => $keywordAnchorsCount,
                'generic' => $genericAnchorsCount,
                'exact_match' => $exactMatchAnchorsCount
            ],
            'branded_anchors_percent' => $brandedAnchorsPercent,
            'keyword_anchors_percent' => $keywordAnchorsPercent,
            'generic_anchors_percent' => $genericAnchorsPercent,
            'exact_match_anchors_percent' => $exactMatchAnchorsPercent,
            'contextual_links_percent' => $contextualLinksPercent,
            'nofollow_links_percent' => $nofollowLinksPercent,
            'dofollow_links_percent' => $dofollowLinksPercent,
            'top_anchor_texts' => $topAnchorTexts
        ];
    }
}
