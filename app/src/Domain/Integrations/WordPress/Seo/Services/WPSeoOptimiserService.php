<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Services;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Adapters\WordPressProvider;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factors;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operations;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\OptimiserContexts;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\SeoOptimiser;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoContext;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoContexts;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoFactor;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoFactors;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoOperation;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoOperations;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoOptimiser;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Optimiser\InternalDBSeoOptimisers;
use App\Infrastructure\Traits\ResponseErrorTrait;
use App\Presentation\Api\Client\Integrations\WordPress\Dtos\Seo\SeoAnalysisRequestDto;
use DateTime;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Services\Service;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\PostEventsManager;
use ReflectionException;
use Throwable;
use WP_Query;

/**
 * Class WPSeoOptimiserService
 *
 * This class is responsible for managing the SEO optimizer service in WordPress.
 */
class WPSeoOptimiserService extends Service
{
    use ResponseErrorTrait;
    use RcLoggerTrait;

    /** @var string DEFAULT_ENTITY_CLASS The default entity class. */
    public const DEFAULT_ENTITY_CLASS = SeoOptimiser::class;

    /**
     * Get the content provider for fetching content
     * @return WordPressProvider The content provider instance
     */
    public function getContentProvider(): WordPressProvider
    {
        return new WordPressProvider();
    }

    /**
     * Analyze all SEO contexts and return the overall score.
     *
     * @param array $params Additional parameters for analysis
     * @param int $postId The ID of the WordPress post
     * @return SeoOptimiser The total SEO score
     * @throws Throwable
     */
    public function analyzeFullOptimiser(int $postId, array $params = [], bool $skipFullAnalysis = false): SeoOptimiser
    {
        $startTime = microtime(true);

        // Initialize the optimizer
        $seoOptimiser = new SeoOptimiser($postId);

        try {

            if ($skipFullAnalysis) {
                try {
                    return $seoOptimiser->loadSeoOptimiserData();
                } catch (Throwable $e) {
                    $this->logAnalyserError('load_optimiser_data_failed', $postId, $params, $e, $startTime);
                    throw $e;
                }
            }

            // ========== reset stuff ========== //
            try {
                // Set the score to 0
                $seoOptimiser->score = 0;
                // Reset the contexts
                $seoOptimiser->contexts = new OptimiserContexts();
                // Set the analysis date
                $seoOptimiser->analysisDate = new DateTime();
            } catch (Throwable $e) {
                $this->logAnalyserError('optimiser_initialization_failed', $postId, $params, $e, $startTime);
                throw $e;
            }

            // Add all contexts
            try {
                $seoOptimiser->initContexts($params);
            } catch (Throwable $e) {
                $this->logAnalyserError('context_initialization_failed', $postId, $params, $e, $startTime);
                throw $e;
            }
            // Run full analysis
            try {
                $seoOptimiser->analyze();
            } catch (Throwable $e) {
                $this->logAnalyserError('analysis_execution_failed', $postId, $params, $e, $startTime);
                throw $e;
            }

            if ($this->isPartialAnalysis($params)) {
                return $seoOptimiser;
            }
            // Delete old postId analyze data
            try {
                $this->deleteOptimiser($postId);
            } catch (Throwable $e) {
                $this->logAnalyserError('old_data_deletion_failed', $postId, $params, $e, $startTime);
                throw $e;
                // Continue execution despite deletion failure
            }

            // Save to DB
            try {
                $this->getRepository()->save($seoOptimiser);
            } catch (Throwable $e) {
                $this->logAnalyserError('database_save_failed', $postId, $params, $e, $startTime);
                throw $e;
            }

            // Save the score on the post meta (with intelligent caching)
            try {
                $seoOptimiser->saveToPostMeta($seoOptimiser);
            } catch (Throwable $e) {
                $this->logAnalyserError('post_meta_save_failed', $postId, $params, $e, $startTime);
                // Continue execution despite meta save failure
            }
        } catch (Throwable $e) {
            // Final catch-all for any unhandled exceptions
            $this->logAnalyserError('unexpected_error', $postId, $params, $e, $startTime);
        }

        return $seoOptimiser;
    }

