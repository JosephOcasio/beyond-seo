<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo;

use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class SeoAnalysisRequestDto
 * @package App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo
 *
 */
class SeoAnalysisRequestDto extends SeoPostIdRequestDto
{
    /** @var string $context List of context keys to be loaded, separated by comma */
    #[Parameter(in: Parameter::QUERY, required: false)]
    public string $context;

    /** @var string $factor List of factor keys to be loaded, separated by comma */
    #[Parameter(in: Parameter::QUERY, required: false)]
    public string $factor;

    /** @var string $operation List of operation keys to be loaded, separated by comma */
    #[Parameter(in: Parameter::QUERY, required: false)]
    public string $operation;

    #[Parameter(in: Parameter::QUERY, required: false)]
    public string $export = 'csv';

}
