<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Models\Results;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * Class OperationResult
 *
 * This class represents the result of an SEO operation.
 */
class OperationResult extends ValueObject
{
    public float $score;
    public string $details;
    public mixed $value;
    public int $timestamp;

    /**
     * Constructor for initializing the object with score, details, value, and timestamp.
     *
     * @param float $score The score of the operation.
     * @param string $details Details about the operation.
     * @param mixed $value The value associated with the operation.
     * @param int $timestamp The timestamp of the operation.
     */
    public function __construct(
        float $score,
        string $details,
        mixed $value,
        int $timestamp
    ) {
        parent::__construct();
        $this->score = $score;
        $this->details = $details;
        $this->value = $value;
        $this->timestamp = $timestamp;
    }
}