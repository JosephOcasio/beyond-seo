<?php /** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\ContentQualityAndLength;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser\RCAnalyzeSemanticDepth;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser\RCAnalyzeUserIntent;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use ReflectionException;
use Throwable;

/**
 * Class ContentDepthValidationOperation
 *
 * This class is responsible for validating the depth and comprehensiveness of content.
 * It analyzes whether a post adequately covers key subtopics, related keywords, and addresses user intent.
 */
#[SeoMeta(
    name: 'Content Depth Validation',
    weight: WeightConfiguration::WEIGHT_CONTENT_DEPTH_VALIDATION_OPERATION,
    description: 'Validates the depth and comprehensiveness of content by analyzing subtopic coverage, semantic richness, and user intent satisfaction. Provides suggestions for improving content quality based on analysis results.'
)]
class ContentDepthValidationOperation extends Operation implements OperationInterface
{
    /** @var RCAnalyzeSemanticDepth|null $semanticAnalysis The semantic analysis object */
    #[HideProperty]
    public ?RCAnalyzeSemanticDepth $semanticAnalysis = null;

    /** @var RCAnalyzeUserIntent|null $userIntentAnalysis The user intent analysis object */
    #[HideProperty]
    public ?RCAnalyzeUserIntent $userIntentAnalysis = null;

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
        $this->semanticAnalysis = new RCAnalyzeSemanticDepth();
        $this->userIntentAnalysis = new RCAnalyzeUserIntent();
        parent::__construct($key, $name, $weight);
    }

    /**
     * Performs content depth analysis for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get post-content
        $content = $this->contentProvider->getContent($postId);
        $cleanContent = $this->contentProvider->cleanContent($content);

        // Get post-type
        $postType = $this->contentProvider->getPostType($postId);

        // Get primary and secondary keywords
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);

        // Get word count
        $wordCount = $this->contentProvider->getWordCount($cleanContent);

        // Analyze content structure
        $structureAnalysis = $this->contentProvider->analyzeContentStructure($content, $wordCount);

        // Analyze subtopic coverage
        $subtopicCoverage = $this->contentProvider->analyzeSubtopicCoverage($content, $secondaryKeywords);

        // Analyze semantic depth
        $semanticDepthAnalysis = $this->analyzeSemanticDepth(
            $content,
            $primaryKeyword,
            $this->contentProvider->getSiteUrl(),
            $this->contentProvider->getLocale(),
            $this->contentProvider->getLanguageFromLocale()
        );

        // Analyze content for user intent satisfaction
        $userIntentAnalysis = $this->analyzeUserIntent(
            $content,
            $cleanContent,
            $primaryKeyword,
            $postType,
            $this->contentProvider->getSiteUrl(),
            $this->contentProvider->getLocale(),
            $this->contentProvider->getLanguageFromLocale()
        );

        // Prepare result data
        return [
            'success' => true,
            'message' => __('Content depth analysis completed successfully', 'beyond-seo'),
            'word_count' => $wordCount,
            'content_type' => $postType,
            'structure_analysis' => $structureAnalysis,
            'subtopic_coverage' => $subtopicCoverage,
            'semantic_depth_analysis' => $semanticDepthAnalysis,
            'user_intent_analysis' => $userIntentAnalysis
        ];
    }

    /**
     * Calculate the overall score based on the performed analysis
     *
     * @return float A score based on the content depth analysis
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        // Extract required analysis components from factorData
        $wordCount = $factorData['word_count'] ?? 0;
        $structureAnalysis = $factorData['structure_analysis'] ?? [];
        $subtopicCoverage = $factorData['subtopic_coverage'] ?? [];
        $semanticDepth = $factorData['semantic_depth_analysis'] ?? [];
        $userIntentAnalysis = $factorData['user_intent_analysis'] ?? [];

        // Weight each component based on importance
        $contentLengthWeight = 0.15;
        $structureWeight = 0.20;
        $subtopicWeight = 0.25;
        $semanticWeight = 0.25;
        $intentWeight = 0.15;

        // Length score (based on how close to the optimal length)
        $lengthScore = $this->calculateLengthScore($wordCount);

        // Structure score
        $structureScore = $this->calculateStructureScore($structureAnalysis);

        // Get component scores
        $subtopicScore = $subtopicCoverage['coverage_score'] ?? 0;
        $semanticScore = $semanticDepth['semantic_richness_score'] ?? 0;
        $intentScore = $userIntentAnalysis['intent_satisfaction_score'] ?? 0;

        // Calculate weighted average
        $overallScore = ($lengthScore * $contentLengthWeight) +
            ($structureScore * $structureWeight) +
            ($subtopicScore * $subtopicWeight) +
            ($semanticScore * $semanticWeight) +
            ($intentScore * $intentWeight);

        return max(0, min(1, $overallScore));
    }

    /**
     * Generate suggestions based on content depth analysis
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        $factorData = $this->value;
        $postId = $this->postId;

        // Get analysis components
        $wordCount = $factorData['word_count'] ?? 0;
        $structureAnalysis = $factorData['structure_analysis'] ?? [];
        $subtopicCoverage = $factorData['subtopic_coverage'] ?? [];
        $semanticDepth = $factorData['semantic_depth_analysis'] ?? [];
        $userIntentAnalysis = $factorData['user_intent_analysis'] ?? [];

        // Check for content depth issues
        if (($subtopicCoverage['coverage_score'] ?? 1) < 0.7) {
            $activeSuggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        // Check for related terms issues
        if (($semanticDepth['related_terms_coverage'] ?? 1) < 0.6) {
            $activeSuggestions[] = Suggestion::IMPORTANT_RELATED_TERMS_MISSING;
        }

        // Check for semantic context issues
        if (($semanticDepth['semantic_richness_score'] ?? 1) < 0.5) {
            $activeSuggestions[] = Suggestion::INSUFFICIENT_SEMANTIC_CONTEXT;
        }

        // Check for heading structure issues
        if (($structureAnalysis['headings_count'] ?? 0) < 3) {
            $activeSuggestions[] = Suggestion::INSUFFICIENT_HEADINGS;
        }

        // Check for content length issues
        $lengthBenchmark = $this->contentProvider->getLengthBenchmarksForContentType($postId);

        if ($wordCount < $lengthBenchmark['min']) {
            $activeSuggestions[] = Suggestion::CONTENT_TOO_SHORT;
        }

        // Check for user intent satisfaction issues
        if (($userIntentAnalysis['intent_satisfaction_score'] ?? 1) < 0.6) {
            $activeSuggestions[] = Suggestion::INTENT_NOT_SATISFIED;
        }

        return $activeSuggestions;
    }

    /**
     * Analyze semantic depth of content
     *
     * @param string $fullContent
     * @param string $primaryKeyword Primary keyword
     * @param string|null $domainUrl
     * @param string|null $locale
     * @param string|null $language
     * @return array Semantic depth analysis results
     */
    private function analyzeSemanticDepth(
        string $fullContent,
        string $primaryKeyword,
        ?string $domainUrl = null,
        ?string $locale = null,
        ?string $language = null
    ): array {
        // Always run local analysis first
        $localResult = $this->normalizeSemanticDepthResult(
            $this->analyzeSemanticDepthLocally($fullContent, $primaryKeyword)
        );

        // Check if fallback to external API is needed
        $needsExternal = false;

        if (
            ($localResult['semantic_richness_score'] ?? 0) < 0.7 ||
            ($localResult['topical_relevance_score'] ?? 0) < 0.7
        ) {
            $needsExternal = true;
        }

        if ($this->getFeatureFlag('external_api_call::semanticDepth') && $needsExternal) {
            try {
                $externalResult = $this->normalizeSemanticDepthResult(
                    $this->analyzeSemanticDepthFromExternalAPI(
                        $fullContent,
                        $primaryKeyword,
                        $domainUrl,
                        $locale,
                        $language
                    )
                );
                if (!empty($externalResult)) {
                    return $externalResult;
                }
            } catch (Throwable) {
                // Optional: log API failure
            }
        }

        return $localResult;
    }

    /**
     * Analyze semantic depth using the external API
     *
     * @param string $fullContent
     * @param string $primaryKeyword
     * @param string|null $domainUrl
     * @param string|null $locale
     * @param string|null $language
     * @return array
     * @throws InternalErrorException
     * @throws ReflectionException
     */
    private function analyzeSemanticDepthFromExternalAPI(
        string $fullContent,
        string $primaryKeyword,
        ?string $domainUrl = null,
        ?string $locale = null,
        ?string $language = null
    ): array {

        // Double-check if external API call is enabled via feature flag
        if (!$this->getFeatureFlag('external_api_call::semanticDepth')) {
            return []; // Return empty array if external API call is disabled
        }

        $rcRepo = new RCAnalyzeSemanticDepth();
        $rcRepo->setParent($this);
        $rcRepo->fromEntity($this->semanticAnalysis);

        $rcRepo->fullContent = $fullContent;
        $rcRepo->primaryKeyword = $primaryKeyword;
        $rcRepo->domainUrl = $domainUrl;
        $rcRepo->locale = $locale;
        $rcRepo->language = $language;

        $rcRepo->rcLoad(false, false);

        return $this->semanticAnalysis->analyse ?? [];
    }

    /**
     * Analyze semantic depth of content with local methods
     *
     * @param string $cleanContent Clean text content
     * @param string $primaryKeyword Primary keyword
     * @return array Semantic depth analysis results
     */
    private function analyzeSemanticDepthLocally(string $cleanContent, string $primaryKeyword): array {
        // Extract entities and concepts (simplified approach)
        $entities = $this->extractEntitiesAndConcepts($cleanContent);

        // Find related terms (LSI keywords)
        $relatedTerms = $this->contentProvider->findLSIKeywords($primaryKeyword, $cleanContent);

        // Calculate semantic richness
        $uniqueTermsCount = count($entities) + count($relatedTerms);
        $wordCount = str_word_count($cleanContent);

        // Semantic density = unique semantic terms / word count
        $semanticDensity = $wordCount > 0 ? $uniqueTermsCount / ($wordCount / 100) : 0;

        // Higher density is better, but with diminishing returns after a point
        $semanticRichnessScore = min(1, $semanticDensity / 5);

        // Calculate a topical relevance score based on related terms
        $topicalRelevanceScore = $this->calculateTopicalRelevance($relatedTerms, $cleanContent, $primaryKeyword);

        // Determine the expected number of related terms based on content length
        $expectedRelatedTerms = max(5, min(20, floor($wordCount / 200)));
        $relatedTermsRatio = min(1, count($relatedTerms) / $expectedRelatedTerms);

        return [
            'unique_entities_count' => count($entities),
            'related_terms_count' => count($relatedTerms),
            'semantic_density' => $semanticDensity,
            'semantic_richness_score' => $semanticRichnessScore,
            'topical_relevance_score' => $topicalRelevanceScore,
            'related_terms_coverage' => $relatedTermsRatio,
            'top_related_terms' => array_slice($relatedTerms, 0, 10, true),
            'top_entities' => array_slice($entities, 0, 10, true)
        ];
    }

    /**
     * Normalize semantic depth analysis result to the expected structure
     *
     * @param array $result
     * @return array
     */
    private function normalizeSemanticDepthResult(array $result): array
    {
        $expectedKeys = [
            'unique_entities_count' => 0,
            'related_terms_count' => 0,
            'semantic_density' => 0.0,
            'semantic_richness_score' => 0.0,
            'topical_relevance_score' => 0.0,
            'related_terms_coverage' => 0.0,
            'top_related_terms' => [],
            'top_entities' => []
        ];

        // Merge the result with expected defaults
        return array_merge($expectedKeys, $result);
    }

    /**
     * Extract entities and concepts from content
     *
     * @param string $content Clean text content
     * @return array Array of entities and concepts
     */
    private function extractEntitiesAndConcepts(string $content): array {
        // This would ideally use NLP API, but we'll use a simplified approach
        //  to Extract potential entities based on capitalization patterns
        preg_match_all('/\b[A-Z][a-zA-Z]*(?:\s+[A-Z][a-zA-Z]*)*\b/', $content, $matches);
        $potentialEntities = $matches[0];

        // Filter out common words that might be capitalized
        $commonWords = ['I', 'You', 'He', 'She', 'It', 'We', 'They', 'The', 'A', 'An'];
        $filteredEntities = array_filter($potentialEntities, static function($entity) use ($commonWords) {
            return !in_array($entity, $commonWords);
        });

        // Count entity occurrences
        $entityCounts = array_count_values($filteredEntities);
        arsort($entityCounts);

        return $entityCounts;
    }

    /**
     * Calculate topical relevance based on related terms
     *
     * @param array $relatedTerms Related terms with counts
     * @param string $content Clean text content
     * @param string $primaryKeyword Primary keyword
     * @return float Topical relevance score (0-1)
     */
    private function calculateTopicalRelevance(array $relatedTerms, string $content, string $primaryKeyword): float {
        if (empty($relatedTerms) || empty($primaryKeyword)) {
            return 0;
        }

        // Simplified assessment of how well the related terms support the main topic
        $contentLength = strlen($content);

        // Calculate proximity between primary keyword and related terms
        $keywordPositions = [];
        $offset = 0;

        // Find all occurrences of the primary keyword
        while (($pos = stripos($content, $primaryKeyword, $offset)) !== false) {
            $keywordPositions[] = $pos;
            $offset = $pos + strlen($primaryKeyword);
        }

        if (empty($keywordPositions)) {
            return 0;
        }

        // For each related term, find its closest proximity to any primary keyword occurrence
        $termProximities = [];
        $termCount = 0;

        foreach (array_keys($relatedTerms) as $term) {
            if (strlen($term) < 4) continue; // Skip very short terms

            $termPositions = [];
            $offset = 0;

            // Find all occurrences of this term
            while (($pos = stripos($content, $term, $offset)) !== false) {
                $termPositions[] = $pos;
                $offset = $pos + strlen($term);
            }

            if (empty($termPositions)) continue;

            // Find a minimum distance between this term and any primary keyword
            $minDistance = $contentLength;

            foreach ($termPositions as $termPos) {
                foreach ($keywordPositions as $keywordPos) {
                    $distance = abs($termPos - $keywordPos);
                    $minDistance = min($minDistance, $distance);
                }
            }

            // Normalize the distance (closer is better)
            $normalizedDistance = 1 - min(1, $minDistance / ($contentLength / 4));
            $termProximities[] = $normalizedDistance;
            $termCount++;
        }

        // Calculate average proximity
        $avgProximity = $termCount > 0 ? array_sum($termProximities) / $termCount : 0;

        // Calculate topical relevance based on term count and proximity
        $termCountScore = min(1, $termCount / 15); // Diminishing returns after 15 terms

        return ($avgProximity * 0.7) + ($termCountScore * 0.3);
    }

    /**
     * Analyze user intent of content (Hybrid: local and external fallback if needed)
     *
     * @param string $content Raw HTML content
     * @param string $cleanContent Clean text content
     * @param string $primaryKeyword Primary keyword
     * @param string $postType Post type
     * @param string|null $domainUrl Domain URL
     * @param string|null $locale Locale
     * @param string|null $language Language
     * @return array User intent analysis results
     */
    private function analyzeUserIntent(
        string $content,
        string $cleanContent,
        string $primaryKeyword,
        string $postType,
        ?string $domainUrl = null,
        ?string $locale = null,
        ?string $language = null
    ): array {
        // Always run local analysis first
        $localResult = $this->normalizeUserIntentResult(
            $this->contentProvider->analyzeUserIntentLocally($content, $cleanContent, $primaryKeyword, $postType)
        );

        // Check if fallback to external API is needed
        $needsExternal = false;

        if (($localResult['intent_satisfaction_score'] ?? 0) < 0.7) {
            $needsExternal = true;
        }

        if ($this->getFeatureFlag('external_api_call::userIntent') && $needsExternal) {
            try {
                $externalResult = $this->normalizeUserIntentResult(
                    $this->analyzeUserIntentFromExternalAPI(
                        $content,
                        $primaryKeyword,
                        $postType,
                        $domainUrl,
                        $locale,
                        $language
                    )
                );

                if (!empty($externalResult)) {
                    return $externalResult;
                }
            } catch (Throwable) {
                // Optional: log API fallback failure
            }
        }

        return $localResult;
    }

    /**
     * Analyze user intent using external API
     *
     * @param string $content Raw HTML content
     * @param string $primaryKeyword Primary keyword
     * @param string $postType Post type
     * @param string|null $domainUrl Domain URL
     * @param string|null $locale Locale
     * @param string|null $language Language
     * @return array User intent analysis results
     * @throws InternalErrorException
     */
    private function analyzeUserIntentFromExternalAPI(
        string $content,
        string $primaryKeyword,
        string $postType,
        ?string $domainUrl = null,
        ?string $locale = null,
        ?string $language = null
    ): array {
        // Double-check if external API call is enabled via feature flag
        if (!$this->getFeatureFlag('external_api_call::userIntent')) {
            return []; // Return empty array if external API call is disabled
        }

        $rcRepo = new RCAnalyzeUserIntent();
        $rcRepo->setParent($this);

        $rcRepo->fullContent = $content;
        $rcRepo->primaryKeyword = $primaryKeyword;
        $rcRepo->postType = $postType;
        $rcRepo->domainUrl = $domainUrl;
        $rcRepo->locale = $locale;
        $rcRepo->language = $language;

        $rcRepo->rcLoad(false, false);

        return $this->userIntentAnalysis->analyse ?? [];
    }

    /**
     * Normalize user intent analysis result to the expected structure
     *
     * @param array $result
     * @return array
     */
    private function normalizeUserIntentResult(array $result): array
    {
        $expectedKeys = [
            'detected_intent' => 'informational',
            'intent_markers' => [],
            'intent_satisfaction_score' => 0.0
        ];

        return array_merge($expectedKeys, $result);
    }

    /**
     * Calculate a length score based on word count
     *
     * @param int $wordCount Word count
     * @return float Length score (0-1)
     */
    private function calculateLengthScore(int $wordCount): float {
        // Optimal range is 1500-3000 words for most content
        if ($wordCount < 300) {
            return 0.1;
        } elseif ($wordCount < 600) {
            return 0.3;
        } elseif ($wordCount < 1000) {
            return 0.5;
        } elseif ($wordCount < 1500) {
            return 0.7;
        } elseif ($wordCount <= 3000) {
            return 1.0;
        } else {
            // Diminishing returns after 3000 words
            return 0.9;
        }
    }

    /**
     * Calculate structure score based on structure analysis
     *
     * @param array $structureAnalysis Structure analysis results
     * @return float Structure score (0-1)
     */
    private function calculateStructureScore(array $structureAnalysis): float {
        $score = 0.5; // Start with a neutral score

        // Assess heading count
        $headingsCount = $structureAnalysis['headings_count'] ?? 0;
        if ($headingsCount >= 5) {
            $score += 0.2;
        } elseif ($headingsCount >= 3) {
            $score += 0.1;
        } elseif ($headingsCount < 2) {
            $score -= 0.1;
        }

        // Assess heading hierarchy
        $hasH1 = (($structureAnalysis['headings_breakdown']['h1'] ?? 0) > 0);
        $hasH2 = (($structureAnalysis['headings_breakdown']['h2'] ?? 0) > 0);
        $hasH3 = (($structureAnalysis['headings_breakdown']['h3'] ?? 0) > 0);

        if ($hasH1 && $hasH2 && $hasH3) {
            $score += 0.1; // Good heading hierarchy
        }

        // Assess paragraph structure
        $avgParagraphLength = $structureAnalysis['avg_paragraph_length'] ?? 0;
        if ($avgParagraphLength > 20 && $avgParagraphLength < 150) {
            $score += 0.1; // Good paragraph length
        } elseif ($avgParagraphLength > 200) {
            $score -= 0.1; // Too long paragraphs
        }

        // Assess multimedia content
        if ($structureAnalysis['has_multimedia'] ?? false) {
            $score += 0.1; // Content includes images/videos
        }

        return max(0, min(1, $score));
    }
}
