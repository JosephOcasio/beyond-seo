<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AssignKeywords\KeywordCompetitionVolumeCheckOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AssignKeywords\KeywordMappingContentOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AssignKeywords\PrimarySecondaryKeywordsValidationOperation;

/**
 * Class AssignKeywordsFactor
 */
#[SeoMeta(
    name: 'Assign Keywords',
    weight: WeightConfiguration::WEIGHT_ASSIGN_KEYWORDS_FACTOR,
    description: 'Validates keyword selection, analyzes competition metrics, and prevents cannibalization across content.',
)]
class AssignKeywordsFactor extends Factor
{
    /** @var class-string[] Operations  */
    protected static array $operationsClasses = [
        PrimarySecondaryKeywordsValidationOperation::class,
        KeywordCompetitionVolumeCheckOperation::class,
        KeywordMappingContentOperation::class
    ];
}
