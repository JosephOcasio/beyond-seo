<?php
declare(strict_types=1);

namespace App\Presentation\Api\Public\Integrations\WordPress\Controller;

use App\Infrastructure\Traits\ResponseErrorTrait;
use App\Presentation\Api\Public\Integrations\WordPress\Dtos\PingGetResponseDto;
use DDD\Presentation\Base\Controller\HttpController;
use DDD\Presentation\Base\OpenApi\Attributes\Summary;
use DDD\Presentation\Base\Router\Routes\Get;
use DDD\Presentation\Base\Router\Routes\Route;

/**
 * Class WPWebhookController
 */
#[Route('webhook')]
class WPWebhookController extends HttpController
{
    use ResponseErrorTrait;

    /**
     * Retrieve 'ok' response for ping requests.
     * @return PingGetResponseDto
     */
    #[Get('/ping')]
    #[Summary('Ping')]
    public function ping(): PingGetResponseDto
    {
        $response = new PingGetResponseDto();
        $response->ok = true;
        return $response;
    }
}
