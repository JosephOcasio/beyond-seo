<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\ContentQualityAndLength\ContentDepthValidationOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\ContentQualityAndLength\ContentLengthValidationOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\ContentQualityAndLength\MultimediaInclusionCheckOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\ContentQualityAndLength\ReadabilityValidationOperation;

/**
 * Class ImproveContentQualityAndLength
 */
#[SeoMeta(
    name: 'Content Quality And Length',
    weight: WeightConfiguration::WEIGHT_CONTENT_QUALITY_AND_LENGTH_FACTOR,
    description: 'Evaluates content length, depth, readability, and multimedia inclusion to enhance overall content quality and user engagement.',
)]
class ContentQualityAndLengthFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        ContentLengthValidationOperation::class,
        ContentDepthValidationOperation::class,
        ReadabilityValidationOperation::class,
        MultimediaInclusionCheckOperation::class,
    ];
}
