<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Models\Results\OptimiserResult;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\OptimiserContext;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\OptimiserContexts;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Contexts\ContentOptimisationContext;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Contexts\LinkingStrategyContext;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Contexts\PerformanceAndSpeedContext;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Contexts\TechnicalSeoContext;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoContexts;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoOptimiser;
use App\Domain\Integrations\WordPress\Seo\Services\WPSeoOptimiserService;
use DateTime;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use ReflectionClass;
use ReflectionException;
use Throwable;

/**
 * Class SEOOptimiser
 *
 * This class is responsible for SEO optimization tasks.
 * @method WPSeoOptimiserService getService()
 * @method SeoOptimisers getParent()
 * @property SeoOptimisers $parent
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBSeoOptimiser::class)]
class SeoOptimiser extends Entity
{

    /** @var int The ID of the post/page the SEO optimizer analyzes */
    public int $postId;
    
    /** @var float The overall SEO score for the post on a scale of 0-1, calculated from weighted context scores */
    public float $score = 0;
    
    /** @var DateTime The timestamp when the SEO analysis was last performed */
    public DateTime $analysisDate;
    
    /** @var OptimiserContexts|null Collection of different analysis contexts (meta, content, etc.) with their factors and operations */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBSeoContexts::class)]
    public ?OptimiserContexts $contexts;

    protected static array $contextClasses = [
        ContentOptimisationContext::class,
        TechnicalSeoContext::class,
        PerformanceAndSpeedContext::class,
        LinkingStrategyContext::class
    ];

    /**
     * Constructor for the SEO optimizer
     * 
     * @param int|null $postId The ID of the post to be optimized
     * @throws Throwable If there is an issue loading data from the database
     */
    public function __construct(?int $postId = null)
    {
        parent::__construct();
        if($postId) {
            $this->contexts = new OptimiserContexts();
            $this->analysisDate = new DateTime();
            $this->postId = $postId;
        }
    }

    /**
     * Initialize the SEO optimizer with context classes
     * @param array $params
     * @return void
     * @throws ReflectionException
     */
    public function initContexts(array $params = []): void {
        /** @var OptimiserContext $optimiserContextClass */
        foreach (self::$contextClasses as $optimiserContextClass) {
            $attributes = (new ReflectionClass($optimiserContextClass))->getAttributes(SeoMeta::class);
            foreach ($attributes as $attribute) {
                /** @var SeoMeta $seoMeta */
                $seoMeta = $attribute->newInstance();
                if (isset($params['context']) && !in_array($seoMeta->getKey('context'), $params['context'], true)) {
                    continue;
                }
                $contextClass = new $optimiserContextClass(
                    $seoMeta->getLocalizedName(),
                    $seoMeta->getKey('context'),
                    $seoMeta->weight,
                    $this->id ?? 0,
                    $params,
                    true
                );
                $this->addContext($contextClass);
            }
        }
    }

    /**
     * Calculate the overall score for all contexts
     * 
     * Triggers score calculation in all contexts and compute weighted average
     * 
     * @return float The overall SEO score between 0-100
     */
    public function calculateScore(): float
    {
        $this->contexts->calculateAllScores();
        $this->score = $this->contexts->getWeightedScore();
        return $this->score;
    }

    /**
     * Add a context to the SEO optimizer
     * 
     * Contexts represent different aspects of SEO analyses such as meta-data, content, etc.
     * 
     * @param OptimiserContext $context The context to be added
     */
    public function addContext(OptimiserContext $context): void
    {
        $this->contexts->add($context);
    }


    /**
     * Analyzes the factors and contexts to determine an overall score
     *
     * Executes all operations in all factors across all contexts and calculates the score
     *
     * @return float The calculated overall score after performing the analysis
     * @throws Throwable
     */
    public function analyze(): float
    {
        // Execute all operations in all factors in all contexts
        foreach ($this->contexts as $context) {
            /* @var OptimiserContext $context */
            foreach ($context->factors as $factor) {
                /* @var Factor $factor */
                $factor->execute([
                    'postId' => $this->postId,
                ]);
            }
        }
        // Calculate the overall score
        return $this->calculateScore();
    }

    /**
     * Get a specific context by its key
     * 
     * @param string $contextKey The unique identifier for the context
     * @return OptimiserContext|null The requested context or null if not found
     */
    public function getContext(string $contextKey): ?OptimiserContext
    {
        return $this->contexts->getContextByKey($contextKey);
    }

    /**
     * Load SEO analysis data for a post from the database
     * 
     * Retrieves existing analysis results including contexts, factors, operations, and suggestions
     *
     * @throws Throwable If there is an error retrieving data from the database
     */
    public function loadSeoOptimiserData(): static
    {
        $service = $this->getService();
        if (!$service instanceof WPSeoOptimiserService) {
            return $this;
        }

        $postId = $this->postId;
        if (!$postId) {
            return $this;
        }
        
        // Load optimiser base data
        $optimiser = $service->getOptimiserByPostId($postId);
        if (!($optimiser instanceof SeoOptimiser) || !($optimiser->id ?? false)) {
            return $this;
        }

        // Map base properties
        $this->id = $optimiser->id;
        $this->postId = $optimiser->postId;
        $this->score = (float) $optimiser->score;
        $this->analysisDate = $optimiser->analysisDate;
        return $optimiser;
    }

    /**
     * Retrieves a unique key for the SEO optimizer
     * 
     * Used for caching and identification
     * 
     * @return string A unique identifier based on entity ID or post-ID
     */
    public function uniqueKey(): string
    {
        $parentUniqueKey = parent::uniqueKey();
        if($this->id) {
            return $parentUniqueKey . '_' . md5((string)$this->id);
        }
        return $parentUniqueKey . '_' . md5((string)$this->postId);
    }

    /**
     * Persists the calculated SEO score to the post's metadata
     *
     * This method saves the SEO score to WordPress post meta table for later retrieval
     * and updates the analysis timestamp. The score is normalized to a percentage value
     * for better readability in the WordPress admin interface.
     *
     * Implements intelligent caching to reduce redundant saves:
     * - Checks if content has changed using hash comparison
     * - Implements throttling to prevent frequent saves
     * - Only saves when there are actual changes
     *
     * @param SeoOptimiser $seoOptimiser
     * @return bool True if the score was successfully saved, false otherwise
     * @throws Throwable
     */
    public function saveToPostMeta(SeoOptimiser $seoOptimiser): bool
    {
        // Validate post ID
        if (empty($this->postId) || !is_numeric($this->postId) || $this->postId <= 0) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception(__('Invalid post ID for SEO score persistence', 'beyond-seo'));
        }

        // Ensure score is calculated and normalized
        if ($this->score < 0 || $this->score > 1) {
            // Normalize score to 0-1 range if outside bounds
            $this->score = max(0, min(1, $this->score));
        }
        
        // Update analysis timestamp if not already set
        if (!isset($this->analysisDate)) {
            $this->analysisDate = new DateTime();
        }
        
        // Convert score to percentage for better readability in meta
        $scorePercentage = round($this->score * 100);
        // Calculate and set the total suggestions count
        $totalSuggestionsCount = OptimiserResult::getTotalSuggestionsCount($seoOptimiser);
        // Encode the score breakdown as JSON for structured storage
        $scoreBreakdown = wp_json_encode($this->getScoreBreakdown());

        // Generate content hash for future comparisons
        $contentHash = WordpressHelpers::generateContentHash($this->postId);

        // Persist data to WordPress post meta using native WordPress functions
        $scoreUpdated       = update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_SEO_SCORE, $scorePercentage);
        $timestampUpdated   = update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_DATE_TIMESTAMP, time());
        update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_ISSUES_COUNT, 0);
        update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_SUGGESTIONS_COUNT, $totalSuggestionsCount);
        update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_SCORE_BREAKDOWN, $scoreBreakdown);
        
        // Update caching metadata
        update_post_meta($this->postId, BaseConstants::OPTION_ANALYSIS_CONTENT_HASH, $contentHash);

        return ($scoreUpdated !== false && $timestampUpdated !== false);
    }

    /**
     * Get a detailed breakdown of the entire scoring hierarchy
     *
     * Returns the optimizer score with contexts, factors and operations along
     * with their individual weights and scores.
     */
    public function getScoreBreakdown(): array
    {
        $contexts = [];
        foreach ($this->contexts as $context) {
            $contexts[] = $context->getScoreBreakdown();
        }

        return [
            'postId' => $this->postId,
            'score' => $this->score,
            'contexts' => $contexts,
        ];
    }

    /**
     * Extracts meta information and suggestions from all context, factor, and operation classes in a structured format.
     *
     * @return array
     */
    public static function extractData(): array
    {
        $contexts = [];
        foreach (self::$contextClasses as $contextClass) {
            $reflectionContext = new \ReflectionClass($contextClass);
            $contextMeta = self::getSeoMeta($contextClass);
            $contextWeight = $contextMeta['weight'] ?? null;
            $contextData = [
                'class' => $reflectionContext->getShortName(),
                'name' => $contextMeta['name'] ?? null,
                'description' => $contextMeta['description'] ?? null,
                'weight' => $contextWeight,
                'factors' => [],
            ];
            // Use reflection to access private/protected static $contextFactors
            $contextFactors = [];
            if ($reflectionContext->hasProperty('contextFactors')) {
                $property = $reflectionContext->getProperty('contextFactors');
                if ($property->isStatic()) {
                    $property->setAccessible(true);
                    $contextFactors = $property->getValue();
                }
            }
            foreach ($contextFactors as $factorClass) {
                $reflectionFactor = new \ReflectionClass($factorClass);
                $factorMeta = self::getSeoMeta($factorClass);
                $factorWeight = $factorMeta['weight'] ?? null;
                $factorData = [
                    'class' => $reflectionFactor->getShortName(),
                    'name' => $factorMeta['name'] ?? null,
                    'description' => $factorMeta['description'] ?? null,
                    'weight' => $factorWeight,
                    'operations' => [],
                ];
                // Use reflection to access private/protected static $operationsClasses
                $operationsClasses = [];
                if ($reflectionFactor->hasProperty('operationsClasses')) {
                    $property = $reflectionFactor->getProperty('operationsClasses');
                    if ($property->isStatic()) {
                        $property->setAccessible(true);
                        $operationsClasses = $property->getValue();
                    }
                }
                foreach ($operationsClasses as $operationClass) {
                    $operationMeta = self::getSeoMeta($operationClass);
                    $operationWeight = $operationMeta['weight'] ?? null;
                    $reflectionOperation = new \ReflectionClass($operationClass);
                    $operationClassShort = $reflectionOperation->getShortName();
                    $operationData = [
                        'class' => $operationClassShort,
                        'name' => $operationMeta['name'] ?? null,
                        'description' => $operationMeta['description'] ?? null,
                        'weight' => $operationWeight,
                        'suggestions' => [],
                    ];
                    // Use reflection to extract suggestions statically if possible
                    $suggestions = [];
                    if ($reflectionOperation->hasMethod('suggestions')) {
                        $method = $reflectionOperation->getMethod('suggestions');
                        $filename = $method->getFileName();
                        $startLine = $method->getStartLine();
                        $endLine = $method->getEndLine();
                        if ($filename && $startLine && $endLine) {
                            $lines = file($filename);
                            $methodCode = implode("", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
                            // Find all Suggestion::SOMETHING occurrences
                            if (preg_match_all('/Suggestion::([A-Z0-9_]+)/', $methodCode, $matches)) {
                                foreach ($matches[1] as $enumCase) {
                                    // Use correct variable variable syntax for enum case
                                    $suggestion = null;
                                    if (defined(Suggestion::class . '::' . $enumCase)) {
                                        $suggestion = constant(Suggestion::class . '::' . $enumCase);
                                    } else {
                                        $suggestion = 'Suggestion::' . $enumCase;
                                    }
                                    $suggestionDescription = method_exists($suggestion, 'getDescription') ? $suggestion->getDescription() : null;
                                    $operationData['suggestions'][] = [
                                        'enum' => $enumCase,
                                        'title' => $suggestionDescription['title'] ?? ($enumCase ?? null),
                                        'description' => $suggestionDescription['description'] ?? null,
                                    ];
                                }
                            }
                        }
                    }
                    $factorData['operations'][] = $operationData;
                }
                $contextData['factors'][] = $factorData;
            }
            $contexts[] = $contextData;
        }
        return $contexts;
    }

    private static function getSeoMeta(string $class): array
    {
        if (!class_exists($class)) {
            return [];
        }
        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes();
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'SeoMeta')) {
                $args = $attribute->getArguments();
                return [
                    'name' => $args['name'] ?? null,
                    'description' => $args['description'] ?? null,
                    'weight' => $args['weight'] ?? null,
                ];
            }
        }
        return [];
    }
}
