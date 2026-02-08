<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class AdvancedSettingsMetaTagsPostRequestDto
 */
class AdvancedSettingsMetaTagsPostRequestDto extends MetaTagsRequestDto
{
    /** @var bool $noindexForPage The advanced settings noindex meta tag */
    #[Parameter(in: Parameter::BODY, required: false)]
    public bool $noindexForPage = false;

    /** @var bool $excludeSitemapForPage The advanced settings for exclude sitemap */
    #[Parameter(in: Parameter::BODY, required: false)]
    public bool $excludeSitemapForPage = false;

    /** @var string|null $canonicalUrl The canonical URL meta tag */
    #[Parameter(in: Parameter::BODY, required: false)]
    public ?string $canonicalUrl = null;

    /** @var bool $disableAutoLinks The advanced settings for disabling auto links */
    #[Parameter(in: Parameter::BODY, required: false)]
    public bool $disableAutoLinks = false;

    /** @var bool $viewportForPage The advanced settings for enabling viewport per page */
    #[Parameter(in: Parameter::BODY, required: false)]
    public bool $viewportForPage = false;
}