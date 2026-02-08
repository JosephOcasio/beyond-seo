<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\LocalKeywordsInContent;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use RankingCoach\Inc\Core\Base\BaseConstants;

/**
 * Class LocalKeywordMetaTagOptimizationOperation
 *
 * This class is responsible for validating the presence of local keywords
 * in meta-titles, descriptions, and image alt text for local SEO optimization.
 */
#[SeoMeta(
    name: 'Local Keyword Meta Tag Optimization',
    weight: WeightConfiguration::WEIGHT_LOCAL_KEYWORD_META_TAG_OPTIMIZATION_OPERATION,
    description: 'Checks if local keywords appear in meta titles, descriptions, and image alt text to strengthen local SEO signals. Calculates coverage percentages and suggests improvements when location-specific phrases are missing.',
)]
class LocalKeywordMetaTagOptimizationOperation extends Operation implements OperationInterface
{
    // Threshold values for scoring and analysis
    private const TITLE_THRESHOLD = 0.8;
    private const META_DESCRIPTION_THRESHOLD = 0.7;
    private const ALT_TEXT_THRESHOLD = 0.6;
    private const OVERALL_COVERAGE_THRESHOLD = 50; // percentage

    /**
     * Performs local keyword meta-tag analysis for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get post-URL
        $postUrl = $this->contentProvider->getPostUrl($postId);
        if (empty($postUrl)) {
            return [
                'success' => false,
                'message' => __('Could not retrieve post URL', 'beyond-seo')
            ];
        }

        // Get local keywords for analysis
        $localKeywords = $this->getLocalKeywordsForAnalysis($postId);
        if (empty($localKeywords)) {
            return [
                'success' => false,
                'message' => __('No local keywords found for analysis', 'beyond-seo')
            ];
        }

        // Fetch the full rendered HTML content
        $htmlContent = $this->contentProvider->getContent($postId);
        if (empty($htmlContent)) {
            return [
                'success' => false,
                'message' => __('Failed to fetch HTML content from URL', 'beyond-seo')
            ];
        }

        // Parse the HTML and analyze local keyword presence
        $analysisResults = $this->contentProvider->analyzeLocalKeywordsInMetaTags($htmlContent, $localKeywords);

        // Prepare final results
        return [
            'success' => true,
            'message' => __('Local keyword meta tag analysis completed successfully', 'beyond-seo'),
            'post_id' => $postId,
            'post_url' => $postUrl,
            'local_keywords' => $localKeywords,
            'analysis' => $analysisResults
        ];
    }

    /**
     * Get local keywords for the analysis.
     * This method retrieves actual local keywords from post-meta and/or extracts location data.
     *
     * @param int $postId The post-ID
     * @return array Array of local keywords
     */
    private function getLocalKeywordsForAnalysis(int $postId): array
    {
        // Try to get local keywords from post-meta
        $localKeywords = get_post_meta($postId, BaseConstants::META_KEY_LOCAL_KEYWORDS, true);

        if (!empty($localKeywords)) {
            // If stored as JSON, decode it
            if (is_string($localKeywords) && $this->contentProvider->isJson($localKeywords)) {
                return json_decode($localKeywords, true);
            }

            // If stored as a comma-separated string
            if (is_string($localKeywords) && str_contains($localKeywords, ',')) {
                return array_map('trim', explode(',', $localKeywords));
            }

            // If already an array
            if (is_array($localKeywords)) {
                return $localKeywords;
            }

            // Single keyword as a string
            if (is_string($localKeywords) && !empty(trim($localKeywords))) {
                return [trim($localKeywords)];
            }
        }

        // If no specific local keywords found, try to extract location info
        // from primary and secondary keywords
        $allKeywords = [];
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);

        if (!empty($primaryKeyword)) {
            $allKeywords[] = $primaryKeyword;
        }

        if (!empty($secondaryKeywords)) {
            $allKeywords = array_merge($allKeywords, $secondaryKeywords);
        }

        // Extract potential location keywords from all keywords
        $locationKeywords = $this->extractLocationKeywords($allKeywords);

