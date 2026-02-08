<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Contexts;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\OptimiserContext;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\LinkingStrategy\AnalyzeBacklinkProfileFactor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\LinkingStrategy\FixBrokenLinksOnPageFactor;

/**
 * Class LinkingStrategyContext
 * 
 * Represents the linking strategy context for SEO analysis.
 */
#[SeoMeta(
    name: 'Linking Strategy',
    weight: WeightConfiguration::WEIGHT_LINKING_STRATEGY_CONTEXT,
    description: 'Analyzes and optimizes internal and external linking strategies to enhance site authority and user navigation.',
)]
class LinkingStrategyContext extends OptimiserContext
{
    /** @var array $contextFactors List of SEO factors that are part of this context */
    protected static array $contextFactors = [
        FixBrokenLinksOnPageFactor::class,
        AnalyzeBacklinkProfileFactor::class
    ];
}
