<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\LinkingStrategy;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AnalyzeBacklinkProfile\ReferringDomainsAnalysisOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AnalyzeBacklinkProfile\ReferringLinksQualityAssessmentOperation;

/**
 * Class AnalyzeBacklinkProfileFactor
 *
 * This class is responsible for analyzing the backlink profile of a website.
 */
#[SeoMeta(
    name: 'Analyze Backlink Profile',
    weight: WeightConfiguration::WEIGHT_ANALYZE_BACKLINK_PROFILE_FACTOR,
    description: 'Evaluates referring domains, backlink quality, anchor text distribution, and domain authority to improve site credibility and search rankings.',
)]
class AnalyzeBacklinkProfileFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        ReferringDomainsAnalysisOperation::class,
        ReferringLinksQualityAssessmentOperation::class
    ];
}