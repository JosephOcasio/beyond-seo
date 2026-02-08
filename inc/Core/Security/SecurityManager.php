<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Security;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/**
 * Class SecurityManager
 * Handles security headers and other security-related functionality
 */
class SecurityManager
{

    /**
     * Initialize security features
     */
    public function __construct()
    {
        $this->initializeHooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function initializeHooks(): void
    {
        // Add security headers early in the WordPress lifecycle
        add_action('admin_init', [$this, 'addAdminSecurityHeaders'], 1);

        // Add headers for AJAX requests
        add_action('wp_ajax_nopriv_*', [$this, 'addAjaxSecurityHeaders'], 1);
        add_action('wp_ajax_*', [$this, 'addAjaxSecurityHeaders'], 1);
    }

    /**
     * Add security headers for frontend
     */
    public function addSecurityHeaders(): void
    {
        // Only add if headers haven't been sent yet
        if (headers_sent()) {
            return;
        }

        $this->setSecurityHeaders();
    }

    /**
     * Add security headers specifically for admin area
     */
    public function addAdminSecurityHeaders(): void
    {
        // Only add headers if in admin, headers are not sent, and correct page is loaded
        if (!is_admin() || headers_sent()) {
            return;
        }

        $currentPage = WordpressHelpers::sanitize_input( 'GET', 'page');

        if ( !str_contains( $currentPage, 'beyondseo' ) || !str_contains( $currentPage, 'beyond-seo' ) ) {
            return;
        }

        $this->setSecurityHeaders();
    }

    /**
     * Add security headers for AJAX requests
     */
    public function addAjaxSecurityHeaders(): void
    {
        if (headers_sent() || !wp_doing_ajax()) {
            return;
        }

        // Restrict CSP only to our plugin AJAX calls (e.g., /wp-json/rankingcoach/)
        $requestUri = WordpressHelpers::sanitize_input( 'SERVER', 'REQUEST_URI' );

        if ( ! str_contains( $requestUri, '/wp-json/rankingcoach' ) ) {
            return;
        }
        $this->setSecurityHeaders();
    }


    /**
     * @param bool $isDevelopment
     * @return string
     */
    private function getCSPPolicy(bool $isDevelopment = false): string {
        if ($isDevelopment) {
            // More permissive for development
            return "default-src 'self' 'unsafe-inline' 'unsafe-eval'; connect-src 'self' ws: wss:;";
        }

        // Production policy
        return "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';";
    }


    /**
     * Set the actual security headers
     *
     */
    private function setSecurityHeaders(): void {
        $cdnSource = 'https://cdn.jsdelivr.net';
        $rankingCoachSources = "https://www.rankingcoach.com https://*.rankingcoach.com";

        $commonCsp = "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' 'unsafe-eval' $rankingCoachSources $cdnSource; "
            . "style-src 'self' 'unsafe-inline' $rankingCoachSources $cdnSource; "
            . "img-src 'self' data: https: blob:; "
            . "font-src 'self' data: $rankingCoachSources $cdnSource; "
            . "connect-src 'self' $rankingCoachSources $cdnSource; "
            . "frame-src 'self' blob: $rankingCoachSources https://mindmup.github.io; "
            . "child-src 'self' blob: $rankingCoachSources https://mindmup.github.io;";

        header("Content-Security-Policy: $commonCsp");

        // Additional security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        header('X-Download-Options: noopen');
        header('X-Permitted-Cross-Domain-Policies: none');
    }
}
