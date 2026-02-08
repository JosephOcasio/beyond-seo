<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class SocialMetaTagsGetResponseDto
 */
class SocialImageSourcesResponseDto extends RestResponseDto {

	/** @var array $image_sources The meta tag */
	#[Parameter(in: Parameter::RESPONSE, required: false)]
	public array $image_sources = [];
}
