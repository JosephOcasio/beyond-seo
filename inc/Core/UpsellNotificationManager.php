<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use WP_Screen;

/**
 * Class UpsellNotificationManager
 *
 * This class is responsible for managing upsell notifications across the plugin.
 * It provides methods to create and display upsell notifications in different contexts.
 */
class UpsellNotificationManager
{
    use RcLoggerTrait;

    /**
     * Singleton instance of NotificationManager.
     *
     * @var UpsellNotificationManager|null
     */
    private static ?UpsellNotificationManager $instance = null;

    /**
     * WordPress admin screen IDs for post and page list screens.
     */
    private const SCREEN_EDIT_POST = 'edit-post';
    private const SCREEN_EDIT_PAGE = 'edit-page';

    /**
     * Constructor notification manager.
     *
     * @return self
     */
    public function __construct() {
        // Initialize the notification manager
        $this->init();
        return $this;
    }

    /**
     * Returns the singleton instance of UpsellNotificationManager.
     * @return UpsellNotificationManager|null
     */
    public static function instance(): ?UpsellNotificationManager {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the upsell notification manager.
     */
    public function init(): void
    {
        // Hook into WordPress actions to display notifications on specific screens
        if(WordpressHelpers::isOnboardingCompleted()) {

            if(CoreHelper::isHighestPaid()) {
                return;
            }

            add_action('current_screen', [$this, 'maybeDisplayScreenNotifications']);
        }
    }

    /**
     * Check the current screen and display appropriate notifications.
     * 
     * @param WP_Screen $current_screen The current WordPress admin screen.
     * @return void
     */
    public function maybeDisplayScreenNotifications(WP_Screen $current_screen): void
    {
        // Display notifications based on the current screen ID
        switch ($current_screen->id) {
            case self::SCREEN_EDIT_POST:
                $this->postListNotification();
                break;
            case self::SCREEN_EDIT_PAGE:
                $this->pageListNotification();
                break;
        }
    }

    /**
     * Create a notification to upsell the plugin to a PRO version.
     *
     * @param string $notification_id The ID for the notification (for dismissal tracking)
     * @param string|null $screen Optional screen context to display the notification on
     * @return void
     */
    public function upsellNotification(string $notification_id = 'rankingcoach-pro-upsell', ?string $screen = Notification::SCREEN_DASHBOARD): void
    {
        // Check if the notification has been dismissed
        $notificationManager = NotificationManager::instance();

        if (!$notificationManager->has_notification($notification_id)) {
            // Build HTML without whitespace between tags to prevent wpautop from adding empty paragraphs
            $html = '<div class="rankingcoach-upsell-notice">';
            $html .= '<div class="rankingcoach-upsell-content">';
            $html .= '<h4>' . esc_html__('They show up everywhere. You can too.', 'beyond-seo') . '</h4>';
            $html .= '<p>' . esc_html__('Your competitors are everywhere, not just on a website. Without multi-channel visibility, you`re losing customers. Upgrade now to stand out where it counts: Google, directories, social platforms, and reviews.', 'beyond-seo') . '</p>';
            $html .= '<div class="rankingcoach-upsell-actions">';
            $html .= '<a href="' . AdminManager::getPageUrl(AdminManager::PAGE_UPSELL ) . '" class="button button-primary">';
            $html .= esc_html__('Boost my visibility', 'beyond-seo');
            $html .= '</a>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';

            $styles = '<style>
                .rankingcoach-upsell-notice {
                    display: flex;
                    align-items: flex-start;
                    gap: 15px;
                    padding: 16px 10px;
                }
                .rankingcoach-upsell-content {
                    flex: 1;
                    font-family: sans-serif;
                    color: #333;
                } 
                .rankingcoach-upsell-content h4 {
                    margin: 0 0 12px;
                    font-size: 20px;
                    font-weight: 300;
                }
                .rankingcoach-upsell-content p {
                    margin: 8px 0 16px;
                    line-height: 1.5;
                }
                .rankingcoach-upsell-actions {
                    margin-top: 12px;
                }
            </style>';

            // Combine HTML and styles
            $message = $html . $styles;

            // Add the notification with raw HTML flag to prevent WordPress from processing it
            $notificationManager->add(
                $message,
                [
                    'id' => $notification_id,
                    'type' => Notification::INFO,
                    'screen' => $screen,
                    'dismissible' => true,
                    'persistent' => true,
                    'raw_html' => true, // Add this flag if NotificationManager supports it
                ]
            );
        }
    }

    /**
     * Get the upsell widget content HTML.
     *
     * @return void
     */
    public function upsellDashboardWidget(): void
    {
        // Check if onboarding is completed
        $isOnboardingCompleted = get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP, false) == true &&
            !empty(get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE, null));

