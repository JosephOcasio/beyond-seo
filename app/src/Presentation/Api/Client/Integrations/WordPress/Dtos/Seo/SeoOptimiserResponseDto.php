<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Models\Results\OptimiserResult;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class SeoOptimiserResponseDto
 */
class SeoOptimiserResponseDto extends RestResponseDto
{
    /** @var OptimiserResult|null $analyseResult The SEO analysis results */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public ?OptimiserResult $analyseResult = null;
}