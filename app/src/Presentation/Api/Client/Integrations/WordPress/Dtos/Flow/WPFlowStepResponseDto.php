<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Flow;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Steps\WPFlowStep;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class WPFlowStepResponseDto
 */
class WPFlowStepResponseDto extends RestResponseDto
{
    /** @var WPFlowStep The FlowSteps requested */
    #[Parameter(in: Parameter::RESPONSE, required: true)]
    public WPFlowStep $step;
}