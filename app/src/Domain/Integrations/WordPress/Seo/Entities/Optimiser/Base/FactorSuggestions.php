<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base;

use App\Domain\Integrations\WordPress\Seo\Services\WPSeoOptimiserService;
use DDD\Domain\Base\Entities\ObjectSet;

/**
 * Class FactorSuggestions
 * 
 * A collection class that manages sets of SEO optimization suggestions (FactorSuggestion objects).
 *
 * @method FactorSuggestion[] getElements()
 * @method FactorSuggestion|null first()
 * @method FactorSuggestion|null getByUniqueKey(string $uniqueKey)
 * @property FactorSuggestion[] $elements
 */
class FactorSuggestions extends ObjectSet
{
    /** @var string SERVICE_NAME Reference to the service class used for SEO optimization operations */
    public const SERVICE_NAME = WPSeoOptimiserService::class;

    /**
     * Order factor suggestions by a specific field
     *
     * @param string $field The field name to sort by (e.g., 'priority', 'activationThreshold')
     * @param string $order The sort direction ('ASC' for ascending, 'DESC' for descending)
     * @return static Returns the sorted collection instance for method chaining
     */
    public function orderBy(string $field, string $order = 'ASC'): static
    {
        $elements = $this->elements;
        usort($elements, static function ($a, $b) use ($field, $order) {
            if ($order === 'ASC') {
                return $a->{$field} <=> $b->{$field};
            } else {
                return $b->{$field} <=> $a->{$field};
            }
        });

        $this->elements = array_values($elements);
        return $this;
    }
}
