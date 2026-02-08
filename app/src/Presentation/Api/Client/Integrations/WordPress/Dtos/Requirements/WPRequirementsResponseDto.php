<?php

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Requirements;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements\WPRequirements;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

class WPRequirementsResponseDto extends RestResponseDto
{
    /** @var WPRequirements requested */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public WPRequirements $requirements;

}
