<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\PageContentKeywords\ContentUpdateSuggestionsOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\PageContentKeywords\KeywordDensityValidationOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\PageContentKeywords\KeywordDistributionOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\PageContentKeywords\RelatedKeywordInclusionOperation;

/**
 * Class PageContentKeywordsFactor
 */
#[SeoMeta(
    name: 'Page Content Keywords',
    weight: WeightConfiguration::WEIGHT_PAGE_CONTENT_KEYWORDS_FACTOR,
    description: 'Evaluates keyword frequency, contextual relevance, placement, and content freshness to improve search visibility, and enhance overall user engagement.',
)]
class PageContentKeywordsFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        ContentUpdateSuggestionsOperation::class,
        KeywordDensityValidationOperation::class,
        KeywordDistributionOperation::class,
        RelatedKeywordInclusionOperation::class,
    ];
}
