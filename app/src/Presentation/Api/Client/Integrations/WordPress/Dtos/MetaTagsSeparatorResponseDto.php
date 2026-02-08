<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class MetaTagsSeparatorResponseDto
 */
class MetaTagsSeparatorResponseDto extends RestResponseDto {

	/** @var string $separator The separator */
	#[Parameter(in: Parameter::RESPONSE, required: true)]
	public string $separator;
}