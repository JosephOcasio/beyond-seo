<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use App\Domain\Integrations\WordPress\Setup\Entities\WPSetup;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class OnboardingDataResponseDto
 *
 * @property bool $internal
 * @property bool $external
 */
class OnboardingDataResponseDto extends RestResponseDto {

    /** @var WPSetup $setupStep The plugin onboarding data, including the internal and external onboarding status */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
	public WPSetup $setupData;
}
