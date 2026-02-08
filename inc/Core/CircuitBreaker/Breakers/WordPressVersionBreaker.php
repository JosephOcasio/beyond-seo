<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\CircuitBreaker\Breakers;

use RankingCoach\Inc\Core\CircuitBreaker\CircuitBreakerInterface;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Version Circuit Breaker
 * 
 * Monitors WordPress version to ensure compatibility.
 */
class WordPressVersionBreaker implements CircuitBreakerInterface {
    
    private const MIN_WP_VERSION = '6.2';
    
    public function get_id(): string {
        return 'wordpress_version';
    }
    
    public function get_name(): string {
        return 'WordPress Version';
    }
    
    public function is_healthy(): bool|int {
        global $wp_version;
        
        if (!isset($wp_version)) {
            return true; // Assume healthy if version not available
        }
        
        return version_compare($wp_version, self::MIN_WP_VERSION, '>=');
    }
    
    public function get_failure_message(): string {
        global $wp_version;
        // translators: %s is the current WordPress version, %s is the brand name of the plugin, %s is the minimum required WordPress version
        return sprintf(
            esc_html('WordPress version %s is not supported. %s requires WordPress %s or higher. The plugin has been temporarily disabled to prevent compatibility issues.'),
            $wp_version ?? 'unknown',
            RANKINGCOACH_BRAND_NAME,
            self::MIN_WP_VERSION
        );
    }
    
    public function get_severity(): string {
        return self::SEVERITY_CRITICAL;
    }
    
    public function get_recovery_action(): string {
        if (!function_exists('admin_url') || !function_exists('esc_url')) {
            return sprintf(
                'Update WordPress to version %s or higher. Go to Dashboard → Updates to update WordPress. The plugin will automatically reactivate once WordPress is updated.',
                self::MIN_WP_VERSION
            );
        }
        
        return sprintf(
            'Update WordPress to version %s or higher. Go to <a href="%s"><strong>Dashboard → Updates</strong></a> to update WordPress. The plugin will automatically reactivate once WordPress is updated.',
            self::MIN_WP_VERSION,
            esc_url(admin_url('update-core.php'))
        );
    }
    
    public function get_context(): array {
        global $wp_version;
        return [
            'current_version' => $wp_version ?? 'unknown',
            'required_version' => self::MIN_WP_VERSION,
            'is_compatible' => $this->is_healthy()
        ];
    }
}