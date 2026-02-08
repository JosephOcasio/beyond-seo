<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo;

use DDD\Presentation\Base\Dtos\RequestDto;
use DDD\Presentation\Base\OpenApi\Attributes\Parameter;

/**
 * Class SeoPostRequestDto
 * @package App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo
 *
 * This class is used to handle the request for SEO analysis of a specific post.
 */
class SeoPostIdRequestDto extends RequestDto
{
    /** @var int $postId The post ID to analyze */
    #[Parameter(in: Parameter::PATH, required: true)]
    public int $postId;
}