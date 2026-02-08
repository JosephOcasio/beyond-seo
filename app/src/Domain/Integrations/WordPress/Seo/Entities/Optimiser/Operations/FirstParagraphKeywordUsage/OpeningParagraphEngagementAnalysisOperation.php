<?php /** @noinspection PhpTooManyParametersInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\FirstParagraphKeywordUsage;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser\RCAnalyzeOpeningParagraphEngagement;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use Throwable;

/**
 * Class OpeningParagraphEngagementAnalysisOperation
 *
 * This class analyzes the opening paragraph's engagement effectiveness by evaluating:
 * - Hook strength and relevance
 * - Emotional appeal
 * - Curiosity elements
 * - Personal connection with readers
 * - Topic clarity and introduction quality
 */
#[SeoMeta(
    name: 'Opening Paragraph Engagement Analysis',
    weight: WeightConfiguration::WEIGHT_OPENING_PARAGRAPH_ENGAGEMENT_ANALYSIS_OPERATION,
    description: 'Analyzes the opening paragraph of a post for engagement effectiveness. It evaluates the hook strength, emotional appeal, curiosity elements, personal connection with readers, and how well the topic is introduced. This helps ensure that the opening paragraph captures reader interest and sets the stage for the content.',
)]
class OpeningParagraphEngagementAnalysisOperation extends Operation implements OperationInterface
{
    /** @var RCAnalyzeOpeningParagraphEngagement|null $engagementAnalyze The engagement analysis object */
    #[HideProperty]
    public ?RCAnalyzeOpeningParagraphEngagement $engagementAnalyze = null;

    // Common stopwords to exclude when analyzing topics
    private array $stopWords = ['the', 'and', 'or', 'a', 'an', 'in', 'on', 'at', 'to', 'for', 'with', 'by', 'of', 'that', 'this'];

    // Pre-compiled regex patterns for performance optimization
    private array $curiosityPatterns = [];
    private array $personalConnectionPatterns = [];
    private array $hookPatterns = [];

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
        $this->engagementAnalyze = new RCAnalyzeOpeningParagraphEngagement();
        
        // Pre-compile regex patterns for better performance
        $this->initializeRegexPatterns();
        