        // Start building the widget HTML
        $html = '<div class="rankingcoach-dashboard-widget">';

        // If onboarding is not completed, show the onboarding message
        if (!$isOnboardingCompleted) {
            $html .= '<div class="rankingcoach-onboarding-message">';
            $html .= '<div class="rankingcoach-onboarding-content">';
            
            // Icon for onboarding message
            $html .= '<div class="rankingcoach-onboarding-icon">';
            $html .= '<svg width="80" height="80" viewBox="0 0 120 121" fill="none" xmlns="http://www.w3.org/2000/svg">
                <g clip-path="url(#clip0_2019_281851)">
                  <path
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                    d="M60.183 119.826C93.2192 119.826 120 93.0449 120 60.0087C120 54.5954 119.281 49.3501 117.933 44.363H108.021H102.028C100.65 50.9885 94.7781 55.9667 87.7437 55.9667H76.2786V26.7866H87.7437C94.7783 26.7866 100.65 31.7652 102.028 38.3911H108.021H115.975C107.308 16.0392 85.5963 0.191406 60.183 0.191406C34.7698 0.191406 13.058 16.0392 4.39133 38.3911H9.69203H15.6845C17.0623 31.7652 22.9342 26.7866 29.9688 26.7866H41.434V55.9667H29.9688C22.9344 55.9667 17.0628 50.9885 15.6847 44.363H9.69203H2.43264C1.0848 49.3501 0.365723 54.5954 0.365723 60.0087C0.365723 93.0449 27.1468 119.826 60.183 119.826Z"
                    fill="#E5E9F0"
                  />
                  <ellipse cx="42.8207" cy="41.3752" rx="8.3598" ry="14.5901" fill="#C9D1DE" />
                  <ellipse
                    cx="8.3598"
                    cy="14.5901"
                    rx="8.3598"
                    ry="14.5901"
                    transform="matrix(-1 0 0 1 83.25 26.7852)"
                    fill="#C9D1DE"
                  />
                  <path
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                    d="M74.8904 54.4027C70.9806 54.4027 67.811 48.5701 67.811 41.3752C67.811 34.1803 70.9806 28.3477 74.8904 28.3477C75.2484 28.3477 75.6001 28.3965 75.9438 28.4909C72.5336 29.4271 69.9178 34.839 69.9178 41.3752C69.9178 47.9113 72.5336 53.3233 75.9438 54.2594C75.6001 54.3538 75.2484 54.4027 74.8904 54.4027Z"
                    fill="#A3B1C7"
                  />
                  <path
                    d="M59.2388 57.25C59.5816 56.6726 60.4175 56.6726 60.7603 57.25L82.2735 93.4827C82.6237 94.0725 82.1986 94.8192 81.5127 94.8192H38.4864C37.8005 94.8192 37.3755 94.0725 37.7257 93.4827L59.2388 57.25Z"
                    fill="#8699B6"
                  />
                  <path
                    d="M57.1827 73.7169C57.0662 72.6687 57.8867 71.752 58.9414 71.752H61.0577C62.1124 71.752 62.9329 72.6687 62.8164 73.7169L61.8207 82.6786H58.1784L57.1827 73.7169Z"
                    fill="white"
                  />
                  <path d="M58.1787 86.3203H61.8209V89.9625H58.1787V86.3203Z" fill="white" />
                </g>
                <defs>
                  <clipPath id="clip0_2019_281851">
                    <rect width="120" height="120" fill="white" transform="translate(0 0.205078)" />
                  </clipPath>
                </defs>
              </svg>';
            $html .= '</div>';
            
            // Text content
            $html .= '<div class="rankingcoach-onboarding-text">';
            $html .= '<h3>' . esc_html__('Onboarding not finished', 'beyond-seo') . '</h3>';
            $html .= '<p>' . esc_html__('You can\'t use all plugin features because you didn\'t finish the onboarding. Complete it now to start using the plugin.', 'beyond-seo') . '</p>';
            $html .= '<a href="' . esc_url(admin_url('admin.php?page=rankingcoach-onboarding')) . '" class="button button-primary rankingcoach-onboarding-button">' .
                esc_html__('Finish Onboarding', 'beyond-seo') . '</a>';
            $html .= '</div>';
            
            $html .= '</div>';
            $html .= '</div>';
        } else {
            // Get website analysis data from WordPress options
            $websiteScore = get_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_SCORE_AVERAGE, 0);
            $pagesCount = get_option(BaseConstants::OPTION_ANALYSIS_WEBSITE_PAGES_COUNT, 0);
            $scoreMin = get_option(BaseConstants::OPTION_ANALYSIS_SCORE_MIN, 0);
            $scoreMax = get_option(BaseConstants::OPTION_ANALYSIS_SCORE_MAX, 100);

