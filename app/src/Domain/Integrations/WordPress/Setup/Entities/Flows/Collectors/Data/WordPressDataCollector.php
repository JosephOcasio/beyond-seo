<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\Data;

use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Collectors\WPFlowCollector;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\WPFlowRequirements;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/**
 * Class WordPressDataCollector
 */
class WordPressDataCollector extends WPFlowCollector
{
    public string $collector = WPFlowRequirements::SETUP_COLLECTOR_WORDPRESS;

    /**
     * @return string
     */
    public function businessWebsiteUrl(): string
    {
        // temporarily use the site URL as the business website URL
        $siteUrl = sanitize_url(get_option('siteurl'));
        if(wp_get_environment_type() !== 'production' && wp_get_environment_type() !== 'staging') {
            return RANKINGCOACH_COMMON_DEV_ENVIRONMENT_HOST ?? $siteUrl;
        }

        $url = get_site_url();
        // prevent to send localhost or IP addresses
        if(WordpressHelpers::isLocalhostUrl($url)) {
            return preg_replace('#^http://#', 'https://', RANKINGCOACH_PRODUCTION_ENVIRONMENT_HOST);
        }
        return preg_replace('#^http://#', 'https://', $url);
    }

    /**
     * @return string
     */
    public function businessEmailAddress(): string
    {
        // get the admin email address
        $adminEmail = get_option('admin_email');
        if(wp_get_environment_type() !== 'production' && empty($adminEmail)) {
            return RANKINGCOACH_COMMON_DEV_ENVIRONMENT_EMAIL ?? ('admin@' . wp_parse_url(get_site_url(), PHP_URL_HOST));
        }
        return $adminEmail;
    }
}
