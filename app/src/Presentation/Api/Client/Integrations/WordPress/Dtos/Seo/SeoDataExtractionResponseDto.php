<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo;

use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class SeoDataExtractionResponseDto
 * Response DTO for SEO data extraction that can handle both CSV and JSON formats
 */
class SeoDataExtractionResponseDto extends RestResponseDto
{
    /** @var string $format The format of the extracted data (csv or json) */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public string $format;

    /** @var string|null $csv The extracted SEO data as CSV string (only when format is csv) */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?string $csv = null;

    /** @var array|null $jsonData The extracted SEO data as array (only when format is json) */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?array $jsonData = null;
}
