<?php
/** @noinspection PhpLackOfCohesionInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\ContentProviderInterface;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoOperation;
use App\Domain\Integrations\WordPress\Seo\Services\WPSeoOptimiserService;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use LogicException;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\FeatureFlagManager;
use Throwable;

/**
 * Represents a specific SEO check or measurement
 * 
 * An Operation is a single unit of SEO analysis that evaluates a specific aspect
 * of a page or post (e.g., keyword density, heading structure, meta-description quality).
 *
 * @method WPSeoOptimiserService getService()
 * @property Operations $parent
 * @method Operations getParent()
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBSeoOperation::class)]
class Operation extends Entity
{
    use RcLoggerTrait;

    // Class name for the parent entity
    public static string $operationClass = '';

    // Persistent properties - stored in a database
    /** @var int|null $id Database identifier for this operation */
    public ?int $id = 0;
    /** @var int|null $factorId ID of the parent SEO factor this operation belongs to */
    public ?int $factorId = 0;
    /** @var string|null $operationName Human-readable name of this operation */
    public ?string $operationName = null;
    /** @var string|null $operationKey Unique identifier key for this operation type */
    public ?string $operationKey = null;

    /** @var float $weight Relative importance of this operation within its parent factor (higher = more important) */
    public float $weight = 1.0;
    // Additional runtime properties - not directly persisted

    /** @var array $value The result from the `perform` method */
    //#[HideProperty]
    public array $value = [];

    /** @var int|null $analysisId ID of the analysis run this operation belongs to */
    #[HideProperty]
    public ?int $analysisId = 0;

    // Score-related properties
    /** @var float $score Normalized score between 0.0-1.0 representing how well this aspect of SEO is optimized */
    public float $score = 0;

    // Suggestion-related properties
    /** @var string[] $suggestions List of suggestion types that should be triggered based on this operation's results */
    public array $suggestions = [];

    // Content-provider-related properties
    /** @var ContentProviderInterface $contentProvider Service that provides content data for analysis */
    protected ContentProviderInterface $contentProvider;

    /**
     * @var int The ID of the post/page being analyzed
     */
    public int $postId = 0;

    /**
     * @var array Feature flags for this operation - exposed in JSON
     */
    public array $featureFlags = [];

    /**
     * Constructor for creating a new operation
     *
     * @throws Throwable If there's an error during initialization
     */
    public function __construct(?string $key = null, ?string $name = null, ?float $weight = 0.0)
    {
        parent::__construct();
        $this->operationKey = $key;
        $this->operationName = $name;
        $this->weight = $weight;
        $this->contentProvider = $this->getService()->getContentProvider();
    }

    /**
     * Set a feature flag value
     *
     * @param string $flag The feature flag name
     * @param bool $value The feature flag value
     * @return void
     */
    public function setFeatureFlag(string $flag, bool $value): void
    {
        // Set in local featureFlags array for JSON serialization
        $this->featureFlags[$flag] = $value;
        
        // Also set the flag in the centralized manager
        if ($this->operationKey) {
            FeatureFlagManager::getInstance()->setOperationFlag($this->operationKey, $flag, $value);
        }
    }

    /**
     * Get a feature flag value
     *
     * @param string $flag The feature flag name
     * @return bool The feature flag value or true if not found
     */
    public function getFeatureFlag(string $flag): bool
    {
        // First check local featureFlags array
        if (array_key_exists($flag, $this->featureFlags)) {
            return $this->featureFlags[$flag];
        }
        
        // Use the centralized feature flag manager if an operation key is available
        if ($this->operationKey) {
            $value = FeatureFlagManager::getInstance()->getFlag($flag, $this->operationKey);
            // Cache the value locally for consistency
            $this->featureFlags[$flag] = $value;
            return $value;
        }
        
        // Fallback to true if no operation key is set
        $this->featureFlags[$flag] = true;
        return true;
    }

    /**
     * Executes the operation using the provided parameters
     *
     * Runs the analysis for this specific SEO aspect, calculates the score,
     * and determines which suggestions should be shown.
     *
     * @param int $postId
     * @return void
     */
    public function execute(int $postId): void
    {
        // Assign the current opeartion key to the static variable
        self::$operationClass = $this->operationKey;

        if ($postId <= 0) {
            $this->value = [
                'success' => false,
                'message' => __('Invalid post ID', 'beyond-seo')
            ];
            return;
        }

        $this->postId = $postId;
        $this->value = $this->run();
    }

    /**
     * Retrieves a unique key for the operation
     * 
     * Generates a unique identifier for this operation instance based on its properties,
     * used for database operations and entity management.
     *
     * @return string Unique identifier string
     */
    public function uniqueKey(): string
    {
        $parentUniqueKey = parent::uniqueKey();
        return $parentUniqueKey . '_' . md5($this->analysisId . $this->factorId . $this->operationKey);
    }

    /**
     * Sets the score for the operation
     * @return void
     */
    public function setScore(): void {
        $this->score = isset($this->value['success']) ? $this->calculateScore() : 0.0;
    }

    /**
     * Sets the suggestions for the operation
     * @return void
     */
    public function setSuggestions(): void {
        $this->suggestions = !empty($this->value) && isset($this->value['success']) ? $this->suggestions() : [];
    }

    /**
     * Retrieves the score of the operation
     * 
     * Gets the normalized score (0.0-1.0) representing how well this SEO aspect is optimized.
     *
     * @return float|int Score value between 0.0-1.0
     */
    public function getScore(): float|int
    {
        return $this->score;
    }

    /**
     * Retrieves the operation suggestions
     * 
     * Gets all applicable suggestions for this operation from the database based on
     * the operation's key. These suggestions provide actionable advice for improving
     * the SEO aspect this operation measures.
     * 
     * @return FactorSuggestions|null Collection of suggestions or null if none found
     * @throws Throwable If there's an error retrieving suggestions
     */
    public function getSuggestions(): ?FactorSuggestions
    {
        $suggestions = new FactorSuggestions();
        foreach ($this->suggestions as $suggestion) {
            $suggestionFound = FactorSuggestion::createFromEnum(
                $suggestion,
                $this->operationKey
            );
            $suggestionFound->displayConfig = $suggestionFound->getBadgeConfig() ?? [];
            $suggestions->add($suggestionFound);
        }

        return $suggestions;
    }

    /**
     * Child classes must implement OperationInterface to override the placeholder method for running the operation
     * @return array|null
     */
    public function run(): ?array
    {
        throw new LogicException(static::class . ' must implement run()');
    }

    /**
     * Child classes must implement OperationInterface to override the placeholder method for calculating score
     * @return float
     */
    public function calculateScore(): float {
        throw new LogicException(static::class . ' must implement calculateScore()');
    }

    /**
     * Child classes must implement OperationInterface to override the placeholder method for getting suggestions
     * @return Suggestion[]
     */

    public function suggestions(): array
    {
        throw new LogicException(static::class . ' must implement suggestions()');
    }

    /**
     * Gets the result of the operation
     *
     * @return array
     */
    public function getResult(): array
    {
        return $this->value;
    }

    /**
     * Get a breakdown of the score calculation for this operation
     *
     * Provides the operation name, key, weight and the calculated score. This
     * information can be used to trace how higher level scores are built up.
     */
    public function getScoreBreakdown(): array
    {
        return [
            'name' => $this->operationName,
            'key' => $this->operationKey,
            'weight' => $this->weight,
            'score' => $this->score,
        ];
    }

    /**
     * Get unique enum values from an array of enums.
     *
     * This is a workaround for array_unique() not working correctly with enum instances.
     *
     * @param array $enumValues Array of enum instances
     * @param bool $ignoreFunction
     * @return array Array of unique enum instances
     * @deprecated This method is deprecated and will be removed in future versions.
     */
    public function getUniqueEnumValues(array $enumValues, bool $ignoreFunction = true): array
    {
        if($ignoreFunction) {
            return $enumValues;
        }

        $uniqueValues = [];
        $seenNames = [];

        foreach ($enumValues as $enumValue) {
            $name = $enumValue->name;
            if (!isset($seenNames[$name])) {
                $seenNames[$name] = true;
                $uniqueValues[] = $enumValue;
            }
        }

        return $uniqueValues;
    }
}
