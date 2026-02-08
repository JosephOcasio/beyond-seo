<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords\WPKeywords;
use DDD\Presentation\Base\Dtos\RestResponseDto;


/**
 * Class KeywordsMetaTagsResponseDto
 */
class KeywordsResponseDto extends RestResponseDto {

	/** @var WPKeywords|null $keywords The location keywords */
	public ?WPKeywords $keywords = null;
}