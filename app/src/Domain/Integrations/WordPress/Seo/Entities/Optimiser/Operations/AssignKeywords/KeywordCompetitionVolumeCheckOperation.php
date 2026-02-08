<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AssignKeywords;

use App\Domain\Common\Entities\Keywords\Keyword;
use App\Domain\Common\Entities\Keywords\Keywords;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser\RCKeywordCompetitionMetrics;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use ReflectionException;
use Throwable;

/**
 * Class KeywordCompetitionVolumeCheckOperation
 *
 * This class is responsible for checking keyword competition, search volume, and CPC balance
 * using external SEO API services to provide insights on keyword difficulty and potential.
 */
#[SeoMeta(
    name: 'Keyword Competition Volume Check',
    weight: WeightConfiguration::WEIGHT_KEYWORD_COMPETITION_VOLUME_CHECK_OPERATION,
    description: 'Analyzes keyword competition, search volume, and CPC balance using external SEO API services. Provides insights on keyword difficulty and potential for content optimization.',
)]
class KeywordCompetitionVolumeCheckOperation extends Operation implements OperationInterface
{
    /** @var RCKeywordCompetitionMetrics|null $metrics The metrics object for keyword competition */
    #[HideProperty]
    public ?RCKeywordCompetitionMetrics $metrics = null;

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
        $this->metrics = new RCKeywordCompetitionMetrics();
        parent::__construct($key, $name, $weight);
    }

    /**
     * Performs keyword competition and volume check for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get keywords for this post
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);
        
        if (empty($primaryKeyword) && empty($secondaryKeywords)) {
            return [
                'success' => false,
                'message' => __('No keywords found for this post', 'beyond-seo'),
                'metrics' => [],
                'recommendations' => []
            ];
        }
        
        // Get domain URL and locale information using the content provider
        $domainUrl = $this->contentProvider->getSiteUrl();
        $locale = $this->contentProvider->getLocale();
        $language = $this->contentProvider->getLanguageFromLocale($locale);
        
        // Get a content category using the content provider
        $contentCategory = $this->contentProvider->getFirstCategoryName($postId);

        // Prepare keywords for an API request
        $keywords = array_merge([$primaryKeyword], $secondaryKeywords);
        $keywords = array_filter($keywords); // Remove empty values

        $keywordsObj = new Keywords();
        foreach ($keywords as $keyword) {
            $keywordObj = new Keyword();
            $keywordObj->name = $keyword;
            $keywordsObj->add($keywordObj);
        }

        $this->metrics->keywords = $keywordsObj;
        $this->metrics->domainUrl = $domainUrl;
        $this->metrics->contentCategory = $contentCategory;
        $this->metrics->locale = $locale;
        $this->metrics->language = $language;
        $this->metrics->primary_keyword = $primaryKeyword;
        $this->metrics->secondary_keywords = $secondaryKeywords;

        // Call external API to get keyword metrics
        $metrics = $this->fetchKeywordMetrics();
        
        if (empty($metrics)) {
            // Check if the API call is disabled by feature flag
            if (!$this->getFeatureFlag('external_api_call')) {
                return [
                    'success' => false,
                    'message' => 'External API call is disabled by feature flag',
                    'metrics' => [],
                    'recommendations' => [],
                    'feature_disabled' => true
                ];
            }
            
            return [
                'success' => false,
                'message' => __('Failed to fetch keyword metrics from API', 'beyond-seo'),
                'metrics' => [],
                'recommendations' => []
            ];
        }
        
        // Separate metrics for primary and secondary keywords
        $primaryKeywordMetrics = [];
        $secondaryKeywordsMetrics = [];
        
        foreach ($metrics as $keyword => $metric) {
            if ($keyword === $primaryKeyword) {
                $primaryKeywordMetrics = $metric;
            } else {
                $secondaryKeywordsMetrics[$keyword] = $metric;
            }
        }

        return [
            'success' => true,
            'message' => __('Keyword metrics fetched successfully', 'beyond-seo'),
            'metrics' => [
                'primary_keyword' => [
                    'keyword' => $primaryKeyword,
                    'metrics' => $primaryKeywordMetrics
                ],
                'secondary_keywords' => array_map(static function ($keyword, $metric) {
                    return [
                        'keyword' => $keyword,
                        'metrics' => $metric
                    ];
                }, array_keys($secondaryKeywordsMetrics), array_values($secondaryKeywordsMetrics)),
                'raw_response' => $this->metrics->raw_response
            ],
            'ideal_ranges' => [
                'difficulty' => [
                    'easy' => '0-' . SeoOptimiserConfig::DIFFICULTY_THRESHOLDS['easy'],
                    'moderate' => SeoOptimiserConfig::DIFFICULTY_THRESHOLDS['easy'] . '-' . SeoOptimiserConfig::DIFFICULTY_THRESHOLDS['moderate'],
                    'hard' => SeoOptimiserConfig::DIFFICULTY_THRESHOLDS['moderate'] . '+'
                ],
                'volume' => [
                    'low' => '0-' . SeoOptimiserConfig::VOLUME_THRESHOLDS['low'],
                    'medium' => SeoOptimiserConfig::VOLUME_THRESHOLDS['low'] . '-' . SeoOptimiserConfig::VOLUME_THRESHOLDS['medium'],
                    'high' => SeoOptimiserConfig::VOLUME_THRESHOLDS['medium'] . '+'
                ],
                'cpc' => [
                    'low' => '0-' . SeoOptimiserConfig::CPC_THRESHOLDS['low'],
                    'medium' => SeoOptimiserConfig::CPC_THRESHOLDS['low'] . '-' . SeoOptimiserConfig::CPC_THRESHOLDS['medium'],
                    'high' => SeoOptimiserConfig::CPC_THRESHOLDS['medium'] . '+'
                ]
            ]
        ];
    }

    /**
     * Fetch keyword metrics from external API
     *
     * @return array Keyword metrics data
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    private function fetchKeywordMetrics(): array {
        // Check if external API call is enabled via feature flag
        if (!$this->getFeatureFlag('external_api_call')) {
            // Return empty array if external API call is disabled
            return [];
        }

        $rcRepo = new RCKeywordCompetitionMetrics();
        $rcRepo->setParent($this);
        $rcRepo->fromEntity($this->metrics);
        $rcRepo->rcLoad(false, false);
        
        return $this->metrics->keyword_metrics;
    }

    /**
     * Evaluate the operation value based on keyword competition and volume metrics.
     *
     * @return float A score based on the keyword metrics
     */
    public function calculateScore(): float
    {
        // If the feature is disabled, return a neutral score
        if (isset($this->value['feature_disabled']) && $this->value['feature_disabled'] === true) {
            return 0.5; // Return a neutral score when the feature is disabled
        }
        
        $primaryKeywordData = $this->value['metrics']['primary_keyword'] ?? null;
        $secondaryKeywordsData = $this->value['metrics']['secondary_keywords'] ?? [];
        
        if (empty($primaryKeywordData)) {
            return 0;
        }
        
        // Score primary keyword (70% of total score)
        $primaryKeywordScore = $this->scoreKeywordMetrics((array)$primaryKeywordData['metrics'] ?? []);
        
        // Score secondary keywords (30% of total score)
        $secondaryKeywordsScore = 0;
        $secondaryCount = count($secondaryKeywordsData);
        
        if ($secondaryCount > 0) {
            $secondaryScores = array_map(function($keywordData) {
                return $this->scoreKeywordMetrics((array)$keywordData['metrics'] ?? []);
            }, $secondaryKeywordsData);
            
            $secondaryKeywordsScore = array_sum($secondaryScores) / max(1, $secondaryCount);
        }
        
        // Calculate the final score (weighted average)
        return ($primaryKeywordScore * 0.7) + ($secondaryKeywordsScore * 0.3);
    }
    
    /**
     * Score individual keyword metrics
     * 
     * @param array $metrics Keyword metrics
     * @return float Score between 0 and 1
     */
    private function scoreKeywordMetrics(array $metrics): float {
        if (empty($metrics)) {
            return 0;
        }
        
        // Start with a base score
        $score = 0.5;
        
        // Difficulty score (lower is better, but not too low)
        if (isset($metrics['difficulty'])) {
            $difficulty = (float) $metrics['difficulty'];
            
            if ($difficulty < SeoOptimiserConfig::DIFFICULTY_THRESHOLDS['easy']) {
                // Easy keywords - very good
                $score += 0.15;
            } elseif ($difficulty < SeoOptimiserConfig::DIFFICULTY_THRESHOLDS['moderate']) {
                // Moderate keywords - good
                $score += 0.1;
            } elseif ($difficulty < 80) {
                // Harder but still reasonable
                $score += 0.05;
            } else {
                // Very hard keywords
                $score -= 0.1;
            }
        }
        
        // Volume score (higher is better, but not if no search volume)
        if (isset($metrics['volume'])) {
            $volume = (int) $metrics['volume'];
            
            if ($volume < 10) {
                // Almost no search volume
                $score -= 0.2;
            } elseif ($volume < SeoOptimiserConfig::VOLUME_THRESHOLDS['low']) {
                // Low volume
                $score -= 0.05;
            } elseif ($volume < SeoOptimiserConfig::VOLUME_THRESHOLDS['medium']) {
                // Medium volume - good
                $score += 0.1;
            } else {
                // High volume - very good
                $score += 0.15;
            }
        }
        
        // CPC score (indicates commercial value)
        if (isset($metrics['cpc'])) {
            $cpc = (float) $metrics['cpc'];
            
            if ($cpc < 0.1) {
                // Almost no commercial value
                $score -= 0.05;
            } elseif ($cpc > SeoOptimiserConfig::CPC_THRESHOLDS['medium']) {
                // High commercial value
                $score += 0.15;
            } elseif ($cpc > SeoOptimiserConfig::CPC_THRESHOLDS['low']) {
                // Medium commercial value
                $score += 0.1;
            }
        }
        
        // Opportunity score (volume/difficulty ratio)
        if (isset($metrics['difficulty'], $metrics['volume'])) {
            $difficulty = max(1, (float) $metrics['difficulty']);
            $volume = (int) $metrics['volume'];
            
            $opportunityRatio = $volume / $difficulty;
            
            if ($opportunityRatio > 100) {
                // Excellent opportunity
                $score += 0.2;
            } elseif ($opportunityRatio > 50) {
                // Very good opportunity
                $score += 0.15;
            } elseif ($opportunityRatio > 20) {
                // Good opportunity
                $score += 0.1;
            } elseif ($opportunityRatio < 5) {
                // Poor opportunity
                $score -= 0.1;
            }
        }
        
        // Ensure the score is between 0 and 1
        return max(0, min(1, $score));
    }

    /**
     * Generate suggestions based on keyword competition and volume analysis
     *
     * @return array Active suggestions based on identified issues
     * @noinspection PhpFunctionCyclomaticComplexityInspection
     */
    public function suggestions(): array
    {
        $activeSuggestions = []; // Will hold all identified issue types

        // Get factor data for this operation
        $factorData = $this->value;
        
        // If the feature is disabled, return no suggestions
        if (isset($factorData['feature_disabled']) && $factorData['feature_disabled'] === true) {
            return $activeSuggestions;
        }

        // Get primary keyword metrics
        $primaryKeywordData = $factorData['metrics']['primary_keyword'] ?? null;
        $secondaryKeywordsData = $factorData['metrics']['secondary_keywords'] ?? [];

        if (empty($primaryKeywordData) || empty($primaryKeywordData['metrics'])) {
            return $activeSuggestions;
        }

        $primaryMetrics = (array)$primaryKeywordData['metrics'];
        $primaryKeyword = $primaryKeywordData['keyword'] ?? '';

        // Check for high keyword difficulty
        if (isset($primaryMetrics['difficulty'])) {
            $difficulty = (float)$primaryMetrics['difficulty'];

            if ($difficulty > SeoOptimiserConfig::DIFFICULTY_THRESHOLDS['moderate']) {
                $activeSuggestions[] = Suggestion::HIGH_KEYWORD_DIFFICULTY;
            }
        }

        // Check for poor volume-competition balance
        if (isset($primaryMetrics['difficulty']) && isset($primaryMetrics['volume'])) {
            $difficulty = (float)$primaryMetrics['difficulty'];
            $volume = (int)$primaryMetrics['volume'];

            // Calculate opportunity score
            $opportunityScore = $difficulty > 0 ? $volume / $difficulty : 0;

            if ($opportunityScore < 10) {
                $activeSuggestions[] = Suggestion::POOR_VOLUME_COMPETITION_BALANCE;
            }
        }

        // Check for commercial intent
        if (isset($primaryMetrics['cpc'])) {
            $cpc = (float)$primaryMetrics['cpc'];

            if ($cpc < SeoOptimiserConfig::CPC_THRESHOLDS['low']) {
                $activeSuggestions[] = Suggestion::LOW_COMMERCIAL_INTENT;
            }
        }

        // Check for keyword portfolio diversity
        $hasEfficientSecondaryKeywords = false;
        $secondaryCount = count($secondaryKeywordsData);

        if ($secondaryCount < 2) {
            $activeSuggestions[] = Suggestion::NARROW_KEYWORD_PORTFOLIO;
        } else {
            // Check if secondary keywords are effective
            $goodSecondaryKeywords = 0;

            foreach ($secondaryKeywordsData as $secondaryKeyword) {
                $metrics = (array)($secondaryKeyword['metrics'] ?? []);

                if (isset($metrics['difficulty'], $metrics['volume'])) {
                    $difficulty = (float)$metrics['difficulty'];
                    $volume = (int)$metrics['volume'];

                    if ($difficulty < SeoOptimiserConfig::DIFFICULTY_THRESHOLDS['moderate'] && $volume > 10) {
                        $goodSecondaryKeywords++;
                    }
                }
            }

            if ($goodSecondaryKeywords < max(1, $secondaryCount / 2)) {
                $activeSuggestions[] = Suggestion::NARROW_KEYWORD_PORTFOLIO;
            } else {
                $hasEfficientSecondaryKeywords = true;
            }
        }

        // Check if long-tail keywords are needed for highly competitive keywords
        if (isset($primaryMetrics['difficulty'])) {
            $difficulty = (float)$primaryMetrics['difficulty'];

            // Count words in the primary keyword
            $wordCount = str_word_count($primaryKeyword);

            // If the primary keyword is challenging and not already long-tail
            if ($difficulty > SeoOptimiserConfig::DIFFICULTY_THRESHOLDS['moderate'] && $wordCount < 3 && !$hasEfficientSecondaryKeywords) {
                $activeSuggestions[] = Suggestion::MISSING_LONG_TAIL_KEYWORDS;
            }
        }

        return $activeSuggestions;
    }
}

