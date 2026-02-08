<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base;

use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoOperations;
use App\Domain\Integrations\WordPress\Seo\Services\WPSeoOptimiserService;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Collection of Operation objects
 * @method WPSeoOptimiserService getService()
 * @method Operation[] getElements()
 * @method Operation|null first()
 * @method Operation|null getByUniqueKey(string $uniqueKey)
 * @property Operation[] $elements
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBSeoOperations::class)]
class Operations extends EntitySet
{
    public const SERVICE_NAME = WPSeoOptimiserService::class;

    /**
     * Gets the average score of all operations
     * @return float The average score
     */
    public function getAverageScore(): float
    {
        $count = count($this->elements);
        if ($count === 0) {
            return 0;
        }

        $totalScore = 0;
        /** @var Operation $operation */
        foreach ($this->elements as $operation) {
            $totalScore += $operation->getScore();
        }

        return $totalScore / $count;
    }

    /**
     * Gets the weighted average score of all operations
     * @return float The weighted average score
     */
    public function getWeightedScore(): float
    {
        $totalWeight = 0;
        $weightedSum = 0;
        
        /** @var Operation $operation */
        foreach ($this->elements as $operation) {
            // Retrieve the operations feateure flags
            $operationIsAvailable = $operation->getFeatureFlag('available');
            // If the operation is not available, skip it
            if (!$operationIsAvailable) {
                continue;
            }

            $weightedSum += $operation->getScore() * $operation->weight;
            $totalWeight += $operation->weight;
        }
        
        if ($totalWeight === 0) {
            return 0;
        }
        
        return $weightedSum / $totalWeight;
    }
}
