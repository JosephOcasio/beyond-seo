<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Public\Integrations\WordPress\Dtos;

use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class MetaTagsGetResponseDto
 */
class PingGetResponseDto extends RestResponseDto {

	/** @var bool $ok The response message */
	#[Parameter(in: Parameter::RESPONSE, required: false)]
	public bool $ok = false;
}
