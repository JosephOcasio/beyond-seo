<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo;

use App\Domain\Integrations\WordPress\Seo\Entities\Analysis\Results\SEOAnalysisResult;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class SeoAnalysisResponseDto
 */
class SeoAnalysisResponseDto extends RestResponseDto
{
    /** @var object|null $analyseResult The SEO analysis results */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public ?object $analyseResult = null;
}