<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base;

use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoFactors;
use App\Domain\Integrations\WordPress\Seo\Services\WPSeoOptimiserService;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Collection of Factor objects
 * @method Factor[] getElements()
 * @method Factor|null first()
 * @method Factor|null getByUniqueKey(string $uniqueKey)
 * @property Factor[] $elements
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBSeoFactors::class)]
class Factors extends EntitySet
{
    public const SERVICE_NAME = WPSeoOptimiserService::class;

    /**
     * Calculate scores for all factors
     * @return void
     */
    public function calculateAllScores(): void
    {
        /** @var Factor $factor */
        foreach ($this->elements as $factor) {
            $factor->calculateScore();
        }
    }

    /**
     * Get the weighted score of all factors
     * @return float The average score
     */
    public function getAverageScore(): float
    {
        $count = count($this->elements);
        if ($count === 0) {
            return 0;
        }

        $totalScore = 0;
        /** @var Factor $factor */
        foreach ($this->elements as $factor) {
            $totalScore += $factor->score;
        }

        return $totalScore / $count;
    }

    /**
     * Get weighted-score of all factors
     * @return float The weighted score
     */
    public function getWeightedScore(): float
    {
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($this->elements as $factor) {
            // Only include available factors in the weighted score calculation
            if (!$factor->isAvailable()) {
                $factor->featureFlags['available'] = false;
                continue;
            }
            
            $weightedSum += $factor->score * $factor->weight;
            $totalWeight += $factor->weight;
        }

        if ($totalWeight === 0) {
            return 0;
        }

        return $weightedSum / $totalWeight;
    }
}
