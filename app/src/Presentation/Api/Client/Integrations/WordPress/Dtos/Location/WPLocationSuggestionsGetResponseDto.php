<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Location;

use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPLocationSuggestionsGetResponseDto
 */
class WPLocationSuggestionsGetResponseDto extends RestResponseDto
{
    /** @var array|null $businessLocationMatches */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?array $businessLocationMatches = null;

    /** @var bool $success */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public bool $success = false;

    /** @var string|null $message */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?string $message = null;
}