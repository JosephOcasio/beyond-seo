<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoContext;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoFactors;
use App\Domain\Integrations\WordPress\Seo\Services\WPSeoOptimiserService;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use ReflectionClass;
use Throwable;

/**
 * Represents a major category of SEO analysis
 * 
 * An OptimiserContext defines a specific area of SEO analysis (such as Content Optimization,
 * Technical SEO, etc.) and contains multiple factors that contribute to the overall score
 * for this area. Each context has its own weight in the final SEO score calculation.
 *
 * @method WPSeoOptimiserService getService()
 * @property OptimiserContexts $parent
 * @method OptimiserContexts getParent()
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBSeoContext::class)]
class OptimiserContext extends Entity
{
    // Persistent properties - stored in a database
    /** @var int|null $id Database identifier for this context */
    public ?int $id = 0;
    /** @var int $analysisId Reference to the parent analysis run this context belongs to */
    public int $analysisId = 0;
    /** @var string|null $contextName Unique identifier key for this context (e.g., 'content_optimisation', 'technical_seo') */
    public ?string $contextName = null;
    /** @var string|null $contextKey Unique identifier key for this context (e.g., 'content_optimisation', 'technical_seo') */
    public ?string $contextKey = null;
    /** @var float $weight Relative importance of this context in the overall SEO score (higher = more important) */
    public float $weight = 1;
    /** @var float $score Calculated score for this context (0.0-1.0) representing the SEO performance for this area */
    public float $score = 0;

    // Additional runtime properties - not directly persisted
    /** @var Factors|null $factors Collection of SEO factors that contribute to this context's analysis */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBSeoFactors::class)]
    public ?Factors $factors;

    /** @var array Feature flags for this context - exposed in JSON */
    public array $featureFlags = [];

    /** @var array $contextFactors List of SEO factors that are part of this context */
    protected static array $contextFactors = [];

    /**
     * Constructor for creating a new OptimiserContext
     *
     * @param string|null $contextName The name of this context (e.g., 'Content Optimization', 'Technical SEO')
     * @param string|null $contextKey The key identifying this context type (e.g., 'content_optimisation')
     * @param float $weight The weight of this context in the overall score calculation (default: 1.0)
     * @param int $analysisId The ID of the analysis run this context belongs to (default: 0)
     * @param mixed $params Additional parameters for context initialization (default: [])
     * @param bool $initFactors Whether to initialize factors for this context (default: false - used for lazyloading)
     */
    public function __construct(string $contextName = null, string $contextKey = null, float $weight = 1.0, int $analysisId = 0, array $params = [], bool $initFactors = false)
    {
        parent::__construct();
        $this->contextName = $contextName;
        $this->contextKey = $contextKey;
        $this->weight = ($weight !== null && $weight >= 0) ? $weight : 1.0;
        $this->analysisId = $analysisId;
        if ($initFactors) {
            $this->factors = new Factors();
            $this->initFactors($params);
        }
        
        // Initialize feature flags from SeoMeta attributes
        $this->initFeatureFlags();
    }

    /**
     * Initialize feature flags from SeoMeta attributes
     *
     * @return void
     */
    public function initFeatureFlags(): void
    {
        try {
            $attributes = (new ReflectionClass(static::class))->getAttributes(SeoMeta::class);
            foreach ($attributes as $attribute) {
                /** @var SeoMeta $seoMeta */
                $seoMeta = $attribute->newInstance();
                $features = $seoMeta->getFeatures();
                
                if (!empty($features) && is_array($features)) {
                    foreach ($features as $feature => $value) {
                        if (is_string($feature) && is_bool($value)) {
                            $this->featureFlags[$feature] = $value;
                        } elseif (is_int($feature) && is_string($value)) {
                            // Handle array of feature names (assume enabled)
                            $this->featureFlags[$value] = true;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // Silently fail if reflection fails - not critical for operation
        }
    }

    /**
     * Initialize factors for this context
     * 
     * Abstract method that should be implemented by each context subclass
     * to define and add the relevant SEO factors for that context. This method is called
     * during construction to set up the factor collection.
     *
     * @param array $params Additional parameters for factor initialization
     * @return void
     */
    protected function initFactors(array $params = []): void
    {
        /** @var Factor $factorClass */
        foreach (static::$contextFactors as $factorClass) {
            $attributes = (new ReflectionClass($factorClass))->getAttributes(SeoMeta::class);
            foreach ($attributes as $attribute) {
                /** @var SeoMeta $seoMeta */
                $seoMeta = $attribute->newInstance();
                if (isset($params['factor']) && !in_array($seoMeta->getKey('factor'), $params['factor'], true)) {
                    continue;
                }
                $factor = new $factorClass(
                    $seoMeta->getLocalizedName(),
                    $seoMeta->getKey('factor'),
                    $seoMeta->weight,
                    $seoMeta->getLocalizedDescription(),
                    $params,
                    true
                );
                if ($factor->isAvailable()) {
                    $this->addFactor($factor);
                }
            }
        }
    }

    /**
     * Calculate the overall score for this context
     * 
     * Processes all factors in this context, calculates their individual scores,
     * and then computes the weighted average to determine the overall score for
     * this SEO context. The score is stored in the 'score' property.
     *
     * @return float The calculated score (0.0-1.0) representing performance in this SEO area
     */
    public function calculateScore(): float
    {
        $this->factors->calculateAllScores();
        $this->score = $this->factors->getWeightedScore();
        return $this->score;
    }

    /**
     * Add a factor to this context
     * 
     * Adds a new SEO factor to this context and connects it to the current
     * analysis run by setting its analysisId. Factors are components that
     * evaluate specific aspects of SEO within this context.
     *
     * @param Factor $factor The SEO factor to add to this context
     * @return void
     */
    public function addFactor(Factor $factor): void
    {
        $factor->analysisId = $this->analysisId;
        $this->factors->add($factor);
    }

    /**
     * @throws Throwable
     */
    public function getContextSuggestions(): FactorSuggestions
    {
        $contextSuggestions = new FactorSuggestions();
        foreach ($this->factors->getElements() as $factor) {
            $factorSuggestions = $factor->getFactorSuggestions();
            /** @var FactorSuggestion $suggestion */
            foreach ($factorSuggestions as $suggestion) {
                if ($contextSuggestions->getByUniqueKey($suggestion->uniqueKey()) === $suggestion) {
                    continue;
                }
                $contextSuggestions->add($suggestion);
            }
        }
        return $contextSuggestions->orderBy('priority');
    }

    /**
     * Get a detailed score breakdown for this context
     *
     * Returns the context name, key, weight, calculated score and the list
     * of factor breakdowns that contributed to it.
     */
    public function getScoreBreakdown(): array
    {
        $factors = [];
        foreach ($this->factors as $factor) {
            $factors[] = $factor->getScoreBreakdown();
        }

        return [
            'name' => $this->contextName,
            'key' => $this->contextKey,
            'weight' => $this->weight,
            'score' => $this->score,
            'factors' => $factors,
        ];
    }

    /**
     * Generate a unique key for this context
     * 
     * Creates a unique identifier string for this context instance based on its
     * analysis ID and context key. This unique key is used for database operations
     * and entity management.
     *
     * @return string Unique identifier string
     */
    public function uniqueKey(): string
    {
        $parentUniqueKey = parent::uniqueKey();
        return $parentUniqueKey . '_' . md5($this->analysisId . $this->contextKey);
    }

    /**
     * Check if this context is available based on its factors
     * A context is considered available if it has at least one available factor
     *
     * @return bool True if the context has available factors, false otherwise
     */
    public function isAvailable(): bool
    {
        // If factors are not initialized, consider the context available
        if (!isset($this->factors) || $this->factors->count() === 0) {
            return true;
        }

        // Check if at least one factor is available
        foreach ($this->factors as $factor) {
            if ($factor->isAvailable()) {
                return true;
            }
        }

        // No available factors found
        return false;
    }
}
