<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class OnboardingRequestDto
 */
class OnboardingRequestDto extends RequestDto
{
    /** @var bool If set to true, debug mode will be activated */
    #[Parameter(in: Parameter::QUERY, required: false)]
    public bool $debug = false;
}
