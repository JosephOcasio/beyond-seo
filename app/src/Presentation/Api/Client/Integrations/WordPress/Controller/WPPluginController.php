<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Controller;
use App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy\WPLegacyAccount;
use App\Domain\Integrations\WordPress\Plugin\Services\WPPluginService;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\PluginInformationRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\PluginInformationResponseDto;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Presentation\Base\Controller\HttpController;
use DDD\Presentation\Base\OpenApi\Attributes\Summary;
use DDD\Presentation\Base\Router\Routes\Post;
use DDD\Presentation\Base\Router\Routes\Route;
use Doctrine\ORM\Mapping\MappingException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use WP_User;

/**
 * Class WPPluginController
 */
#[Route('pluginInformation')]
class WPPluginController extends HttpController
{
    /**
     * Get plugin data / information and current user data
     *
     * @param PluginInformationRequestDto $requestDto
     * @param WPPluginService $wpPluginService
     * @return PluginInformationResponseDto
     */
    #[Post]
    #[Summary('Get plugin information')]
    public function getPluginInformation(
        PluginInformationRequestDto $requestDto,
        WPPluginService $wpPluginService,
    ): PluginInformationResponseDto
    {
        $response = new PluginInformationResponseDto();
        $response->pluginData = $wpPluginService->getPlugin();

        /**
         * @TODO Implement database lazy loading if needed.
         */
//        $currentWpUser = wp_get_current_user();
//        if($currentWpUser instanceof WP_User) {
//            $currentUserId = $currentWpUser->ID;
//            $userInstance = new WPLegacyAccount($currentUserId);
//            $user = $userInstance->getById();
//        }
        $response->userData = null;
        $response->rcAccountId = $wpPluginService->getRankingCoachAccountId();
        $response->rcProjectId = $wpPluginService->getRankingCoachProjectId();
        $response->rcActivationCode = $wpPluginService->getRankingCoachActivationCode();

        // Map subscription codes to display names
        $subscription = $wpPluginService->getRankingCoachSubscription();
        switch ($subscription) {
            case 'seo_wp_free':
            case 'radar_wp_test':
                $response->rcSubscriptionName = 'Free';
                break;
            case 'seo_ai_small':
                $response->rcSubscriptionName = 'Standard';
                break;
            case 'seo_ai_medium':
            case 'seo_ai_medium2025':
                $response->rcSubscriptionName = 'Advanced';
                break;
            case 'seo_ai_large':
                $response->rcSubscriptionName = 'Pro';
                break;
            case 'seo_ai_social':
                $response->rcSubscriptionName = 'Social';
                break;
            case 'annual_360':
            case 'monthly_360':
                $response->rcSubscriptionName = '360';
                break;
            default:
                // Fallback for unknown plans
                $response->rcSubscriptionName = 'Free';
                break;
        }

        $response->rcRemainingKeywords = $wpPluginService->getRankingCoachRemainingKeywords();

        return $response;
    }
}
