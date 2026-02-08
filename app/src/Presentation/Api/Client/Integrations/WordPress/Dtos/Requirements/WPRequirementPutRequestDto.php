<?php

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Requirements;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements\WPRequirement;
use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPRequirementPostRequestDto
 */

class WPRequirementPutRequestDto extends RequestDto
{
    #[Parameter(in: Parameter::PATH, required: true)]
    public int $requirementId;

    #[Parameter(in: Parameter::BODY, required: true)]
    public ?WPRequirement $requirement = null;
}
