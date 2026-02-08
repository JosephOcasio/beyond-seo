<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Libs;

use DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * ContentFetcher handles URL content retrieval with static caching
 * for improved performance across multiple SEO analysis operations.
 */
class ContentFetcher
{
    /**
     * Static cache for URL content
     * @var array<string, array>
     */
    private static array $contentCache = [];

    /**
     * Static cache for URLs
     * @var array<string, string>
     */
    private static array $urlCache = [];

    /**
     * HTTP client instance
     */
    private Client $httpClient;

    /**
     * Default request options
     */
    private array $defaultOptions = [
        'timeout' => 10,
        'connect_timeout' => 5,
        'headers' => [
            'User-Agent' => 'SEO Analysis Tool/1.0 (Compatible; +https://example.com/bot)',
        ],
        'allow_redirects' => [
            'max' => 5,
            'strict' => true,
            'referer' => true,
            'protocols' => ['http', 'https'],
            'track_redirects' => true,
        ],
    ];

    /**
     * Constructor
     *
     * @param Client|null $client Optional Guzzle HTTP client instance
     */
    public function __construct(?Client $client = null)
    {
        $devMode = wp_get_environment_type();
        // Add verifying => false if local
        if ($devMode !== 'production') {
            // Disable SSL verification for local development
            $this->defaultOptions['verify'] = false;
        }
        $this->httpClient = $client ?? new Client();
    }

    /**
     * Fetch content from URL with caching
     *
     * @param string $url URL to fetch
     * @param bool $useCache Whether to use cache (default true)
     * @param array $options Additional request options
     * @return array Content data with raw HTML, text content, and DOM
     * @throws RuntimeException When content cannot be fetched
     */
    public function fetchContent(string $url, array $options = [], bool $useCache = true): array
    {
        $cacheKey = $this->generateCacheKey($url);

        // Return from cache if available and requested
        if ($useCache && isset(self::$contentCache[$cacheKey])) {
            return self::$contentCache[$cacheKey];
        }

        try {
            // Merge custom options with defaults
            $requestOptions = array_merge($this->defaultOptions, $options);

            // Execute request
            $response = $this->httpClient->request('GET', $url, $requestOptions);

            // Process response
            $result = $this->processResponse($response, $url);

            // Store in cache
            if ($useCache) {
                self::$contentCache[$cacheKey] = $result;
            }
            self::$urlCache[$cacheKey] = $url;

            return $result;
        } catch (GuzzleException $e) {
            throw new RuntimeException(
                sprintf(
                    'Failed to fetch content from URL: %s. Error: %s',
                    esc_html($url),
                    esc_html($e->getMessage())
                ),
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                $e->getCode(),
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                $e
            );
        }
    }

    /**
     * Process HTTP response into structured content data
     */
    private function processResponse(ResponseInterface $response, string $url): array
    {
        $html = (string)$response->getBody();

        // Create DOM document
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // Suppress HTML parsing errors
        $dom->loadHTML($html);
        libxml_clear_errors();

        // Extract text content
        $textContent = wp_strip_all_tags($html);

        // Extract meta information
        $metaTags = $this->extractMetaTags($dom);

        return [
            'raw_html' => $html,
            'text_content' => $textContent,
            'dom' => $dom,
            'meta' => $metaTags,
            'headers' => $response->getHeaders(),
            'status_code' => $response->getStatusCode(),
            'url' => $url,
            'content_length' => strlen($html),
            'fetch_time' => time(),
        ];
    }

    /**
     * Extract meta tags from DOM
     */
    private function extractMetaTags(DOMDocument $dom): array
    {
        $metaTags = [];
        $metaNodes = $dom->getElementsByTagName('meta');

        foreach ($metaNodes as $meta) {
            $name = $meta->getAttribute('name') ?: $meta->getAttribute('property');
            $content = $meta->getAttribute('content');

            if ($name && $content) {
                $metaTags[$name] = $content;
            }
        }

        return $metaTags;
    }

    /**
     * Generate a cache key from URL
     */
    private function generateCacheKey(string $url): string
    {
        return md5($url);
    }

    /**
     * Get the URL from the cache
     */
    public static function getUrlsFromCache(): array
    {
        return self::$urlCache;
    }

    /**
     * Set URL to cache
     */
    public static function setUrlToCache(string $url): void
    {
        $cacheKey = md5($url);
        self::$urlCache[$cacheKey] = $url;
    }

    /**
     * Clear the content cache
     */
    public static function clearCache(): void
    {
        self::$contentCache = [];
    }

    /**
     * Clear the URL cache
     */
    public static function clearUrlCache(): void
    {
        self::$urlCache = [];
    }

    /**
     * Remove specific URL from cache
     */
    public static function removeFromCache(string $url): void
    {
        $cacheKey = md5($url);
        unset(self::$contentCache[$cacheKey]);
        unset(self::$urlCache[$cacheKey]);
    }
}
