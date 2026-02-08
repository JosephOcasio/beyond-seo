<?php

declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Setup;

use App\Domain\Integrations\WordPress\Setup\Entities\SetupSteps\SetupStepCompletions\WPSetupStepCompletion;
use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPSetupStepCompletionUpdateRequestDto
 */
class WPSetupStepCompletionUpdateRequestDto extends RequestDto
{
    /** @var WPSetupStepCompletion|null SetupStepCompletion object */
    #[Parameter(in: Parameter::BODY, required: true)]
    public ?WPSetupStepCompletion $setupStepCompletion = null;
}