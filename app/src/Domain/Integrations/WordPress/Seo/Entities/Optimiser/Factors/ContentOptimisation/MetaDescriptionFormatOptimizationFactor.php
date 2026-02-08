<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\MetaDescriptionFormatOptimization\MetaDescriptionCtaValidationOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\MetaDescriptionFormatOptimization\MetaDescriptionLengthCheckOperation;

/**
 * Class MetaDescriptionFormatOptimizationFactor
 *
 * This class is responsible for creating a meta-description tag for SEO optimization.
 */
#[SeoMeta(
    name: 'Meta Description Format Optimization',
    weight: WeightConfiguration::WEIGHT_META_DESCRIPTION_FORMAT_OPTIMIZATION_FACTOR,
    description: 'Ensures optimal meta descriptions with proper length and compelling call-to-action to improve click-through rates from search results.',
)]
class MetaDescriptionFormatOptimizationFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        MetaDescriptionLengthCheckOperation::class,
        MetaDescriptionCtaValidationOperation::class,
    ];
}
