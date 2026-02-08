<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Models\Results;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\FactorSuggestions;
use DDD\Domain\Base\Entities\ValueObject;

/**
 * Class FactorResult
 *
 * This class represents the result of a specific SEO factor analysis.
 */
class FactorResult extends ValueObject
{
    public string $name;
    public string $key;
    public float $score;
    public float $weight;
    public FactorSuggestions $suggestions;

    /**
     * Constructor for initializing the object with name, key, score, and weight.
     *
     * @param string $name The name of the factor.
     * @param string $key The key of the factor.
     * @param float $score The score of the factor.
     * @param float $weight The weight of the factor.
     */
    public function __construct(string $name, string $key, float $score, float $weight, FactorSuggestions $suggestions = null)
    {
        parent::__construct();
        $this->name = $name;
        $this->key = $key;
        $this->score = $score;
        $this->weight = $weight;
        $this->suggestions = $suggestions ?? new FactorSuggestions();
    }
}