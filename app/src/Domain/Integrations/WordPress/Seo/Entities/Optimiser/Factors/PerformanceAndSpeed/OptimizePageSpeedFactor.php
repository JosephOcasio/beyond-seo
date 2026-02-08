<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\PerformanceAndSpeed;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\OptimizePageSpeed\OptimizePageSpeedOperation;

/**
 * Class OptimizePageSpeedFactor
 *
 * This class is responsible for ensuring that the website's page speed is optimized.
 */
#[SeoMeta(
    name: 'Optimize Page Speed',
    weight: WeightConfiguration::WEIGHT_OPTIMIZE_PAGE_SPEED_FACTOR,
    description: 'Analyzes loading speed metrics and identifies performance bottlenecks to improve user experience and search rankings.'
)]
class OptimizePageSpeedFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        OptimizePageSpeedOperation::class
    ];
}