<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\MetaDescriptionKeywords\DescriptionKeywordOveruseOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\MetaDescriptionKeywords\PrimarySecondaryKeywordCheckOperation;

/**
 * Class MetaDescriptionKeywordsFactor
 *
 * It extends the base Factor class and can be used to evaluate the effectiveness of keyword assignment.
 */
#[SeoMeta(
    name: 'Meta Description Keywords',
    weight: WeightConfiguration::WEIGHT_META_DESCRIPTION_KEYWORDS_FACTOR,
    description: 'Analyzes keyword usage and positioning within meta descriptions to ensure relevance without oversaturation.',
)]
class MetaDescriptionKeywordsFactor extends Factor
{

    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        PrimarySecondaryKeywordCheckOperation::class,
        DescriptionKeywordOveruseOperation::class,
    ];
}
