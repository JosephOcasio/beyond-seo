<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Setup;

use App\Domain\Integrations\WordPress\Setup\Entities\SetupSteps\SetupStepCompletions\WPSetupStepCompletion;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPSetupStepCompletionGetResponseDto
 */
class WPSetupStepCompletionResponseDto extends RestResponseDto
{
    /** @var WPSetupStepCompletion The SetupStepCompletion requested */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public WPSetupStepCompletion $setupStepCompletion;
}