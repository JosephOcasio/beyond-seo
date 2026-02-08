<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\CircuitBreaker\Breakers;

use RankingCoach\Inc\Core\CircuitBreaker\CircuitBreakerInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PDO Extension Circuit Breaker
 * 
 * Monitors PDO extension availability for database operations.
 */
class PDOExtensionBreaker implements CircuitBreakerInterface {
    
    public function get_id(): string {
        return 'pdo_extension';
    }
    
    public function get_name(): string {
        return 'PDO Extension';
    }
    
    public function is_healthy(): bool|int {
        return extension_loaded('pdo') && extension_loaded('pdo_mysql');
    }
    
    public function get_failure_message(): string {
        $missing = [];
        
        if (!extension_loaded('pdo')) {
            $missing[] = 'PDO';
        }
        
        if (!extension_loaded('pdo_mysql')) {
            $missing[] = 'PDO MySQL';
        }

        // translators: %s are the missing PHP extensions, %s is the brand name of the plugin
        return sprintf(
            'Required PHP extension(s) missing: %s. %s requires PDO and PDO MySQL extensions for database operations. The plugin has been temporarily disabled.',
            implode(', ', $missing),
            RANKINGCOACH_BRAND_NAME
        );
    }
    
    public function get_severity(): string {
        return self::SEVERITY_CRITICAL;
    }
    
    public function get_recovery_action(): string {
        return 'Contact your hosting provider to enable the PDO and PDO MySQL extensions in your PHP configuration. The plugin will automatically reactivate once these extensions are available.';
    }
    
    public function get_context(): array {
        return [
            'pdo_loaded' => extension_loaded('pdo'),
            'pdo_mysql_loaded' => extension_loaded('pdo_mysql'),
            'php_version' => PHP_VERSION,
            'available_pdo_drivers' => extension_loaded('pdo') ? \PDO::getAvailableDrivers() : []
        ];
    }
}