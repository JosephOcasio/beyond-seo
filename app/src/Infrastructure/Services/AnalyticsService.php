<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use DateTime;

/**
 * AnalyticsService
 *
 * Tracks and reports plugin usage metrics and performance.
 *
 * @since 1.2.0
 */
class AnalyticsService
{
    /**
     * Analytics data namespace
     */
    private const ANALYTICS_KEY = 'beyond_seo_analytics';

    /**
     * Record a metric event
     *
     * @param string $metric Metric name (e.g., 'api_call', 'setting_updated')
     * @param array<string, mixed> $data Event data
     */
    public static function recordEvent(string $metric, array $data = []): void
    {
        $analytics = self::getAnalytics();

        $event = [
            'timestamp' => (new DateTime())->getTimestamp(),
            'metric' => $metric,
            'user_id' => get_current_user_id(),
            'data' => $data,
        ];

        if (!isset($analytics['events'])) {
            $analytics['events'] = [];
        }

        // Keep only last 1000 events (rotate old data)
        $analytics['events'][] = $event;
        if (count($analytics['events']) > 1000) {
            $analytics['events'] = array_slice($analytics['events'], -1000);
        }

        update_option(self::ANALYTICS_KEY, $analytics);
    }

    /**
     * Get analytics summary
     *
     * @return array<string, mixed>
     */
    public static function getSummary(): array
    {
        $analytics = self::getAnalytics();
        $events = $analytics['events'] ?? [];

        $metrics = [
            'total_events' => count($events),
            'total_api_calls' => 0,
            'total_settings_updated' => 0,
            'api_calls_today' => 0,
            'settings_updated_today' => 0,
            'last_api_call' => null,
            'last_settings_update' => null,
            'top_endpoints' => [],
        ];

        $today = (new DateTime())->format('Y-m-d');

        foreach ($events as $event) {
            $event_date = date('Y-m-d', $event['timestamp']);

            if ($event['metric'] === 'api_call') {
                $metrics['total_api_calls']++;
                if ($event_date === $today) {
                    $metrics['api_calls_today']++;
                }
                $metrics['last_api_call'] = $event['timestamp'];

                // Track top endpoints
                $endpoint = $event['data']['endpoint'] ?? 'unknown';
                $metrics['top_endpoints'][$endpoint] = ($metrics['top_endpoints'][$endpoint] ?? 0) + 1;
            }

            if ($event['metric'] === 'setting_updated') {
                $metrics['total_settings_updated']++;
                if ($event_date === $today) {
                    $metrics['settings_updated_today']++;
                }
                $metrics['last_settings_update'] = $event['timestamp'];
            }
        }

        // Sort endpoints by frequency
        arsort($metrics['top_endpoints']);
        $metrics['top_endpoints'] = array_slice($metrics['top_endpoints'], 0, 10, true);

        return $metrics;
    }

    /**
     * Get performance metrics
     *
     * @return array<string, mixed>
     */
    public static function getPerformance(): array
    {
        $analytics = self::getAnalytics();

        return [
            'cache_hits' => $analytics['cache_hits'] ?? 0,
            'cache_misses' => $analytics['cache_misses'] ?? 0,
            'average_response_time' => $analytics['avg_response_time'] ?? 0,
            'error_count' => $analytics['error_count'] ?? 0,
            'last_error' => $analytics['last_error'] ?? null,
        ];
    }

    /**
     * Record cache hit
     */
    public static function recordCacheHit(): void
    {
        $analytics = self::getAnalytics();
        $analytics['cache_hits'] = ($analytics['cache_hits'] ?? 0) + 1;
        update_option(self::ANALYTICS_KEY, $analytics);
    }

    /**
     * Record cache miss
     */
    public static function recordCacheMiss(): void
    {
        $analytics = self::getAnalytics();
        $analytics['cache_misses'] = ($analytics['cache_misses'] ?? 0) + 1;
        update_option(self::ANALYTICS_KEY, $analytics);
    }

    /**
     * Record error
     */
    public static function recordError(string $error): void
    {
        $analytics = self::getAnalytics();
        $analytics['error_count'] = ($analytics['error_count'] ?? 0) + 1;
        $analytics['last_error'] = $error;
        update_option(self::ANALYTICS_KEY, $analytics);
    }

    /**
     * Clear analytics (retention policy)
     */
    public static function purgeOldData(int $days = 90): int
    {
        $analytics = self::getAnalytics();
        $cutoff = (new DateTime("-{$days} days"))->getTimestamp();
        $original_count = count($analytics['events'] ?? []);

        $analytics['events'] = array_filter(
            $analytics['events'] ?? [],
            fn($event) => $event['timestamp'] >= $cutoff
        );

        update_option(self::ANALYTICS_KEY, $analytics);

        return $original_count - count($analytics['events']);
    }

    /**
     * Get all analytics data
     *
     * @return array<string, mixed>
     */
    private static function getAnalytics(): array
    {
        $data = get_option(self::ANALYTICS_KEY);
        return is_array($data) ? $data : [];
    }
}
