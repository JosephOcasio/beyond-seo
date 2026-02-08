<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageDescriptionMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageKeywordsMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags\WPWebPageTitleMetaTag;
use DDD\Presentation\Base\Dtos\RestResponseDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class MetaTagsGetResponseDto
 */
class MetaTagsGetResponseDto extends RestResponseDto {

	/** @var WPWebPageTitleMetaTag|null $title The meta tag */
	#[Parameter(in: Parameter::RESPONSE, required: false)]
	public ?WPWebPageTitleMetaTag $title = null;

    /** @var WPWebPageDescriptionMetaTag|null $description The meta tag */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?WPWebPageDescriptionMetaTag $description = null;

    /** @var WPWebPageKeywordsMetaTag|null $keywords The meta tag */
    #[Parameter(in: Parameter::RESPONSE, required: false)]
    public ?WPWebPageKeywordsMetaTag $keywords = null;

    /**
     * MetaTagsGetResponseDto constructor.
     * @param WPWebPageTitleMetaTag|null $title
     * @param WPWebPageDescriptionMetaTag|null $description
     * @param WPWebPageKeywordsMetaTag|null $keywords
     */
    public function __construct(?WPWebPageTitleMetaTag $title = null, ?WPWebPageDescriptionMetaTag $description = null, ?WPWebPageKeywordsMetaTag $keywords = null) {
        $this->title = $title;
        $this->description = $description;
        $this->keywords = $keywords;

        parent::__construct();
    }
}