        parent::__construct($key, $name, $weight);
    }
    
    /**
     * Initialize regex patterns once for reuse
     */
    private function initializeRegexPatterns(): void
    {
        // Curiosity gap patterns
        $this->curiosityPatterns = [
            // Question patterns
            '/\?/i',
            // Mystery patterns
            '/secret|mystery|reveal|discover|find out|surprising|unexpected|you won\'t believe/i',
            // Teaser patterns
            '/here\'s why|here\'s how|let me show you|the truth about|what you need to know/i',
            // Incomplete information patterns
            '/most people don\'t know|little-known|hidden|behind the scenes|untold story/i'
        ];

        // Personal connection patterns
        $this->personalConnectionPatterns = [
            // Direct address
            '/\byou\b|\byour\b/i',
            // Shared experience
            '/\bwe\b|\bour\b/i',
            // Empathy indicators
            '/understand|relate|feel|struggle|challenge|like you|just like you/i',
            // Personal story indicators
            '/\bI\b|\bmy\b|\bmine\b/i'
        ];
        
        // Hook patterns
        $this->hookPatterns = [
            'question' => '/\?/i',
            'statistic' => '/\d+%|\d+\s+percent|\d+\s+people/i',
            'bold_statement' => '/never|always|best|worst|greatest|ultimate|revolutionary|groundbreaking|surprising|shocking|incredible/i',
            'problem_solution' => '/problem|solution|struggle|challenge|overcome|achieve|improve|boost|enhance/i',
            'story_beginning' => '/when|one day|last year|recently|in \d{4}|once upon a time/i'
        ];
    }

    /**
     * Performs opening paragraph engagement analysis for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get the content
        $content = $this->contentProvider->getContent($postId, true);

        // Get post-information for context
        $postType = $this->contentProvider->getPostType($postId);
        $category = $this->contentProvider->getFirstCategoryName($postId);

        // Get primary and secondary keywords and SEO metadata
        $primaryKeyword = $this->contentProvider->getPrimaryKeyword($postId);
        $secondaryKeywords = $this->contentProvider->getSecondaryKeywords($postId);
        $pageTitle = $this->contentProvider->getPostTitle($postId);
        $metaDescription = $this->contentProvider->getMetaDescription($postId);
        $headings = $this->contentProvider->extractHeadings($content);

        $hasIntroMedia = $this->hasIntroMedia($content);

        // Extract the opening paragraph
        $openingParagraph = $this->extractOpeningParagraph($content);


        if (empty($openingParagraph)) {
            return [
                'success' => false,
                'message' => __('No opening paragraph found in the content', 'beyond-seo')
            ];
        }

        // Analyze the opening paragraph for engagement metrics
        $engagementAnalysis = $this->analyseOpeningParagraphForEngagement(
            $openingParagraph,
            $content,
            $category,
            $primaryKeyword,
            $secondaryKeywords,
            $headings,
            $pageTitle,
            $metaDescription
        );

        // Prepare the result
        // Check keyword presence in opening paragraph
        $keywordAnalysis = $this->analyzeKeywordPresence($openingParagraph, $primaryKeyword, $secondaryKeywords);
        
        // Store the result for score calculation and suggestions
        $this->value = [
            'success' => true,
            'message' => __('Opening paragraph engagement analysis completed successfully', 'beyond-seo'),
            'opening_paragraph' => $openingParagraph,
            'has_intro_media' => $hasIntroMedia,
            'post_type' => $postType,
            'category' => $category,
            'analysis' => $engagementAnalysis,
            'keyword_analysis' => $keywordAnalysis
        ];

        return $this->value;
    }

    /**
     * Calculate the score based on the performed opening paragraph engagement analysis.
     *
     * @return float A score between 0 and 1 based on the engagement analysis
     */
    public function calculateScore(): float
    {
        if (!isset($this->value['success']) || !$this->value['success']) {
            return 0.0;
        }

        $engagementAnalysis = $this->value['analysis'] ?? [];
        $keywordAnalysis = $this->value['keyword_analysis'] ?? [];
        
        // Base engagement score (70% of total score)
        $engagementScore = $engagementAnalysis['engagement_score'] ?? 0.0;
        $engagementWeight = 0.7;
        
        // Keyword presence score (30% of total score)
        $keywordScore = 0.0;
        $keywordWeight = 0.3;
        
        if ($keywordAnalysis['has_primary_keyword'] ?? false) {
            $keywordScore = 1.0;
            
            // Bonus for early keyword placement
            $keywordPositionByWord = $keywordAnalysis['primary_keyword_position_by_word'] ?? null;
            if ($keywordPositionByWord !== null) {
                if ($keywordPositionByWord <= 10) {
                    $keywordScore = 1.0; // Perfect placement
                } elseif ($keywordPositionByWord <= 20) {
                    $keywordScore = 0.9; // Good placement
                } elseif ($keywordPositionByWord <= 30) {
                    $keywordScore = 0.8; // Acceptable placement
                } else {
                    $keywordScore = 0.7; // Late but present
                }
            }
            
            // Bonus for secondary keywords
            $secondaryKeywordsCount = $keywordAnalysis['secondary_keywords_count'] ?? 0;
            if ($secondaryKeywordsCount > 0) {
                $keywordScore = min(1.0, $keywordScore + ($secondaryKeywordsCount * 0.05));
            }
        }
        
        // Calculate final weighted score
        $finalScore = ($engagementScore * $engagementWeight) + ($keywordScore * $keywordWeight);
        
        return round($finalScore, 2);
    }

    /**
     * Generate suggestions based on the opening paragraph engagement analysis.
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        if (!isset($this->value['success']) || !$this->value['success']) {
            return $activeSuggestions;
        }

        $engagementAnalysis = $this->value['analysis'] ?? [];
        $keywordAnalysis = $this->value['keyword_analysis'] ?? [];

        // PRIORITY 1: Check for missing primary keyword in first paragraph
        if (!($keywordAnalysis['has_primary_keyword'] ?? false)) {
            $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_FIRST_PARAGRAPH;
        }

        // PRIORITY 2: Check for poor keyword placement (if keyword exists but is positioned late)
        if (($keywordAnalysis['has_primary_keyword'] ?? false)) {
            $keywordPositionByWord = $keywordAnalysis['primary_keyword_position_by_word'] ?? null;
            if ($keywordPositionByWord !== null && $keywordPositionByWord > 30) {
                $activeSuggestions[] = Suggestion::POOR_KEYWORD_ENGAGEMENT_IN_OPENING;
            }
        }

        // PRIORITY 3: Check paragraph length optimization
        if (!($engagementAnalysis['is_optimal_length'] ?? false)) {
            $wordCount = $engagementAnalysis['word_count'] ?? 0;
            if ($wordCount < SeoOptimiserConfig::PARAGRAPH_WORD_COUNT_THRESHOLD['short']) {
                $activeSuggestions[] = Suggestion::PARAGRAPHS_TOO_SHORT;
            } elseif ($wordCount > SeoOptimiserConfig::PARAGRAPH_WORD_COUNT_THRESHOLD['long']) {
                $activeSuggestions[] = Suggestion::PARAGRAPHS_TOO_LONG;
            }
        }

        // PRIORITY 4: Check engagement-related issues
        $engagementScore = $engagementAnalysis['engagement_score'] ?? 0.0;

        // Check if the hook is effective
        if (!($engagementAnalysis['hook_analysis']['effective'] ?? false)) {
            $activeSuggestions[] = Suggestion::INTENT_NOT_SATISFIED;
        }

        // Check if there's emotional appeal
        if (($engagementAnalysis['emotional_appeal']['score'] ?? 0.0) < 0.3) {
            $activeSuggestions[] = Suggestion::LACKS_EMOTIONAL_APPEAL;
        }

        // Check if there's a curiosity gap
        if (!($engagementAnalysis['has_curiosity_gap'] ?? false)) {
            $activeSuggestions[] = Suggestion::LACKS_CURIOSITY_ELEMENTS;
        }

        // Check if there's a personal connection
        if (!($engagementAnalysis['has_personal_connection'] ?? false)) {
            $activeSuggestions[] = Suggestion::LACKS_READER_CONNECTION;
        }

        // Check if the topic is clearly introduced (separate from keyword presence)
        if (!($engagementAnalysis['introduces_topic_clearly'] ?? false)) {
            $activeSuggestions[] = Suggestion::INTENT_NOT_SATISFIED;
        }

        // If the overall engagement score is poor
        if ($engagementScore < 0.4) {
            $activeSuggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
        }

        // If the content lacks multimedia in the intro
        if ($engagementScore < 0.6 && !$this->value['has_intro_media']) {
            $activeSuggestions[] = Suggestion::MISSING_MULTIMEDIA_IN_INTRO_SECTION;
        }

        return $this->getUniqueEnumValues($activeSuggestions);
    }

    /**
     * Hybrid analysis: run local analysis and fall back to AI if the score is low.
     */
    private function analyseOpeningParagraphForEngagement(
        string $openingParagraph,
        string $fullContent,
        string $category,
        string $primaryKeyword,
        array $secondaryKeywords,
        array $headings,
        ?string $pageTitle,
        ?string $metaDescription
    ): array {
        // Always run local analysis first
        $localResult = $this->normalizeEngagementResult(
            $this->analyzeOpeningParagraphEngagement($openingParagraph, $fullContent, $category)
        );
        
        // Check if fallback to external API is needed
        $needsExternal = false;
        
        if (($localResult['engagement_score'] ?? 0) < 0.75) {
            $needsExternal = true;
        }

        if ($this->getFeatureFlag('external_api_call') && $needsExternal) {
            try {
                $externalResult = $this->normalizeEngagementResult(
                    $this->analyzeOpeningParagraphEngagementFromExternalAPI(
                        $this->contentProvider->getSiteUrl(),
                        $this->contentProvider->getLocale(),
                        $this->contentProvider->getLanguageFromLocale(),
                        $fullContent,
                        $primaryKeyword,
                        $secondaryKeywords,
                        $openingParagraph,
                        $headings,
                        $pageTitle,
                        $metaDescription
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
     * Normalize engagement analysis result to the expected structure
     *
     * @param array $result
     * @return array
     */
    private function normalizeEngagementResult(array $result = []): array
    {
        $expectedKeys = [
            'word_count' => 0,
            'is_optimal_length' => false,
            'optimal_length_range' => [30, 80],
            'hook_analysis' => [
                'effective' => false,
                'detected_hooks' => [],
                'has_knowledge_gap' => false,
                'relevance_score' => 0.0
            ],
            'emotional_appeal' => [
                'score' => 0.0,
                'detected_emotions' => [],
                'emotional_words' => []
            ],
            'has_curiosity_gap' => false,
            'has_personal_connection' => false,
            'introduces_topic_clearly' => false,
            'engagement_score' => 0.0,
            'engagement_level' => 'low'
        ];

        // Merge the result with expected defaults
        return array_merge($expectedKeys, $result);
    }

    /**
     * Analyze opening paragraph engagement using external AI API
     *
     * @param string $domainUrl Domain URL
     * @param string $locale Locale
     * @param string $language Language
     * @param string $content Raw HTML content of the full page
     * @param string $primaryKeyword Primary keyword
     * @param array $secondaryKeywords List of secondary keywords
     * @param string $firstParagraph First paragraph text
     * @param array $headings Page headings (H1, H2, H3, etc.)
     * @param string|null $pageTitle Page title
     * @param string|null $metaDescription Meta description
     * @return array Engagement analysis result
     * @throws InternalErrorException
     */
    private function analyzeOpeningParagraphEngagementFromExternalAPI(
        string $domainUrl,
        string $locale,
        string $language,
        string $content,
        string $primaryKeyword,
        array $secondaryKeywords,
        string $firstParagraph,
        array $headings,
        ?string $pageTitle,
        ?string $metaDescription,
    ): array {
        // Double-check if external API call is enabled via feature flag
        if (!$this->getFeatureFlag('external_api_call')) {
            return []; // Return empty array if external API call is disabled
        }
        $rcRepo = new RCAnalyzeOpeningParagraphEngagement();
        $rcRepo->setParent($this);

        $rcRepo->fullContent = $content;
        $rcRepo->primaryKeyword = $primaryKeyword;
        $rcRepo->secondaryKeywords = $secondaryKeywords;
        $rcRepo->firstParagraph = $firstParagraph;
        $rcRepo->pageTitle = $pageTitle ?? null;
        $rcRepo->metaDescription = $metaDescription ?? null;
        $rcRepo->headings = $headings;
        $rcRepo->domainUrl = $domainUrl;
        $rcRepo->locale = $locale;
        $rcRepo->language = $language;

        $rcRepo->rcLoad(false, false);

        return json_decode(json_encode($this->engagementAnalyze->analyse), true) ?? [];
    }

    /**
     * Checks if the content has introductory media elements like images, videos, or iframes.
     *
     * @param string $content
     * @return bool
     */
    private function hasIntroMedia(string $content): bool
    {
        $content = trim($content);

        // Match the first HTML element
        if (preg_match('/^(<[^>]+>)/i', $content, $match)) {
            $firstElement = $match[1];

            // Case 1: Starts directly with <img>, <video>, or <iframe>
            if (preg_match('/^<(img|video|iframe)\b/i', $firstElement)) {
                return true;
            }

            // Case 2: Starts with <p> that contains one of the media elements
            if (stripos($firstElement, '<p') !== false) {
                if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $content, $pMatch)) {
                    $firstParagraph = $pMatch[1];

                    if (preg_match('/<(img|video|iframe)\b/i', $firstParagraph)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Extracts the opening paragraph from the content.
     *
     * @param string $content The content to extract from
     * @return string The opening paragraph or empty string if not found
     */
    private function extractOpeningParagraph(string $content): string
    {
        // Use regex to find the first paragraph
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $content, $matches)) {
            return wp_strip_all_tags($matches[0]);
        }

        // If no paragraph tags, try to identify the first text block
        $cleanContent = $this->contentProvider->cleanContent($content);
        $sentences = preg_split('/(?<=[.?!])\s+/', $cleanContent, 2);

        if (!empty($sentences[0])) {
            return $sentences[0];
        }

        return '';
    }

    /**
     * Analyzes the opening paragraph for engagement metrics.
     *
     * @param string $openingParagraph The opening paragraph to analyze
     * @param string $fullContent The full content for context
     * @param string $category The content category for context
     * @return array Engagement analysis results
     */
    private function analyzeOpeningParagraphEngagement(string $openingParagraph, string $fullContent, string $category): array
    {
        // Get word count of the opening paragraph
        $wordCount = $this->contentProvider->getWordCount($openingParagraph);

        // Check if the paragraph is too short or too long (optimal: 30-80 words)
        $isOptimalLength = $wordCount >= 30 && $wordCount <= 80;

        // Analyze hook effectiveness
        $hookAnalysis = $this->analyzeHookEffectiveness($openingParagraph, $category);

        // Check for emotional appeal
        $emotionalAppeal = $this->analyzeEmotionalAppeal($openingParagraph);

        // Check for a question or curiosity gap
        $hasCuriosityGap = $this->hasCuriosityGap($openingParagraph);

        // Check for personal connection
        $hasPersonalConnection = $this->hasPersonalConnection($openingParagraph);

        // Check if the paragraph introduces the main topic clearly
        $introducesTopicClearly = $this->introducesTopicClearly($openingParagraph, $fullContent);

        // Calculate overall engagement score
        $engagementScore = $this->calculateEngagementScore(
            $isOptimalLength,
            $hookAnalysis['effective'],
            $emotionalAppeal['score'],
            $hasCuriosityGap,
            $hasPersonalConnection,
            $introducesTopicClearly
        );

        return [
            'word_count' => $wordCount,
            'is_optimal_length' => $isOptimalLength,
            'optimal_length_range' => [30, 80],
            'hook_analysis' => $hookAnalysis,
            'emotional_appeal' => $emotionalAppeal,
            'has_curiosity_gap' => $hasCuriosityGap,
            'has_personal_connection' => $hasPersonalConnection,
            'introduces_topic_clearly' => $introducesTopicClearly,
            'engagement_score' => $engagementScore,
            'engagement_level' => $this->getEngagementLevel($engagementScore)
        ];
    }

    /**
     * Analyzes the effectiveness of the hook in the opening paragraph.
     *
     * @param string $paragraph The paragraph to analyze
     * @param string $category The content category for context
     * @return array Hook analysis results
     */
    private function analyzeHookEffectiveness(string $paragraph, string $category): array
    {
        $detectedHooks = [];
        foreach ($this->hookPatterns as $type => $pattern) {
            if (preg_match($pattern, $paragraph)) {
                $detectedHooks[] = $type;
            }
        }

        // Check if the opening creates a knowledge-gap
        $hasKnowledgeGap = preg_match('/want to know|you\'ll learn|discover|find out|reveal|secret|how to|why you should/i', $paragraph);

        // Determine if the hook is effective based on the number of detected hooks
        $isEffective = !empty($detectedHooks) || $hasKnowledgeGap;

        return [
            'detected_hooks' => $detectedHooks,
            'has_knowledge_gap' => $hasKnowledgeGap,
            'effective' => $isEffective,
            'recommended_for_category' => $this->getRecommendedHookForCategory($category)
        ];
    }

    /**
     * Returns the recommended hook type for a given content category.
     *
     * @param string $category The content category
     * @return string The recommended hook type
     */
    private function getRecommendedHookForCategory(string $category): string
    {
        $categoryHookMap = [
            'technology' => 'statistic',
            'health' => 'question',
            'finance' => 'bold_statement',
            'education' => 'problem_solution',
            'entertainment' => 'story_beginning'
        ];

        $normalizedCategory = strtolower($category);

        foreach ($categoryHookMap as $cat => $hook) {
            if (str_contains($normalizedCategory, $cat)) {
                return $hook;
            }
        }

        // Default recommendation for unmatched categories
        return 'question';
    }

    /**
     * Analyzes the emotional appeal of the opening paragraph.
     *
     * @param string $paragraph The paragraph to analyze
     * @return array Emotional appeal analysis
     */
    private function analyzeEmotionalAppeal(string $paragraph): array
    {
        // Lists of emotional trigger words by category
        $emotionalTriggers = [
            'joy' => ['happy', 'excited', 'thrilled', 'delighted', 'joy', 'pleasure', 'celebrate'],
            'fear' => ['afraid', 'scary', 'terrifying', 'dreadful', 'anxious', 'fear', 'worried'],
            'anger' => ['angry', 'frustrated', 'irritated', 'annoyed', 'outraged', 'furious'],
            'sadness' => ['sad', 'depressed', 'heartbroken', 'disappointed', 'gloomy', 'miserable'],
            'surprise' => ['amazed', 'astonished', 'surprised', 'shocked', 'startled', 'unexpected'],
            'trust' => ['trust', 'reliable', 'dependable', 'honest', 'authentic', 'proven', 'guaranteed'],
            'anticipation' => ['anticipate', 'await', 'expect', 'coming soon', 'prepare for', 'look forward'],
            'curiosity' => ['curious', 'discover', 'reveal', 'mystery', 'secret', 'wonder', 'fascinating']
        ];

        $detectedEmotions = [];
        $emotionalWordCount = 0;

        $lowerParagraph = strtolower($paragraph);
        foreach ($emotionalTriggers as $emotion => $words) {
            $count = 0;
            foreach ($words as $word) {
                if (str_contains($lowerParagraph, $word)) {
                    $count++;
                    $emotionalWordCount++;
                }
            }

            if ($count > 0) {
                $detectedEmotions[$emotion] = $count;
            }
        }

        // Calculate emotional score based on variety and density
        $varietyScore = count($detectedEmotions) / count($emotionalTriggers);

        $wordCount = $this->contentProvider->getWordCount($paragraph);
        $emotionalDensity = $wordCount > 0 ? $emotionalWordCount / $wordCount : 0;

        // Overall emotional appeal score (0-1)
        $overallScore = min(1, ($varietyScore * 0.5) + ($emotionalDensity * 10 * 0.5));

        return [
            'detected_emotions' => $detectedEmotions,
            'emotional_word_count' => $emotionalWordCount,
            'emotional_density' => round($emotionalDensity * 100, 2) . '%',
            'variety_score' => round($varietyScore, 2),
            'score' => round($overallScore, 2)
        ];
    }

    /**
     * Checks if the opening paragraph contains a curiosity gap.
     * Uses pre-compiled regex patterns for better performance.
     *
     * @param string $paragraph The paragraph to check
     * @return bool True if a curiosity gap is detected
     */
    private function hasCuriosityGap(string $paragraph): bool
    {
        foreach ($this->curiosityPatterns as $pattern) {
            if (preg_match($pattern, $paragraph)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the opening paragraph establishes a personal connection with the reader.
     * Uses pre-compiled regex patterns for better performance.
     *
     * @param string $paragraph The paragraph to check
     * @return bool True if a personal connection is detected
     */
    private function hasPersonalConnection(string $paragraph): bool
    {
        foreach ($this->personalConnectionPatterns as $pattern) {
            if (preg_match($pattern, $paragraph)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the opening paragraph clearly introduces the main topic.
     * Optimized to reduce regex operations.
     *
     * @param string $paragraph The opening paragraph
     * @param string $fullContent The full content for context
     * @return bool True if the topic is clearly introduced
     */
    private function introducesTopicClearly(string $paragraph, string $fullContent): bool
    {
        // Cache main topics to avoid repeated extraction
        static $cachedTopics = [];
        static $cachedContent = '';
        
        // Only extract topics if content has changed
        if ($cachedContent !== $fullContent) {
            $cachedTopics = $this->extractMainTopics($fullContent);
            $cachedContent = $fullContent;
        }

        if (empty($cachedTopics)) {
            return false;
        }

        // Check if any main topic is mentioned in the opening paragraph
        $paragraphLower = strtolower($paragraph);
        foreach ($cachedTopics as $topic) {
            if (str_contains($paragraphLower, strtolower($topic))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts the main topics from the full content.
     * Optimized to use fewer regex operations.
     *
     * @param string $content The full content
     * @return array Array of main topics
     */
    private function extractMainTopics(string $content): array
    {
        $mainTopics = [];

        // Extract from title (if available)
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $content, $matches)) {
            $title = wp_strip_all_tags($matches[0]);
            $mainTopics[] = $title;

            // Extract keywords from the title
            $words = explode(' ', $title);
            foreach ($words as $word) {
                if (strlen($word) > 3 && !in_array(strtolower($word), $this->stopWords, true)) {
                    $mainTopics[] = $word;
                }
            }
        }

        // Extract from H2 headings as they often represent main topics
        if (preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $content, $matches)) {
            foreach ($matches[1] as $heading) {
                $mainTopics[] = wp_strip_all_tags($heading);
            }
        }

        return array_unique($mainTopics);
    }

    /**
     * Calculates the overall engagement score of the opening paragraph.
     *
     * @param bool $isOptimalLength Whether the paragraph has an optimal length
     * @param bool $hasEffectiveHook Whether the paragraph has an effective hook
     * @param float $emotionalAppealScore The emotional appeal score
     * @param bool $hasCuriosityGap Whether the paragraph has a curiosity gap
     * @param bool $hasPersonalConnection Whether the paragraph establishes a personal connection
     * @param bool $introducesTopicClearly Whether the paragraph clearly introduces the topic
     * @return float The overall engagement score (0-1)
     */
    private function calculateEngagementScore(
        bool $isOptimalLength,
        bool $hasEffectiveHook,
        float $emotionalAppealScore,
        bool $hasCuriosityGap,
        bool $hasPersonalConnection,
        bool $introducesTopicClearly
    ): float {
        // Weight of each factor in the overall score
        $weights = [
            'optimal_length' => 0.15,
            'effective_hook' => 0.25,
            'emotional_appeal' => 0.2,
            'curiosity_gap' => 0.15,
            'personal_connection' => 0.1,
            'introduces_topic' => 0.15
        ];

        // Calculate scores for each factor
        $scores = [
            'optimal_length' => $isOptimalLength ? 1.0 : 0.5, // If not optimal, still give partial credit
            'effective_hook' => $hasEffectiveHook ? 1.0 : 0.3,
            'emotional_appeal' => $emotionalAppealScore,
            'curiosity_gap' => $hasCuriosityGap ? 1.0 : 0.3,
            'personal_connection' => $hasPersonalConnection ? 1.0 : 0.5,
            'introduces_topic' => $introducesTopicClearly ? 1.0 : 0.3
        ];

        // Calculate weighted average
        $weightedScore = 0;
        foreach ($weights as $factor => $weight) {
            $weightedScore += $scores[$factor] * $weight;
        }

        return round($weightedScore, 2);
    }

    /**
     * Returns the engagement level based on the score.
     *
     * @param float $score The engagement score
     * @return string The engagement level description
     */
    private function getEngagementLevel(float $score): string
    {
        if ($score >= 0.8) {
            return 'excellent';
        } elseif ($score >= 0.6) {
            return 'good';
        } elseif ($score >= 0.4) {
            return 'average';
        } elseif ($score >= 0.2) {
            return 'below_average';
        } else {
            return 'poor';
        }
    }

    /**
     * Analyze keyword presence in the opening paragraph
     *
     * @param string $openingParagraph The opening paragraph text
     * @param string $primaryKeyword The primary keyword to check
     * @param array $secondaryKeywords Array of secondary keywords to check
     * @return array Analysis results
     */
    private function analyzeKeywordPresence(string $openingParagraph, string $primaryKeyword, array $secondaryKeywords): array
    {
        $openingParagraphLower = strtolower($openingParagraph);
        $primaryKeywordLower = strtolower($primaryKeyword);
        
        // Check primary keyword presence
        $hasPrimaryKeyword = !empty($primaryKeyword) && str_contains($openingParagraphLower, $primaryKeywordLower);
        
        // Find primary keyword position if present
        $primaryKeywordPosition = null;
        $primaryKeywordPositionByWord = null;
        
        if ($hasPrimaryKeyword) {
            $primaryKeywordPosition = strpos($openingParagraphLower, $primaryKeywordLower);
            
            // Calculate position by word count
            $textBeforeKeyword = substr($openingParagraph, 0, $primaryKeywordPosition);
            $wordsBeforeKeyword = str_word_count($textBeforeKeyword);
            $primaryKeywordPositionByWord = $wordsBeforeKeyword + 1; // +1 because we want 1-based indexing
        }
        
        // Check secondary keywords presence
        $secondaryKeywordsFound = [];
        foreach ($secondaryKeywords as $secondaryKeyword) {
            if (!empty($secondaryKeyword) && str_contains($openingParagraphLower, strtolower($secondaryKeyword))) {
                $secondaryKeywordsFound[] = $secondaryKeyword;
            }
        }
        
        return [
            'has_primary_keyword' => $hasPrimaryKeyword,
            'primary_keyword_position' => $primaryKeywordPosition,
            'primary_keyword_position_by_word' => $primaryKeywordPositionByWord,
            'secondary_keywords_found' => $secondaryKeywordsFound,
            'secondary_keywords_count' => count($secondaryKeywordsFound),
            'total_keywords_found' => ($hasPrimaryKeyword ? 1 : 0) + count($secondaryKeywordsFound)
        ];
    }


}
