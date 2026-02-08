<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Flow;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowSteps;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPFlowStepsResponseDto
 */
class WPFlowStepsResponseDto extends RestResponseDto
{
    /** @var WPFlowSteps The FlowSteps requested */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public WPFlowSteps $steps;

    /** @var bool Indicates if the address requirement should be prefilled */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public bool $prefillAddressRequirement = false;
}