<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\PerformanceAndSpeed;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\ImageOptimization\ImageCompressionValidationOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\ImageOptimization\NextGenImageFormatValidationOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\ImageOptimization\ResponsiveImageSizingOperation;

/**
 * Class ImageOptimizationFactor
 *
 * This class is responsible for ensuring that images on the website are properly optimized
 * for file size without losing noticeable quality.
 */
#[SeoMeta(
    name: 'Image Optimization',
    weight: WeightConfiguration::WEIGHT_IMAGE_OPTIMIZATION_FACTOR,
    description: 'Evaluates how images are compressed, formatted, and scaled responsively to reduce load times and improve usability across devices. If no images are found on the page, this check is marked as passed by default.',
)]

class ImageOptimizationFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        ImageCompressionValidationOperation::class,
        NextGenImageFormatValidationOperation::class,
        ResponsiveImageSizingOperation::class,
    ];
}
