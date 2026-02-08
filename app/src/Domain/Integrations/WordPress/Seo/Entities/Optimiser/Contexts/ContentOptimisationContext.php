<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Contexts;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\OptimiserContext;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\AssignKeywordsFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\ContentQualityAndLengthFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\ContentReadabilityFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\FirstParagraphKeywordUsageFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\HeaderTagsStructureFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\LocalKeywordsInContentFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\MetaDescriptionFormatOptimizationFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\MetaDescriptionKeywordsFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\MetaTitleFormatOptimizationFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\MetaTitleKeywordsFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation\PageContentKeywordsFactor;

/**
 * Class ContentOptimisationContext
 * 
 * Represents the content optimization context for SEO analysis.
 */
#[SeoMeta(
    name: 'Content Optimisation',
    weight: WeightConfiguration::WEIGHT_CONTENT_OPTIMISATION_CONTEXT,
    description: 'Analyzes and optimizes content for SEO by focusing on keyword usage, content quality, readability, and meta tags.',
)]
class ContentOptimisationContext extends OptimiserContext
{
    /** @var array $contextFactors List of SEO factors that are part of this context */
    protected static array $contextFactors = [
        AssignKeywordsFactor::class,
        MetaTitleKeywordsFactor::class,
        MetaDescriptionKeywordsFactor::class,
        PageContentKeywordsFactor::class,
        FirstParagraphKeywordUsageFactor::class,
        HeaderTagsStructureFactor::class,
        MetaTitleFormatOptimizationFactor::class,
        MetaDescriptionFormatOptimizationFactor::class,
        ContentQualityAndLengthFactor::class,
        ContentReadabilityFactor::class,
        LocalKeywordsInContentFactor::class,
    ];
}