        // If still no keywords, try to get business location from options
        if (empty($locationKeywords)) {
            $businessLocation = $this->getBusinessLocationFromOptions();
            if (!empty($businessLocation)) {
                $locationKeywords[] = $businessLocation;
            }
        }

        return !empty($locationKeywords) ? $locationKeywords : $allKeywords;
    }

    /**
     * Get a business location from WordPress options.
     *
     * @return string Business location or empty string if not found
     */
    private function getBusinessLocationFromOptions(): string
    {
        // Try to get from common option names used by local SEO plugins
        $locationOptions = [
            'rankingcoach_business_city',
            'wpseo_local_business_city',
            'aioseo_location_city',
            'business_city',
            'store_city'
        ];

        foreach ($locationOptions as $option) {
            $location = get_option($option);
            if (!empty($location)) {
                return $location;
            }
        }

        return '';
    }

    /**
     * Extract potential location keywords from a list of keywords.
     * Looks for common location patterns in keywords.
     *
     * @param array $keywords List of keywords
     * @return array Extracted location keywords
     */
    private function extractLocationKeywords(array $keywords): array
    {
        $locationKeywords = [];
        $locationIndicators = ['in', 'near', 'around', 'at', 'serving'];

        foreach ($keywords as $keyword) {
            // Look for phrases like "service in location" or "service near location"
            foreach ($locationIndicators as $indicator) {
                if (stripos($keyword, " $indicator ") !== false) {
                    $parts = explode(" $indicator ", $keyword, 2);
                    if (isset($parts[1]) && !empty(trim($parts[1]))) {
                        $locationKeywords[] = trim($parts[1]);
                    }
                }
            }

            // Look for zip codes (simple pattern for US zip codes)
            if (preg_match('/\b\d{5}(?:-\d{4})?\b/', $keyword, $matches)) {
                $locationKeywords[] = $matches[0];
            }

            // Look for common city+state patterns (e.g., "New York, NY")
            if (preg_match('/([A-Z][a-z]+(?: [A-Z][a-z]+)*),? ([A-Z]{2})/', $keyword, $matches)) {
                $locationKeywords[] = $matches[0];
            }
        }

        return array_unique($locationKeywords);
    }

    /**
     * Calculate a score based on local keyword presence in meta-tags and alt text.
     *
     * @return float A score between 0 and 1
     */
    public function calculateScore(): float
    {
        $analysis = $this->value['analysis'] ?? [];
        $coverage = $analysis['coverage'] ?? [];

        if (empty($coverage)) {
            return 0;
        }

        // Weights for different elements
        $weights = [
            'title' => 0.4,        // Meta-title has the highest weight
            'description' => 0.3,  // Meta-description is next
            'image_alt' => 0.2,    // Image alt text
            'schema' => 0.1        // Schema markup
        ];

        $elementScores = [];

        // Score for meta title
        if (isset($analysis['meta_title']['analysis'])) {
            $titleAnalysis = $analysis['meta_title']['analysis'];
            $elementScores['title'] = $titleAnalysis['has_any_keyword'] ?
                ($titleAnalysis['keywords_found'] / max(1, count($this->value['local_keywords']))) : 0;

            // Title is very important, so we apply a threshold
            if ($elementScores['title'] < self::TITLE_THRESHOLD && $elementScores['title'] > 0) {
                $elementScores['title'] *= 0.7; // Penalty for low keyword coverage in the title
            }
        } else {
            $elementScores['title'] = 0;
        }

        // Score for meta description
        if (isset($analysis['meta_description']['analysis'])) {
            $descAnalysis = $analysis['meta_description']['analysis'];
            $elementScores['description'] = $descAnalysis['has_any_keyword'] ?
                ($descAnalysis['keywords_found'] / max(1, count($this->value['local_keywords']))) : 0;

            // Apply a threshold for description
            if ($elementScores['description'] < self::META_DESCRIPTION_THRESHOLD && $elementScores['description'] > 0) {
                $elementScores['description'] *= 0.8; // Smaller penalty than title
            }
        } else {
            $elementScores['description'] = 0;
        }

        // Score for image alt text
        if (isset($analysis['image_alt']['analysis'])) {
            $imgAnalysis = $analysis['image_alt']['analysis'];

            // If there are no images, don't penalize the score
            if ($imgAnalysis['total_images'] === 0) {
                $elementScores['image_alt'] = 0.5; // Neutral score
            } else {
                $keywordCoverage = $imgAnalysis['keywords_found'] / max(1, count($this->value['local_keywords']));
                $imageCoverage = $imgAnalysis['images_with_keywords'] / max(1, $imgAnalysis['total_images']);

                // We weight keyword coverage higher than image coverage
                $elementScores['image_alt'] = ($keywordCoverage * 0.7) + ($imageCoverage * 0.3);

                // Apply a threshold for alt text
                if ($elementScores['image_alt'] < self::ALT_TEXT_THRESHOLD && $elementScores['image_alt'] > 0) {
                    $elementScores['image_alt'] *= 0.9; // Smallest penalty
                }
            }
        } else {
            $elementScores['image_alt'] = 0;
        }

        // Score for schema markup
        if (isset($analysis['schema_markup']['analysis'])) {
            $schemaAnalysis = $analysis['schema_markup']['analysis'];

            if (!$schemaAnalysis['schema_found']) {
                // No schema found, assign a low but not zero score
                $elementScores['schema'] = 0.2;
            } else {
                $elementScores['schema'] = $schemaAnalysis['has_any_keyword'] ?
                    ($schemaAnalysis['keywords_found'] / max(1, count($this->value['local_keywords']))) : 0.3;
            }
        } else {
            $elementScores['schema'] = 0;
        }

        // Calculate weighted score
        $finalScore = 0;
        foreach ($weights as $element => $weight) {
            $finalScore += ($elementScores[$element] ?? 0) * $weight;
        }

        // Additional bonus for excellent overall coverage
        if (isset($coverage['keyword_coverage_percentage']) &&
            $coverage['keyword_coverage_percentage'] >= 75) {
            $finalScore = min(1, $finalScore * 1.1); // 10% bonus
        }

        return max(0, min(1, $finalScore));
    }

    /**
     * Generate suggestions based on local keyword meta-tag analysis.
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];
        $factorData = $this->value;
        $analysis = $factorData['analysis'] ?? [];
        $localKeywords = $factorData['local_keywords'] ?? [];

        if (empty($analysis) || empty($localKeywords)) {
            return $activeSuggestions;
        }

        // Check if keywords are missing from the meta-title
        if (isset($analysis['meta_title']['analysis']) &&
            !$analysis['meta_title']['analysis']['has_any_keyword']) {
            $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_META_TITLE;
        }

        // Check if keywords are missing from the meta-description
        if (isset($analysis['meta_description']['analysis']) &&
            !$analysis['meta_description']['analysis']['has_any_keyword']) {
            $activeSuggestions[] = Suggestion::MISSING_RELATED_KEYWORDS;
        }

        // Check if keywords are missing from an image alt text
        if (isset($analysis['image_alt']['analysis']) &&
            $analysis['image_alt']['analysis']['total_images'] > 0 &&
            $analysis['image_alt']['analysis']['images_with_keywords'] === 0) {
            $activeSuggestions[] = Suggestion::IMPORTANT_RELATED_TERMS_MISSING;
        }

        // Check overall keyword coverage
        if (isset($analysis['coverage']) &&
            $analysis['coverage']['keyword_coverage_percentage'] < self::OVERALL_COVERAGE_THRESHOLD) {
            $activeSuggestions[] = Suggestion::INSUFFICIENT_SECONDARY_KEYWORDS;
        }

        // Check schema markup
        if (isset($analysis['schema_markup']['analysis'])) {
            if (!$analysis['schema_markup']['analysis']['schema_found']) {
                // No schema markup found
                $activeSuggestions[] = Suggestion::WEAK_TOPICAL_AUTHORITY;
            } elseif (!$analysis['schema_markup']['analysis']['has_any_keyword']) {
                // Schema found but missing local keywords
                $activeSuggestions[] = Suggestion::MISSING_KEYWORD_COVERAGE;
            }
        }

        return $activeSuggestions;
    }
}
