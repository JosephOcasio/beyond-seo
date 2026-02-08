<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\TechnicalSeo;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\SchemaMarkup\SchemaMarkupValidationOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\SchemaMarkup\SchemaTypeIdentificationOperation;

/**
 * Class SchemaMarkupFactor
 *
 * This class is responsible for ensuring that schema markup is used correctly on a webpage.
 */
#[SeoMeta(
    name: 'Schema Markup',
    weight: WeightConfiguration::WEIGHT_SCHEMA_MARKUP_FACTOR,
    description: 'Analyzes structured data for proper schema usage and guideline adherence to improve indexing, rich results, and search performance.',
)]
class SchemaMarkupFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        SchemaTypeIdentificationOperation::class,
        SchemaMarkupValidationOperation::class,
    ];
}
