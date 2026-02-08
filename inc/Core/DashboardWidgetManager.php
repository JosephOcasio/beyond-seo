<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Traits\SingletonManager;

/**
 * Class DashboardWidgetManager
 *
 * This class is responsible for managing the dashboard widget.
 */
class DashboardWidgetManager
{
    use SingletonManager;

    /**
     * Initialize the dashboard widget manager.
     */
    public function init(): void
    {
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
    }

    /**
     * Add a dashboard widget.
     */
    public function add_dashboard_widget(): void
    {
        wp_add_dashboard_widget(
            BaseConstants::OPTION_UPSELL_WIDGET_ID,
             // translators: %s is the brand name of the plugin
             sprintf(esc_html__('%s Overview', 'beyond-seo'), RANKINGCOACH_BRAND_NAME),
            [$this, 'display_dashboard_widget']
        );
    }

    /**
     * Display the dashboard widget content.
     */
    public function display_dashboard_widget(): void
    {
        if(WordpressHelpers::isOnboardingCompleted()) {

            if(!CoreHelper::isHighestPaid()) {
                // Create the upsell notification using the dedicated manager
                UpsellNotificationManager::instance()->dashboardNotification();
            }
            else {
                // Remove the upsell widget if the user is on the highest paid plan
                UpsellNotificationManager::instance()->removeDashboardNotification();
            }
        }

        UpsellNotificationManager::instance()->upsellDashboardWidget();
    }
}
