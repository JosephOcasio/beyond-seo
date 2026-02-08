<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base;

use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoContexts;
use App\Domain\Integrations\WordPress\Seo\Services\WPSeoOptimiserService;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Collection of OptimiserContext objects
 * @method OptimiserContext[] getElements()
 * @method OptimiserContext|null first()
 * @method OptimiserContext|null getByUniqueKey(string $uniqueKey)
 * @property OptimiserContext[] $elements
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBSeoContexts::class)]
class OptimiserContexts extends EntitySet
{
    public const SERVICE_NAME = WPSeoOptimiserService::class;

    /**
     * Get the weighted score of all contexts
     */
    public function getWeightedScore(): float
    {
        if ($this->count() === 0) {
            return 0;
        }
        $totalScore = 0;
        $totalWeight = 0;
        
        /** @var OptimiserContext $context */
        foreach ($this->elements as $context) {
            // Only include available contexts in the weighted score calculation
            if (!$context->isAvailable()) {
                $context->featureFlags['available'] = false;
                continue;
            }
            
            $totalScore += $context->score * $context->weight;
            $totalWeight += $context->weight;
        }
        return $totalWeight > 0 ? $totalScore / $totalWeight : 0;
    }

    /**
     * Calculate the score for all contexts
     */
    public function calculateAllScores(): void
    {
        foreach ($this->elements as $context) {
            $context->calculateScore();
        }
    }

    /**
     * Get a context by its unique key
     * @param string $contextKey The key for the context
     * @return OptimiserContext|null The context if found, null otherwise
     */
    public function getContextByKey(string $contextKey): ?OptimiserContext
    {
        foreach ($this->elements as $context) {
            if ($context->contextKey === $contextKey) {
                return $context;
            }
        }
        return null;
    }
}