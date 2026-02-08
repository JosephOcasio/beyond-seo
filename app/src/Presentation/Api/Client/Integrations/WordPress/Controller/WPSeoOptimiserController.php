<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Controller;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Adapters\WordPressProvider;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Models\Results\OptimiserResult;
use App\Domain\Integrations\WordPress\Seo\Services\WPSeoOptimiserService;
use App\Infrastructure\Traits\ResponseErrorTrait;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo\SeoAnalysisRequestDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo\SeoDataExtractionResponseDto;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo\SeoOptimiserResponseDto;
use DDD\Presentation\Base\Controller\HttpController;
use DDD\Presentation\Base\OpenApi\Attributes\Summary;
use DDD\Presentation\Base\Router\Routes\Get;
use DDD\Presentation\Base\Router\Routes\Post;
use DDD\Presentation\Base\Router\Routes\Route;
use Exception;
use RankingCoach\Inc\Core\Initializers\Hooks;
use Throwable;

/**
 * Controller for SEO analysis in WordPress
 */
#[Route('optimiser/{postId}')]
class WPSeoOptimiserController extends HttpController
{
    use ResponseErrorTrait;

    /**
     * Extract SEO data
     * @param SeoAnalysisRequestDto $requestDto
     * @param WPSeoOptimiserService $seoOptimiserService
     * @return SeoDataExtractionResponseDto
     * @throws Throwable
     */
    #[Get('/data')]
    #[Summary('Extract SEO Data')]
    public function extractSeoData(
        SeoAnalysisRequestDto $requestDto,
        WPSeoOptimiserService $seoOptimiserService
    ): SeoDataExtractionResponseDto
    {
        $responseDto = new SeoDataExtractionResponseDto();
        $responseDto->format = $requestDto->export;

        if ($requestDto->export === 'csv') {
            $csvData = $seoOptimiserService->extractData(true);
            $responseDto->csv = $csvData;
            return $responseDto;
        }

        $data = $seoOptimiserService->extractData();
        $responseDto->jsonData = $data;
        return $responseDto;
    }

    /**
     * Retrieve the SEO Optimiser
     * @param SeoAnalysisRequestDto $requestDto
     * @param WPSeoOptimiserService $seoOptimiserService
     * @return SeoOptimiserResponseDto
     * @throws Throwable
     */
    #[Get]
    #[Summary('Retrieve SEO Optimiser')]
    public function retrieveSeoOptimiser(
        SeoAnalysisRequestDto $requestDto,
        WPSeoOptimiserService $seoOptimiserService
    ): SeoOptimiserResponseDto
    {
        $response = new SeoOptimiserResponseDto();
        try {
            $optimiser = $seoOptimiserService->analyzeFullOptimiser($requestDto->postId, [], true);
            $response->analyseResult = OptimiserResult::fromOptimiser($optimiser);
        } catch (Exception $e) {
            return $this->processException($e, SeoOptimiserResponseDto::class);
        }

        return $response;
    }

    /**
     * Process the SEO Optimizer
     * @param SeoAnalysisRequestDto $requestDto
     * @param WPSeoOptimiserService $seoOptimiserService
     * @return SeoOptimiserResponseDto
     * @throws Throwable
     */
    #[Post]
    #[Summary('Proceed SEO Optimiser')]
    public function proceedSeoOptimiser(
        SeoAnalysisRequestDto $requestDto,
        WPSeoOptimiserService  $seoOptimiserService
    ): SeoOptimiserResponseDto
    {
        $response = new SeoOptimiserResponseDto();
        $params = $seoOptimiserService->prepareSeoOptimiserQueryParams($requestDto);
        
        try {
            // Check if analysis should be throttled
            $shouldThrottle = apply_filters(
                Hooks::RANKINGCOACH_FILTER_SHOULD_THROTTLE_SEO_ANALYSIS,
                WordPressProvider::shouldThrottleAnalysis($requestDto->postId),
                $requestDto->postId
            );

            if ($shouldThrottle) {
                throw new Exception('SEO analysis is being throttled. Please try again later.');
            }

            $optimiser = $seoOptimiserService->analyzeFullOptimiser($requestDto->postId, $params);

            $seoOptimiserService->calculateAndSaveAverageScore();

            $response->analyseResult = OptimiserResult::fromOptimiser($optimiser);
        } catch (Exception $e) {
            return $this->processException($e, SeoOptimiserResponseDto::class);
        }

        return $response;
    }
}
