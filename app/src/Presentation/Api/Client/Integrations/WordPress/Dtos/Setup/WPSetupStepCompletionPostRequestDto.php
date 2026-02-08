<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Setup;

use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPSetupStepCompletionPostRequestDto
 */
class WPSetupStepCompletionPostRequestDto extends RequestDto
{
    /** @var int $stepNumber The requested step number */
    #[Parameter(in: Parameter::BODY, required: true)]
    public int $stepNumber = 1;
}