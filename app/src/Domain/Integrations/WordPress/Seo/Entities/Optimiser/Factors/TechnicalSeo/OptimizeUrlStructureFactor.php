<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Factors\TechnicalSeo;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\OptimizeUrlStructure\HyphensInsteadOfUnderscoresOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\OptimizeUrlStructure\PrimaryKeywordInUrlOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\OptimizeUrlStructure\UrlLengthCheckOperation;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\OptimizeUrlStructure\UrlReadabilityOperation;

/**
 * Class OptimizeURLStructureFactor
 *
 * This class is responsible for optimizing the URL structure of a website.
 */
#[SeoMeta(
    name: 'Optimize Url Structure',
    weight: WeightConfiguration::WEIGHT_OPTIMIZE_URL_STRUCTURE_FACTOR,
    description: 'Evaluates URLs for keyword inclusion, readability, proper length, and hyphen usage to improve search engine visibility.',
)]
class OptimizeUrlStructureFactor extends Factor
{
    /** @var class-string[] Operations */
    protected static array $operationsClasses = [
        HyphensInsteadOfUnderscoresOperation::class,
        PrimaryKeywordInUrlOperation::class,
        UrlLengthCheckOperation::class,
        UrlReadabilityOperation::class,
    ];
}
