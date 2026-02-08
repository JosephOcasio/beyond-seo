<?php
declare(strict_types=1);

namespace App\Presentation\Api\Public\Integrations\WordPress\Controller;

use App\Infrastructure\Traits\ResponseErrorTrait;
use App\Presentation\Api\Public\Integrations\WordPress\Dtos\ServiceSyncResponseDto;
use DDD\Presentation\Base\Controller\HttpController;
use DDD\Presentation\Base\OpenApi\Attributes\Summary;
use DDD\Presentation\Base\Router\Routes\Post;
use DDD\Presentation\Base\Router\Routes\Route;
use Exception;
use RankingCoach\Inc\Core\Api\Content\ContentApiManager;
use Throwable;

/**
 * Class WPPingController
 */
#[Route('sync')]
class WPServiceSyncController extends HttpController
{
    use ResponseErrorTrait;

    /**
     * Retrieve 'ok' response for ping requests.
     * @return ServiceSyncResponseDto
     * @throws Throwable
     */
    #[Post('/keywords')]
    #[Summary('Sync Keywords')]
    public function syncKeywords(): ServiceSyncResponseDto
    {
        $response = new ServiceSyncResponseDto();
        try {
            $result = ContentApiManager::handleKeywordsSynchronization();
            $response->keywords = (object)$result ?? null;
            $response->success = true;
        } catch (Exception $e) {
            $response->success = false;
            $response->message = $e->getMessage();
        }
        return $response;
    }
}
