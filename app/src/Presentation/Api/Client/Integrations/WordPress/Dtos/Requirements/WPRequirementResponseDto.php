<?php

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Requirements;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements\WPRequirement;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class WPRequirementResponseDto extends RestResponseDto
{
    /** @var WPRequirement requested */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public WPRequirement $requirement;

}