            // Calculate score percentage for progress bar
            $scorePercentage = $scoreMax > 0 ? min(100, max(0, ($websiteScore / 100) * 100)) : 0;

            // Determine score color based on value
            $scoreColor = '#28a745'; // Default green
            if ($scorePercentage < 30) {
                $scoreColor = '#dc3545'; // Red for low scores
            } elseif ($scorePercentage < 80) {
                $scoreColor = '#ffc107'; // Yellow/amber for medium scores
            }

            // Website score section
            $html .= '<div class="rankingcoach-score-section">';
            $html .= '<h3>' . esc_html__('Website Analysis Overview', 'beyond-seo') . '</h3>';

            
            // Score visualization
            $html .= '<div class="rankingcoach-score-visualization">';
            $html .= '<div class="rankingcoach-score-circle" style="--score-color: ' . esc_attr($scoreColor) . '; --score-percentage: ' . esc_attr($scorePercentage) . '%;">';
            $html .= '<div class="score-circle-inner">';
            $html .= '<span class="rankingcoach-score-label">' . esc_html__('Current Score', 'beyond-seo') . '</span>';
            /* translators: %d is the website score */
            $html .= '<span class="rankingcoach-score-value">' . esc_html( sprintf( '%d', $websiteScore )) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
            
            // Mini cards for stats
            $html .= '<div class="rankingcoach-mini-cards">';
            $html .= '<div class="rankingcoach-mini-card">';
            $html .= '<span class="mini-card-label">' . esc_html__('Min Score', 'beyond-seo') . '</span>';
            $html .= '<span class="mini-card-value">' . esc_html($scoreMin) . '</span>';
            $html .= '</div>';
            $html .= '<div class="rankingcoach-mini-card">';
            $html .= '<span class="mini-card-label">' . esc_html__('Max Score', 'beyond-seo') . '</span>';
            $html .= '<span class="mini-card-value">' . esc_html($scoreMax) . '</span>';
            $html .= '</div>';
            $html .= '<div class="rankingcoach-mini-card">';
            $html .= '<span class="mini-card-label">' . esc_html__('Pages', 'beyond-seo') . '</span>';
            $html .= '<span class="mini-card-value">' . esc_html($pagesCount) . '</span>';
            $html .= '</div>';
            $html .= '</div>';
            
            $html .= '</div>';

