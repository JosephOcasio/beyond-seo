<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\LocalKeywordsInContent\LocalKeywordMetaTagOptimizationOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\LocalKeywordsInContent\LocalKeywordPresenceOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\LocalKeywordsInContent\LocalSchemaMarkupSuggestionOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\LocalKeywordsInContent\LocalSchemaValidationOperation;

/**
 * Class LocalKeywordsInContentFactor
 *
 */
#[SeoMeta(
    name: 'Local Keywords In Content',
    weight: WeightConfiguration::WEIGHT_LOCAL_KEYWORDS_IN_CONTENT_FACTOR,
    description: 'Evaluates location keywords in content, meta tags, validates LocalBusiness schema, and suggests markup improvements for local SEO optimization.',
)]
class LocalKeywordsInContentFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        LocalKeywordPresenceOperation::class,
        LocalSchemaValidationOperation::class,
        LocalKeywordMetaTagOptimizationOperation::class,
        LocalSchemaMarkupSuggestionOperation::class
    ];
}
