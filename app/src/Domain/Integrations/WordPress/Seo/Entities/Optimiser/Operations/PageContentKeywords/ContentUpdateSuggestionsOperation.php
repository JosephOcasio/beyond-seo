<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\PageContentKeywords;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser\RCContentUpdateSuggestions;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use ReflectionException;
use Throwable;

/**
 * Class ContentUpdateSuggestionsOperation
 *
 * This operation analyzes content for outdated information, content freshness issues,
 * and opportunities for improvements based on industry changes and competitive advantage.
 * It provides actionable recommendations for updating or pruning content.
 */
#[SeoMeta(
    name: 'Content Update Suggestions',
    weight: WeightConfiguration::WEIGHT_CONTENT_UPDATE_SUGGESTIONS_OPERATION,
    description: 'Analyzes content age and relevance to suggest updates or pruning. Reviews industry trends and competitor changes, generating actionable recommendations for refreshing outdated sections or removing obsolete information to maintain quality.',
)]
class ContentUpdateSuggestionsOperation extends Operation implements OperationInterface
{
    /** @var RCContentUpdateSuggestions|null $contentAnalysis The content analysis object */
    #[HideProperty]
    public ?RCContentUpdateSuggestions $contentAnalysis = null;

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
        $this->contentAnalysis = new RCContentUpdateSuggestions();
        parent::__construct($key, $name, $weight);
    }

    /**
     * Performs content update analysis for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get domain URL and post URL
        $postUrl = $this->contentProvider->getPostUrl($postId);
        
        // Get locale and language information
        $locale = $this->contentProvider->getLocale();
        $language = $this->contentProvider->getLanguageFromLocale($locale);
        
        // Get content category
        $contentCategory = $this->contentProvider->getFirstCategoryName($postId);

        // Get the primary keyword
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);

        // Get publication date and last updated date
        $post = get_post($postId);
        $publicationDate = $post ? $post->post_date : '';
        $lastUpdated = $post ? $post->post_modified : '';

        // Get the full content of the page
        $fullContent = $this->contentProvider->getContent($postId);
        
        if (empty($fullContent)) {
            return [
                'success' => false,
                'message' => __('Failed to extract content from the page', 'beyond-seo'),
                'recommendations' => []
            ];
        }

        // Set up request parameters
        $this->contentAnalysis->fullContent = $fullContent;
        $this->contentAnalysis->domainUrl = $postUrl;
        $this->contentAnalysis->contentCategory = $contentCategory;
        $this->contentAnalysis->primaryKeyword = $primaryKeyword;
        $this->contentAnalysis->publicationDate = $publicationDate;
        $this->contentAnalysis->lastUpdated = $lastUpdated;
        $this->contentAnalysis->locale = $locale;
        $this->contentAnalysis->language = $language;

        // Call external API to get content update suggestions
        $recommendations = $this->fetchContentUpdateSuggestions();
        
        if (empty($recommendations)) {
            // Check if the API call is disabled by feature flag
            if (!$this->getFeatureFlag('external_api_call')) {
                return [
                    'success' => false,
                    'message' => __('External API call is disabled by feature flag', 'beyond-seo'),
                    'recommendations' => [],
                    'feature_disabled' => true
                ];
            }
            
            return [
                'success' => false,
                'message' => __('Failed to fetch content update suggestions from API', 'beyond-seo'),
                'recommendations' => []
            ];
        }

        return [
            'success' => true,
            'message' => __('Content update suggestions fetched successfully', 'beyond-seo'),
            'post_data' => [
                'url' => $postUrl,
                'publication_date' => $publicationDate,
                'last_updated' => $lastUpdated,
                'category' => $contentCategory
            ],
            'recommendations' => $recommendations
        ];
    }

    /**
     * Fetch content update suggestions from external API
     *
     * @return array Content update suggestions
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    private function fetchContentUpdateSuggestions(): array
    {
        // Check if external API call is enabled via feature flag
        if (!$this->getFeatureFlag('external_api_call')) {
            // Return empty array if external API call is disabled
            return [];
        }
        
        $rcRepo = new RCContentUpdateSuggestions();
        $rcRepo->setParent($this);
        $rcRepo->fromEntity($this->contentAnalysis);
        $rcRepo->rcLoad(false, false);
        
        return $this->contentAnalysis->recommendations ?? [];
    }

    /**
     * Calculate the score based on the content update recommendations.
     *
     * @return float A score based on the content freshness and quality
     */
    public function calculateScore(): float
    {
        // If the feature is disabled, return a neutral score
        if (isset($this->value['feature_disabled']) && $this->value['feature_disabled'] === true) {
            return 0.5; // Return a neutral score when the feature is disabled
        }
        
        $recommendations = $this->value['recommendations'];
        if($recommendations && (is_object($recommendations) || is_array($recommendations))) {
            $recommendations = json_decode(json_encode($recommendations), true);
        }
        
        // No recommendations available
        if (empty($recommendations)) {
            return 1.0; // Perfect score if no issues found
        }
        
        // Calculate weighted average of category scores
        $totalScore = 0;
        $weightSum = 0;
        
        $categoryWeights = [
            'contentFreshness' => 0.3,
            'industryChanges' => 0.25,
            'contentExpansion' => 0.2,
            'competitiveAdvantage' => 0.25
        ];
        
        foreach ($recommendations as $category => $data) {
            // cast to array to avoid issues with stdClass
            if ($data && (is_object($data) || is_array($data))) {
                $data = json_decode(json_encode($data), true);
            }
            if (isset($data['score'], $categoryWeights[$category])) {
                $categoryScore = $data['score'] / 100; // Convert to 0-1 scale
                $weight = $categoryWeights[$category];
                
                $totalScore += ($categoryScore * $weight);
                $weightSum += $weight;
            }
        }
        
        // Handle case where no valid categories were found
        if ($weightSum === 0) {
            return 0.5; // Default middle score
        }
        
        return $totalScore / $weightSum;
    }

    /**
     * Generate suggestions based on content update analysis
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        // If the feature is disabled, return no suggestions
        if (isset($this->value['feature_disabled']) && $this->value['feature_disabled'] === true) {
            return $activeSuggestions;
        }
        
        // Get factor data for this operation
        $factorData = $this->value;

        // If no data or analysis failed, return empty suggestions
        if (empty($factorData) || !isset($factorData['recommendations']) || $factorData['success'] === false) {
            return $activeSuggestions;
        }

        $postId = $this->postId;
        if(!$postId) {
            return $activeSuggestions;
        }

        $recommendations = $factorData['recommendations'];
        // Cast to array to avoid issues with stdClass
        if ($recommendations && (is_object($recommendations) || is_array($recommendations))) {
            $recommendations = json_decode(json_encode($recommendations), true);
        }
        
        // Check for content freshness issues
        if (isset($recommendations['contentFreshness']) && $recommendations['contentFreshness']['score'] < 70) {
            $activeSuggestions[] = Suggestion::OUTDATED_CONTENT;
        }
        
        // Check for industry changes adaptations needed
        if (isset($recommendations['industryChanges']) && $recommendations['industryChanges']['score'] < 65) {
            $activeSuggestions[] = Suggestion::INDUSTRY_CHANGES;
        }
        
        // Check for content expansion needs
        if (isset($recommendations['contentExpansion']) && $recommendations['contentExpansion']['score'] < 60) {
            $activeSuggestions[] = Suggestion::CONTENT_EXPANSION;
        }
        
        // Check for competitive advantage issues
        if (isset($recommendations['competitiveAdvantage']) && $recommendations['competitiveAdvantage']['score'] < 65) {
            $activeSuggestions[] = Suggestion::COMPETITIVE_ADVANTAGE;
        }
        
        // Check if content should be considered for pruning
        // This is a more complex decision based on multiple factors
        $pruningCandidate = $this->isPruningCandidate($recommendations, $factorData['post_data'] ?? []);
        if ($pruningCandidate) {
            $activeSuggestions[] = Suggestion::PRUNING_CANDIDATE;
        }

        return $activeSuggestions;
    }
    
    /**
     * Determine if content is a candidate for pruning
     * 
     * @param array $recommendations The API recommendations
     * @param array $postData Post metadata
     * @return bool Whether the content is a pruning candidate
     */
    private function isPruningCandidate(array $recommendations, array $postData): bool
    {
        // Content is old (more than 2 years old) and has very low scores
        $isOld = false;
        
        // Check content age
        if (!empty($postData['last_updated'])) {
            $lastUpdated = strtotime($postData['last_updated']);
            $twoYearsAgo = strtotime('-2 years');
            $isOld = ($lastUpdated < $twoYearsAgo);
        }
        
        // Check if multiple scores are very low
        $lowScoreCount = 0;
        $categories = ['contentFreshness', 'industryChanges', 'competitiveAdvantage'];
        foreach ($categories as $category) {
            if (isset($recommendations[$category]['score']) && $recommendations[$category]['score'] < 50) {
                $lowScoreCount++;
            }
        }
        
        $hasLowScores = ($lowScoreCount >= 2) ?? false;
        
        // Consider as a pruning candidate if both conditions are met
        return ($isOld && $hasLowScores);
    }
}