            if(!CoreHelper::isHighestPaid()) {
                // Upsell section - only add if we have the upsell data
                $html .= '<div class="rankingcoach-upsell-section">';
                $html .= '<p class="rankingcoach-upsell-message">' . esc_html__('Your competitors are everywhere, not just on a website. Without multi-channel visibility, you`re losing customers. Upgrade now to stand out where it counts: Google, directories, social platforms, and reviews.', 'beyond-seo') . '</p>';
                $html .= '<a href="' . esc_url(AdminManager::getPageUrl(AdminManager::PAGE_UPSELL)) . '" class="button button-primary rankingcoach-upsell-button">' .
                    esc_html__('Boost my visibility', 'beyond-seo') . '</a>';
                $html .= '</div>';
            }
        }

        // Add CSS styles
        $style = '<style>
            .rankingcoach-dashboard-widget {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                color: #333;
                padding: 0;
                margin: 0;
                border-radius: 12px;
                overflow: hidden;
            }
            
            /* Onboarding Message Styling */
            .rankingcoach-onboarding-message {
                padding: 20px;
                border-radius: 12px;
                background: linear-gradient(135deg, #f5f7fa 0%, #e4e7eb 100%);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
                border: 1px solid #e0e0e0;
            }
            .rankingcoach-onboarding-content {
                display: flex;
                align-items: center;
                gap: 20px;
            }
            .rankingcoach-onboarding-icon {
                flex-shrink: 0;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .rankingcoach-onboarding-text {
                flex: 1;
            }
            .rankingcoach-onboarding-text h3 {
                margin-top: 0;
                margin-bottom: 10px !important;
                font-size: 18px !important;
                font-weight: 600 !important;
                color: #1565c0 !important;
            }
            .rankingcoach-onboarding-text p {
                font-size: 14px;
                line-height: 1.6;
                color: #444;
                margin-bottom: 15px;
            }
            .rankingcoach-onboarding-button {
                display: inline-block;
                background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%) !important;
                color: white !important;
                border: none !important;
                padding: 8px 16px !important;
                border-radius: 4px !important;
                font-weight: 500 !important;
                text-decoration: none !important;
                transition: all 0.3s ease !important;
                box-shadow: 0 2px 5px rgba(25, 118, 210, 0.3) !important;
            }
            .rankingcoach-onboarding-button:hover {
                background: linear-gradient(135deg, #1565c0 0%, #1976d2 100%) !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 4px 8px rgba(25, 118, 210, 0.4) !important;
            }
            .rankingcoach-score-section {
                margin-bottom: 20px;
                text-align: center;
                padding: 20px;
                border-radius: 12px;
                margin-bottom: 15px;
            }
            .rankingcoach-score-section h3 {
                margin-bottom: 20px !important;
                font-size: 18px !important;
                font-weight: 600 !important;
                color: #1565c0 !important;
                text-shadow: 0 1px 2px rgba(21, 101, 192, 0.1);
            }
            
            /* Mini Cards Styling */
            .rankingcoach-mini-cards {
                display: flex;
                justify-content: center;
                gap: 12px;
                margin-top: 25px;
                flex-wrap: wrap;
            }
            .rankingcoach-mini-card {
                display: flex;
                flex-direction: column;
                align-items: center;
                background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%);
                padding: 12px 16px;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
                min-width: 80px;
                transform: translateY(0);
                transition: all 0.3s ease;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }
            .rankingcoach-mini-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(25, 118, 210, 0.4);
            }
            .mini-card-label {
                font-size: 10px;
                font-weight: 500;
                color: rgba(255, 255, 255, 0.9);
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 4px;
            }
            .mini-card-value {
                font-size: 18px;
                font-weight: 700;
                color: #ffffff;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            }
            
            /* Score Visualization */
            .rankingcoach-score-visualization {
                display: flex;
                justify-content: center;
                align-items: center;
                margin-bottom: 15px;
            }
            .rankingcoach-score-circle {
                position: relative;
                width: 140px;
                height: 140px;
                border-radius: 50%;
                background: conic-gradient(
                    from 0deg,
                    var(--score-color) 0% var(--score-percentage),
                    #e3f2fd var(--score-percentage) 100%
                );
                display: flex;
                justify-content: center;
                align-items: center;
                box-shadow: 0 8px 25px rgba(25, 118, 210, 0.2);
                margin: 0 auto;
                position: relative;
                overflow: hidden;
            }
            .rankingcoach-score-circle::before {
                content: "";
                position: absolute;
                width: 120px;
                height: 120px;
                border-radius: 50%;
                background: linear-gradient(135deg, #ffffff 0%, #f8fbff 100%);
                box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.1);
            }
            .score-circle-inner {
                position: relative;
                z-index: 2;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
            }
            .rankingcoach-score-value {
                font-size: 28px;
                font-weight: 700;
                color: var(--score-color);
                margin: 2px 0;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            }
            .rankingcoach-score-label {
                font-size: 11px;
                color: #666;
                font-weight: 500;
                margin-bottom: 2px;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }
            .rankingcoach-score-percentage {
                font-size: 12px;
                color: #888;
                font-weight: 600;
                margin-top: 2px;
            }
            
            /* Upsell Section */
            .rankingcoach-upsell-section {
                background: linear-gradient(135deg, #e3f2fd 0%, #f8fbff 100%);
                border-left: 4px solid #1976d2;
                padding: 18px;
                border-radius: 8px;
                margin-top: 15px;
                box-shadow: 0 2px 8px rgba(25, 118, 210, 0.1);
            }
            .rankingcoach-upsell-message {
                margin: 0 0 15px;
                font-size: 14px;
                line-height: 1.6;
                color: #37474f;
            }
            .rankingcoach-upsell-button {
                display: inline-block;
                text-decoration: none;
                background: linear-gradient(135deg, #1976d2 0%, #42a5f5 100%);
                color: white !important;
                padding: 10px 20px;
                border-radius: 6px;
                font-weight: 600;
                box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3);
                transition: all 0.3s ease;
                border: none;
            }
            .rankingcoach-upsell-button:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(25, 118, 210, 0.4);
                color: white !important;
            }
            
            /* Responsive Design */
            @media (max-width: 600px) {
                .rankingcoach-mini-cards {
                    gap: 8px;
                }
                .rankingcoach-mini-card {
                    min-width: 70px;
                    padding: 10px 12px;
                }
                .rankingcoach-score-circle {
                    width: 120px;
                    height: 120px;
                }
                .rankingcoach-score-circle::before {
                    width: 100px;
                    height: 100px;
                }
                .rankingcoach-onboarding-content {
                    flex-direction: column;
                    text-align: center;
                }
                .rankingcoach-onboarding-text {
                    text-align: center;
                }
            }
        </style>';

        $html .= '</div>';

        echo wp_kses_post($html);
        echo $style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Create a notification for a specific admin page.
     *
     * @param string $page_slug The admin page slug
     * @param string $notification_id Custom notification ID
     * @return void
     */
    public function adminPageNotification(string $page_slug, string $notification_id = ''): void
    {
        if (empty($notification_id)) {
            $notification_id = 'rankingcoach-pro-upsell-' . $page_slug;
        }

        // Create notification for the specific admin page
        $this->upsellNotification($notification_id, $page_slug);
    }

    /**
     * Create a notification for the dashboard.
     *
     * @return void
     */
    public function dashboardNotification(): void
    {
        $this->upsellNotification('rankingcoach-upsell-dashboard', Notification::SCREEN_DASHBOARD);
    }

    /**
     * Create a notification for the post list screen.
     *
     * @return void
     */
    public function postListNotification(): void
    {
        $this->adminPageNotification(self::SCREEN_EDIT_POST);
    }

    /**
     * Create a notification for the page list screen.
     *
     * @return void
     */
    public function pageListNotification(): void
    {
        $this->adminPageNotification(self::SCREEN_EDIT_PAGE);
    }

    /**
     * Remove the dashboard upsell notification.
     *
     * @return void
     */
    public function removeDashboardNotification(): void
    {
        NotificationManager::instance()->remove_by_id('rankingcoach-upsell-dashboard');
    }
}