    /**
     * Log analyser errors with comprehensive context information
     *
     * @param string $errorType The type of error that occurred
     * @param int $postId The post ID being analyzed
     * @param array $params Analysis parameters
     * @param Throwable $exception The caught exception
     * @param float $startTime The analysis start time
     */
    private function logAnalyserError(string $errorType, int $postId, array $params, Throwable $exception, float $startTime): void
    {
        $executionTime = microtime(true) - $startTime;
        
        $this->log_json([
            'operation_type' => 'seo_analysis',
            'operation_status' => 'error',
            'error_type' => $errorType,
            'post_id' => $postId,
            'post_type' => get_post_type($postId) ?: 'unknown',
            'post_status' => get_post_status($postId) ?: 'unknown',
            'analysis_params' => $params,
            'execution_time' => round($executionTime, 4),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'error_details' => [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace_summary' => array_slice($exception->getTrace(), 0, 3) // Limited trace for readability
            ],
            'context' => [
                'is_partial_analysis' => $this->isPartialAnalysis($params),
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'timestamp' => gmdate('Y-m-d H:i:s')
            ]
        ], 'analyser_errors');
    }

    /**
     * Analyze only a specific SEO factor within a context and return its score.
     *
     * @param int $postId The ID of the WordPress post
     * @return SeoOptimiser|null Score of the factor or null if not found
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws Exception
     * @throws MappingException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function getOptimiserByPostId(int $postId): ?SeoOptimiser
    {
        return $this->getRepository()->getOptimiserByPostId($postId);
    }

    /**
     * Get the repository for the SEO optimizer model.
     *
     * @param bool $multiple Whether to return a single optimizer or multiple optimizers
     * @return InternalDBSeoOptimiser|InternalDBSeoOptimisers The repository instance
     */
    private function getRepository(bool $multiple = false): InternalDBSeoOptimiser|InternalDBSeoOptimisers
    {
        if($multiple) {
            return new InternalDBSeoOptimisers();
        }
        return new InternalDBSeoOptimiser();
    }

    /**
     * Generate a CSV line from an array of fields.
     *
     * @param array $fields The fields to include in the CSV line
     * @param string $separator The separator to use between fields
     * @return string The generated CSV line
     */
    private function csvLine(array $fields, string $separator): string
    {
        return implode(
            $separator,
            array_map(
                static function ($value): string {
                    $value = (string) $value;
                    return '"' . str_replace('"', '""', $value) . '"';
                },
                $fields
            )
        );
    }

