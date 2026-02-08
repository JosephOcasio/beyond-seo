<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\FirstParagraphKeywordUsage\FirstParagraphKeywordCheckOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\FirstParagraphKeywordUsage\FirstParagraphKeywordStuffingOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\FirstParagraphKeywordUsage\OpeningParagraphEngagementAnalysisOperation;

/**
 * Class FirstParagraphKeywordUsageFactor
 *
 */
#[SeoMeta(
    name: 'First Paragraph Keyword Usage',
    weight: WeightConfiguration::WEIGHT_FIRST_PARAGRAPH_KEYWORD_USAGE_FACTOR,
    description: 'Evaluates keyword placement, density, and engagement in opening paragraphs to optimize topic relevance and reader connection.',
)]
class FirstParagraphKeywordUsageFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        FirstParagraphKeywordCheckOperation::class,
        FirstParagraphKeywordStuffingOperation::class,
        OpeningParagraphEngagementAnalysisOperation::class,
    ];
}
