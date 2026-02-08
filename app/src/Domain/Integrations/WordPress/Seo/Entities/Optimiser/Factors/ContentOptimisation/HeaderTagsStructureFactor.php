<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\ContentOptimisation;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\HeaderTagsStructure\FixingHeaderConsistencyOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\HeaderTagsStructure\HeaderHierarchyCheckOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\HeaderTagsStructure\KeywordsInHeaderCheckOperation;

/**
 * Class HeaderTagsStructureFactor
 *
 */
#[SeoMeta(
    name: 'Header Tags Structure',
    weight: WeightConfiguration::WEIGHT_HEADER_TAGS_STRUCTURE_FACTOR,
    description: 'Analyzes HTML heading tags (h1-h6) hierarchy, keyword usage, and structural consistency to enhance content organization and SEO effectiveness.'
)]
class HeaderTagsStructureFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        HeaderHierarchyCheckOperation::class,
        KeywordsInHeaderCheckOperation::class,
        FixingHeaderConsistencyOperation::class,
    ];
}
