<?php
declare( strict_types=1 );

namespace App\Presentation\Api\Client\Integrations\WordPress\Controller;

use App\Domain\Integrations\WordPress\Seo\Services\WPWebPageService;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\AdvancedSettingsMetaTagsGetResponseDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\AdvancedSettingsMetaTagsPostRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\MetaTagsGetRequestDto;
use DDD\Presentation\Base\Controller\HttpController;
use DDD\Presentation\Base\OpenApi\Attributes\Summary;
use DDD\Presentation\Base\OpenApi\Attributes\Tag;
use DDD\Presentation\Base\Router\Routes\Get;
use DDD\Presentation\Base\Router\Routes\Post;
use DDD\Presentation\Base\Router\Routes\Route;
use Throwable;

/**
 * Class WPSocialMetaTagsController
 */
#[Route('advancedSettings/{postId}')]
#[Tag(name: 'AdvancedSettings', group: 'Modules', description: 'Operations for advanced settings metaTags')]
class WPAdvancedSettingsMetaTagsController extends HttpController {

    /**
     * Get MetaTags
     *
     * @param MetaTagsGetRequestDto $requestDto
     * @param WPWebPageService $webPageService
     * @return AdvancedSettingsMetaTagsGetResponseDto
     */
	#[Get]
	#[Summary('Get all advanced settings metaTags')]
	public function getAllAdvancedSettingsMetaTags(
        MetaTagsGetRequestDto $requestDto,
        WPWebPageService $webPageService
    ): AdvancedSettingsMetaTagsGetResponseDto
	{
        $response = new AdvancedSettingsMetaTagsGetResponseDto();

        /** @var array $metaTags */
        $advancedSettings = $webPageService->getAdvancedSettingsMetaTags($requestDto);

        $response->noindexForPage = (bool)$advancedSettings['noindexForPage'];
        $response->excludeSitemapForPage = (bool)$advancedSettings['excludeSitemapForPage'];
        $response->disableAutoLinks = (bool)$advancedSettings['disableAutoLinks'] ?? false;
        $response->canonicalUrl = $advancedSettings['canonicalUrl'];
        $response->viewportForPage = (bool)$advancedSettings['viewportForPage'] ?? false;

        return $response;
	}

    /**
     * Save MetaTag Keywords
     *
     * @param AdvancedSettingsMetaTagsPostRequestDto $requestDto
     * @param WPWebPageService $webPageService
     * @return AdvancedSettingsMetaTagsGetResponseDto
     * @throws Throwable
     */
    #[Post]
    #[Summary('Update all advanced settings metaTags')]
    public function updateAllAdvancedSettingsMetaTag (
        AdvancedSettingsMetaTagsPostRequestDto $requestDto,
        WPWebPageService $webPageService
    ): AdvancedSettingsMetaTagsGetResponseDto {

        $webPageService->throwErrors = true;
        $response = new AdvancedSettingsMetaTagsGetResponseDto();

        $webPageService->saveAdvancedSettingsMetaTags($requestDto);

        /** @var array $advanceSettings */
        $advanceSettings = $webPageService->getAdvancedSettingsMetaTags($requestDto);

        $response->noindexForPage = (bool)$advanceSettings['noindexForPage'];
        $response->excludeSitemapForPage = (bool)$advanceSettings['excludeSitemapForPage'];
        $response->disableAutoLinks = (bool)$advanceSettings['disableAutoLinks'] ?? false;
        $response->canonicalUrl = $advanceSettings['canonicalUrl'];
        $response->viewportForPage = (bool)$advanceSettings['viewportForPage'] ?? false;

        return $response;
    }
}
