<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageKeywordsMetaTag;
use DDD\Presentation\Base\Dtos\RestResponseDto;


/**
 * Class KeywordsMetaTagsResponseDto
 */
class KeywordsMetaTagsResponseDto extends RestResponseDto {

	/** @var WPWebPageKeywordsMetaTag|null $keywords The keywords */
	public ?WPWebPageKeywordsMetaTag $keywords = null;
}