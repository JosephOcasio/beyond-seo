<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\CircuitBreaker\Breakers;

use RankingCoach\Inc\Core\CircuitBreaker\CircuitBreakerInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PHP Version Circuit Breaker
 * 
 * Monitors PHP version to ensure compatibility.
 */
class PHPVersionBreaker implements CircuitBreakerInterface {
    
    private const MIN_PHP_VERSION = '8.0';
    
    public function get_id(): string {
        return 'php_version';
    }
    
    public function get_name(): string {
        return 'PHP Version';
    }
    
    public function is_healthy(): bool|int {
        // Use phpversion() as fallback in case PHP_VERSION is modified
        $current_version = PHP_VERSION;
        $phpversion_result = phpversion();
        
        // Log discrepancy if versions don't match
        if ($current_version !== $phpversion_result) {
            error_log(sprintf(
                'PHPVersionBreaker: PHP_VERSION (%s) differs from phpversion() (%s)',
                $current_version,
                $phpversion_result
            ));
            // Use phpversion() as it's more reliable
            $current_version = $phpversion_result;
        }
        
        return version_compare($current_version, self::MIN_PHP_VERSION, '>=');
    }
    
    public function get_failure_message(): string {
        // translators: %s is the current PHP version, %s is the brand name of the plugin, %s is the minimum required PHP version
        return sprintf(
            esc_html('PHP version %s is not supported. %s requires PHP %s or higher. The plugin has been temporarily disabled to prevent compatibility issues.'),
            PHP_VERSION,
            RANKINGCOACH_BRAND_NAME,
            self::MIN_PHP_VERSION
        );
    }
    
    public function get_severity(): string {
        return self::SEVERITY_CRITICAL;
    }
    
    public function get_recovery_action(): string {
        return sprintf(
            'Contact your hosting provider to upgrade PHP to version %s or higher. The plugin will automatically reactivate once the PHP version requirement is met.',
            self::MIN_PHP_VERSION
        );
    }
    
    public function get_context(): array {
        $php_version_constant = PHP_VERSION;
        $phpversion_function = phpversion();
        
        return [
            'current_version' => $php_version_constant,
            'phpversion_function' => $phpversion_function,
            'required_version' => self::MIN_PHP_VERSION,
            'version_compare_result' => version_compare($phpversion_function, self::MIN_PHP_VERSION),
            'versions_match' => $php_version_constant === $phpversion_function,
            'php_version_id' => PHP_VERSION_ID ?? 'undefined'
        ];
    }
}