    /**
     * Extract SEO data in array or CSV format.
     *
     * @param bool $csv Whether to return data in CSV format
     * @return array|string The extracted SEO data
     */
    public function extractData(bool $csv = false): array|string
    {
        $data = SeoOptimiser::extractData();

        if (!$csv) {
            return $data;
        }

        $separator = ';';
        $lines = [];

        // CSV headers
        $lines[] = $this->csvLine([
            'context class',
            'context name',
            'context description',
            'context weight',
            'factor class',
            'factor name',
            'factor description',
            'factor weight',
            'operation class',
            'operation name',
            'operation description',
            'operation weight',
            'suggestions enum',
            'suggestions title',
            'suggestions descriptions',
        ], $separator);

        foreach ($data as $context) {
            $contextClass = (string) ($context['class'] ?? '');
            $contextName = (string) ($context['name'] ?? '');
            $contextDescription = (string) ($context['description'] ?? '');
            $contextWeight = (string) ($context['weight'] ?? '');
            $factors = $context['factors'] ?? [];

            if (!$factors) {
                $lines[] = $this->csvLine([
                    $contextClass,
                    $contextName,
                    $contextDescription,
                    $contextWeight,
                ], $separator);
                continue;
            }

            foreach ($factors as $factorIdx => $factor) {
                $factorClass = (string) ($factor['class'] ?? '');
                $factorName = (string) ($factor['name'] ?? '');
                $factorDescription = (string) ($factor['description'] ?? '');
                $factorWeight = (string) ($factor['weight'] ?? '');
                $operations = $factor['operations'] ?? [];

                if (!$operations) {
                    $lines[] = $this->csvLine([
                        $factorIdx === 0 ? $contextClass : '',
                        $factorIdx === 0 ? $contextName : '',
                        $factorIdx === 0 ? $contextDescription : '',
                        $factorIdx === 0 ? $contextWeight : '',
                        $factorClass,
                        $factorName,
                        $factorDescription,
                        $factorWeight,
                    ], $separator);
                    continue;
                }

                foreach ($operations as $opIdx => $operation) {
                    $operationClass = (string) ($operation['class'] ?? '');
                    $operationName = (string) ($operation['name'] ?? '');
                    $operationDescription = (string) ($operation['description'] ?? '');
                    $operationWeight = (string) ($operation['weight'] ?? '');
                    $suggestions = $operation['suggestions'] ?? [];

                    if (!$suggestions) {
                        $lines[] = $this->csvLine([
                            $factorIdx === 0 && $opIdx === 0 ? $contextClass : '',
                            $factorIdx === 0 && $opIdx === 0 ? $contextName : '',
                            $factorIdx === 0 && $opIdx === 0 ? $contextDescription : '',
                            $factorIdx === 0 && $opIdx === 0 ? $contextWeight : '',
                            $opIdx === 0 ? $factorClass : '',
                            $opIdx === 0 ? $factorName : '',
                            $opIdx === 0 ? $factorDescription : '',
                            $opIdx === 0 ? $factorWeight : '',
                            $operationClass,
                            $operationName,
                            $operationDescription,
                            $operationWeight,
                        ], $separator);
                        continue;
                    }

                    foreach ($suggestions as $sugIdx => $suggestion) {
                        $lines[] = $this->csvLine([
                            $factorIdx === 0 && $opIdx === 0 && $sugIdx === 0 ? $contextClass : '',
                            $factorIdx === 0 && $opIdx === 0 && $sugIdx === 0 ? $contextName : '',
                            $factorIdx === 0 && $opIdx === 0 && $sugIdx === 0 ? $contextDescription : '',
                            $factorIdx === 0 && $opIdx === 0 && $sugIdx === 0 ? $contextWeight : '',
                            $opIdx === 0 && $sugIdx === 0 ? $factorClass : '',
                            $opIdx === 0 && $sugIdx === 0 ? $factorName : '',
                            $opIdx === 0 && $sugIdx === 0 ? $factorDescription : '',
                            $opIdx === 0 && $sugIdx === 0 ? $factorWeight : '',
                            $sugIdx === 0 ? $operationClass : '',
                            $sugIdx === 0 ? $operationName : '',
                            $sugIdx === 0 ? $operationDescription : '',
                            $sugIdx === 0 ? $operationWeight : '',
                            (string) ($suggestion['enum'] ?? ''),
                            (string) ($suggestion['title'] ?? ''),
                            (string) ($suggestion['description'] ?? ''),
                        ], $separator);
                    }
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Get the repository for the SEO context model.
     *
     * @param bool $multiple Whether to return a single context or multiple contexts
     * @return InternalDBSeoContext|InternalDBSeoContexts The repository instance
     */
    public function getContextRepository(bool $multiple = false): InternalDBSeoContext|InternalDBSeoContexts
    {
        if($multiple) {
            return new InternalDBSeoContexts();
        }
        return new InternalDBSeoContext();
    }

    /**
     * Get the repository for the SEO factor model.
     *
     * @param bool $multiple Whether to return a single context or multiple contexts
     * @return InternalDBSeoFactor|InternalDBSeoFactors The repository instance
     */
    public function getFactorRepository(bool $multiple = false): InternalDBSeoFactor|InternalDBSeoFactors
    {
        if($multiple) {
            return new InternalDBSeoFactors();
        }
        return new InternalDBSeoFactor();
    }

    /**
     * Get the repository for the SEO operation model.
     *
     * @param bool $multiple Whether to return a single context or multiple contexts
     * @return InternalDBSeoOperation|InternalDBSeoOperations The repository instance
     */
    public function getOperationRepository(bool $multiple = false): InternalDBSeoOperation|InternalDBSeoOperations
    {
        if($multiple) {
            return new InternalDBSeoOperations();
        }
        return new InternalDBSeoOperation();
    }

    /**
     * Delete all SEO optimizer data for a given post ID.
     *
     * This method performs a cascading delete of all related entities:
     * 1. Fetches the optimizer for the post
     * 2. Deletes all operations associated with each factor
     * 3. Deletes all factors associated with each context
     * 4. Deletes all contexts associated with the optimizer
     * 5. Deletes the optimizer itself
     *
     * @param int $postId The ID of the post whose SEO data should be deleted
     * @return void
     * @throws ReflectionException
     * @throws NonUniqueResultException
     * @throws InternalErrorException
     * @throws \Doctrine\DBAL\Exception
     */
    public function deleteOptimiser(int $postId): void
    {
        // 1. Get the optimizer to find its analysisId
        $optimiser = $this->getOptimiserByPostId($postId);
        if (!$optimiser) {
            return;
        }

        // 2. Get all contexts for this analysisId
        $contexts = $this->getContextsByAnalysisId($optimiser->id);
        if ($contexts) {
            foreach ($contexts->getElements() as $context) {
                // 3. Get all factors for this contextId
                $factors = $this->getFactorsByContextId($context->id);
                if ($factors) {
                    foreach ($factors->getElements() as $factor) {
                        // 4. Get all operations for this factorId
                        $operations = $this->getOperationsByFactorId($factor->id);
                        if ($operations) {
                            // 5. Delete all operations
                            $this->getOperationRepository(true)->delete($operations);
                        }
                    }
                    // 6. Delete all factors for this context
                    $this->getFactorRepository(true)->delete($factors);
                }
            }
            // 7. Delete all contexts for this analysis
            $this->getContextRepository(true)->delete($contexts);
        }

        // 8. Delete the optimizer itself
        $this->getRepository()->delete($optimiser);
    }

    /**
     * Fetch the domain content for analysis
     *
     * @param string $url
     * @param bool $useCache
     * @return array
     */
    public static function fetchContent(string $url, bool $useCache = true): array {

        static $fetcher = null;

        if ($fetcher === null) {
            $fetcher = new ContentFetcher();
        }

        return $fetcher->fetchContent($url, useCache: $useCache);
    }

    /**
     * Get contexts by analysis ID
     * @param int|string|null $analysisId The analysis IDs to filter by
     * @return OptimiserContexts|null The contexts that match the analysis ID
     * @throws Throwable
     */
    public function getContextsByAnalysisId(int|string|null $analysisId): ?OptimiserContexts
    {
        if ($analysisId === null) {
            return null;
        }
        $repo = new InternalDBSeoContexts();
        return $repo->getByAnalysisId($analysisId);
    }

    /**
     * Get factors by context ID
     * @param int|string|null $contextId The context ID to filter by
     * @return Factors|null The factors that match the context ID
     * @throws Throwable
     */
    public function getFactorsByContextId(int|string|null $contextId): ?Factors
    {
        if ($contextId === null) {
            return null;
        }
        $repo = new InternalDBSeoFactors();
        return $repo->getByContextId($contextId);
    }

    /**
     * Get operations by factor ID
     * @param int|string|null $factorId The factor ID to filter by
     * @return Operations|null The operations that match the factor ID
     * @throws Throwable
     */
    public function getOperationsByFactorId(int|string|null $factorId): ?Operations
    {
        if ($factorId === null) {
            return null;
        }
        $repo = new InternalDBSeoOperations();
        return $repo->getByFactorId($factorId);
    }

    /**
     * Checks if the analysis is partial by verifying if any of the specified keys are present in the params array.
     *
     * @param array $params The parameters to check
     * @return bool Returns true if any of the partial analysis keys are present, false otherwise
     */
    private function isPartialAnalysis(array $params): bool {
        // Define the keys that indicate a partial analysis
        $partialAnalysisKeys = [
            'context',
            'factor',
            'operation',
        ];
        
        // Check if any of the keys exist in the params array
        foreach ($partialAnalysisKeys as $key) {
            if (array_key_exists($key, $params)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * @param SeoAnalysisRequestDto $requestDto
     * @return array
     */
    public function prepareSeoOptimiserQueryParams(SeoAnalysisRequestDto $requestDto): array
    {
        $params = [];
        if (isset($requestDto->context)) {
            $params['context'] = explode(',', $requestDto->context);
        }
        if (isset($requestDto->factor)) {
            $params['factor'] = explode(',', $requestDto->factor);
        }
        if (isset($requestDto->operation)) {
            $params['operation'] = explode(',', $requestDto->operation);
        }
        return $params;
    }

    /**
     * Get list of important pages and posts that need SEO optimization
     * @return array Array of post IDs to optimize
     */
    private function getImportantPagesAndPosts(): array
    {
        $posts = [];
        $maxPosts = 30; // Maximum number of posts to optimize

        // 1. Homepage and blog page (highest priority)
        $frontPageId = (int) get_option('page_on_front');
        $blogPageId = (int) get_option('page_for_posts');

        if ($frontPageId > 0) {
            $posts[] = $frontPageId;
        }

        if ($blogPageId > 0 && $blogPageId !== $frontPageId) {
            $posts[] = $blogPageId;
        }

        // 2. Important static pages (About, Contact, Services, etc.)
        $posts = array_merge($posts, $this->getImportantStaticPages());

        // 3. Most viewed posts (based on comment count as a proxy for engagement)
        $posts = array_merge($posts, $this->getMostViewedPosts());

        // 4. Recent posts with high engagement
        $posts = array_merge($posts, $this->getRecentHighEngagementPosts());

        // 5. Fill remaining slots with recent posts
        $currentCount = count(array_unique($posts));
        $remaining = max(0, $maxPosts - $currentCount);

        if ($remaining > 0) {
            $posts = array_merge($posts, $this->getRecentPosts($remaining, array_unique($posts)));
        }

        // Remove duplicates and invalid IDs
        $posts = array_unique(array_filter($posts, function($postId) {
            return $postId > 0 && get_post_status($postId) === 'publish';
        }));

        // Limit to max posts
        return array_slice($posts, 0, $maxPosts);
    }

    /**
     * Get important static pages
     * @return array Array of page IDs
     */
    private function getImportantStaticPages(): array
    {
        $pageIds = [];

        // Common page slugs that are typically important
        $importantSlugs = [
            'about',
            'about-us',
            'contact',
            'contact-us',
            'services',
            'products',
            'portfolio',
            'team',
            'faq',
            'pricing'
        ];

        foreach ($importantSlugs as $slug) {
            $page = get_page_by_path($slug);
            if ($page && $page->post_status === 'publish') {
                $pageIds[] = $page->ID;
            }
        }

        // Also check for pages in main menu
        $menuPages = $this->getPagesFromMainMenu();
        $pageIds = array_merge($pageIds, $menuPages);

        return array_unique($pageIds);
    }


    /**
     * Get pages from the main navigation menu
     * @return array Array of page IDs
     */
    private function getPagesFromMainMenu(): array
    {
        $pageIds = [];

        // Get primary menu location
        $locations = get_nav_menu_locations();
        if (empty($locations)) {
            return $pageIds;
        }

        // Try common menu location names
        $menuLocationNames = ['primary', 'main', 'header', 'main-menu', 'primary-menu'];
        $menuId = null;

        foreach ($menuLocationNames as $locationName) {
            if (isset($locations[$locationName])) {
                $menuId = $locations[$locationName];
                break;
            }
        }

        if (!$menuId) {
            // If no standard location found, use the first available menu
            $menuId = reset($locations);
        }

        if (!$menuId) {
            return $pageIds;
        }

        // Get menu items
        $menuItems = wp_get_nav_menu_items($menuId);
        if (!$menuItems) {
            return $pageIds;
        }

        foreach ($menuItems as $item) {
            if ($item->object === 'page' && $item->object_id > 0) {
                $pageIds[] = (int) $item->object_id;
            }
        }

        return $pageIds;
    }

    /**
     * Get most viewed posts based on comment count
     * @param int $limit Number of posts to retrieve
     * @return array Array of post IDs
     */
    private function getMostViewedPosts(int $limit = 10): array
    {
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'orderby' => 'comment_count',
            'order' => 'DESC',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];

        $query = new WP_Query($args);
        return $query->posts ?: [];
    }

    /**
     * Get recent posts with high engagement (comments)
     * @param int $limit Number of posts to retrieve
     * @return array Array of post IDs
     */
    private function getRecentHighEngagementPosts(int $limit = 10): array
    {
        $args = [
            'post_type' => ['post'],
            'post_status' => 'publish',
            'orderby' => [
                'comment_count' => 'DESC',
                'date' => 'DESC'
            ],
            'date_query' => [
                [
                    'after' => '3 months ago',
                ],
            ],
            'comment_count' => [
                'value' => 1,
                'compare' => '>='
            ],
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];

        $query = new WP_Query($args);
        return $query->posts ?: [];
    }

    /**
     * Get recent posts
     * @param int $limit Number of posts to retrieve
     * @param array $excludeIds Post IDs to exclude
     * @return array Array of post IDs
     */
    private function getRecentPosts(int $limit = 10, array $excludeIds = []): array
    {
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => $limit,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];

        $query = new WP_Query($args);
        $posts = $query->posts ?: [];

        if (!empty($excludeIds)) {
            $posts = array_diff($posts, $excludeIds);
        }

        // Return only the requested number of posts
        return array_slice(array_values($posts), 0, $limit);
    }

    /**
     * Optimize a batch of posts
     * @param array $postIds Array of post IDs to optimize
     * @throws Throwable
     */
    private function optimizeBatch(array $postIds): void
    {
        foreach ($postIds as $postId) {
            try {
                // Check if post still exists and is published
                if (get_post_status($postId) !== 'publish') {
                    continue;
                }

                // Ignore if post have already the seo-score and it's recent
                $seoScore = get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_SEO_SCORE, true);
                $lastSaveTimestamp = get_post_meta($postId, BaseConstants::OPTION_ANALYSIS_DATE_TIMESTAMP, true);
                
                // Skip if score exists and was saved recently (within the last 24 hours)
                if (!empty($seoScore) && !empty($lastSaveTimestamp)) {
                    $timeSinceLastSave = time() - (int)$lastSaveTimestamp;
                    if ($timeSinceLastSave < 10800) { // 3 hours in seconds
                        continue;
                    }
                }

                $this->analyzeFullOptimiser($postId);

                // Add a small delay to prevent server overload
                if (function_exists('sleep')) {
                    usleep(100000); // 0.1 second delay
                }
            } catch (Throwable $e) {
                // Log error but continue with next post
                $this->log("Batch SEOOptimizer: Error for post $postId: " . $e->getMessage() . ' (File: ' . $e->getFile() . ' Line: ' . $e->getLine() . ')', 'ERROR');
            }
        }
    }

    /**
     * Calculates the average score from all analysed posts and saves comprehensive metrics to options table
     *
     * This method aggregates the scores of all posts that have been analysed,
     * calculates the average, and stores comprehensive analytics data in WordPress options
     * for monitoring, debugging, and performance analysis.
     *
     * @param int $limit Maximum number of records to process (default: 10000)
     * @return void
     * @throws Throwable
     */
    public function calculateAndSaveAverageScore(int $limit = 10000): void
    {
        try {
            // Get comprehensive analysis results
            $analysisResult = PostEventsManager::calculateAverageScoreFromPostMeta($limit);
            update_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_SCORE_AVERAGE, $analysisResult['average_score']);
            update_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_PAGES_COUNT, $analysisResult['count']);
            update_option(BaseConstants::OPTION_ANALYSIS_SCORE_MIN, $analysisResult['min_score'] ?? null);
            update_option(BaseConstants::OPTION_ANALYSIS_SCORE_MAX, $analysisResult['max_score'] ?? null);

            // Log successful calculation
            // $this->log("SEO average score calculation completed successfully: {$analysisResult['average_score']} from {$analysisResult['count']} posts");

        } catch (Throwable $e) {
            /* translators: %s is the error message */
            throw new Exception(sprintf(__('Failed to calculate and save average SEO score: %s', 'beyond-seo'), $e->getMessage()), $e->getCode(), $e); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    /**
     * Run SEO optimization for important pages and posts as a background process
     *
     * @throws Throwable
     */
    public function runSeoOptimizationForImportantPagesAndPosts(): void
    {
        // Get list of important pages and posts
        $posts = $this->getImportantPagesAndPosts();

        // Process in batches of 10 to avoid timeout
        $batches = array_chunk($posts, 5);

        foreach ($batches as $postIds) {
            // Schedule optimization for each batch
            $this->optimizeBatch($postIds);
        }

        $this->calculateAndSaveAverageScore();
    }
}

