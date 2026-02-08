<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Contexts;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\OptimiserContext;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\TechnicalSeo\OptimizeUrlStructureFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\TechnicalSeo\SchemaMarkupFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\TechnicalSeo\SearchEngineIndexationFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\TechnicalSeo\UseCanonicalTagsFactor;

/**
 * Class TechnicalSEOContext
 * 
 * Represents the technical SEO context for analysis.
 */
#[SeoMeta(
    name: 'Technical Seo',
    weight: WeightConfiguration::WEIGHT_TECHNICAL_SEO_CONTEXT,
    description: 'Analyzes and optimizes technical aspects of SEO, including URL structure, canonical tags, schema markup, and search engine indexation.',
)]
class TechnicalSeoContext extends OptimiserContext
{
    /** @var array $contextFactors List of SEO factors that are part of this context */
    protected static array $contextFactors = [
        OptimizeUrlStructureFactor::class,
        UseCanonicalTagsFactor::class,
        SchemaMarkupFactor::class,
        SearchEngineIndexationFactor::class
    ];
}
