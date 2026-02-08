<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Admin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Admin\AdminPage;
use RankingCoach\Inc\Core\Api\Feedback\FeedbackApiManager;
use RankingCoach\Inc\Traits\SingletonManager;

/**
 * Class FeedbackPage
 * Handles the deactivate-feedback modal orchestration and feedback submission.
 *
 * @method FeedbackPage getInstance(): static
 */
class FeedbackPage extends AdminPage
{
    use SingletonManager;

    /**
     * The page slug used for routing.
     *
     * @var string
     */
    public string $name = 'feedback';

    /**
     * Holds a reference to the AdminManager instance.
     *
     * @var AdminManager|null
     */
    public static ?AdminManager $managerInstance = null;

    /**
     * Cached plugin basename for matching deactivate actions.
     */
    private string $pluginBasename;

    public function __construct()
    {
        $this->pluginBasename = defined('RANKINGCOACH_PLUGIN_BASENAME')
            ? (string) RANKINGCOACH_PLUGIN_BASENAME
            : plugin_basename(RANKINGCOACH_FILE);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_rankingcoach_submit_deactivate_feedback', [$this, 'handle_feedback']);

        parent::__construct();
    }

    public function page_name(): string
    {
        return $this->name;
    }

    public function page_content(?callable $failCallback = null): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('Deactivate feedback', 'beyond-seo') . '</h1></div>';
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'plugins.php' && $hook !== 'network_plugins.php') {
            return;
        }

        $version = defined('RANKINGCOACH_VERSION') ? RANKINGCOACH_VERSION : '1.0.0';

        wp_enqueue_style(
            'rankingcoach-deactivate-feedback-style',
            RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/css/deactivate-feedback.css',
            [],
            $version
        );

        wp_enqueue_script(
            'rankingcoach-deactivate-feedback',
            RANKINGCOACH_PLUGIN_ADMIN_URL . 'assets/js/deactivate-feedback.js',
            ['jquery'],
            $version,
            true
        );

        wp_localize_script(
            'rankingcoach-deactivate-feedback',
            'RankingCoachDeactivateFeedback',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rankingcoach_deactivate_feedback_nonce'),
                'pluginFile' => rawurlencode($this->pluginBasename),
                'pluginSlug' => dirname($this->pluginBasename),
                'pluginName' => defined('RANKINGCOACH_BRAND_NAME') ? (string) RANKINGCOACH_BRAND_NAME : 'rankingCoach',
                'strings' => [
                    'modalTitle' => esc_html__('Help Us Improve', 'beyond-seo'),
                    'modalSubtitle' => sprintf(
                        // translators: %s is the brand/plugin name shown in the deactivation modal.
                        esc_html__(
                            'Would you mind telling us why you are deactivating %s? Your feedback helps us make the plugin better.',
                            'beyond-seo'
                        ),
                        RANKINGCOACH_BRAND_NAME
                    ),                    'submit' => esc_html__('Submit and Deactivate', 'beyond-seo'),
                    'skip' => esc_html__('Skip and Deactivate', 'beyond-seo'),
                    'errorNoOption' => esc_html__('Please select a reason', 'beyond-seo'),
                    'sending' => esc_html__('Submitting...', 'beyond-seo'),
                    'deleteData' => esc_html__('Delete all project data and settings upon deactivation', 'beyond-seo'),
                ],
                'reasons' => [
                    [
                        'code' => 'no_need',
                        'label' => esc_html__("I no longer need the plugin", 'beyond-seo'),
                        'details' => '',
                    ],
                    [
                        'code' => 'switched_plugin',
                        'label' => esc_html__("I'm switching to a different plugin", 'beyond-seo'),
                        'details' => '',
                    ],
                    [
                        'code' => 'errors',
                        'label' => esc_html__("I couldn't get the plugin to work", 'beyond-seo'),
                        'details' => esc_html__('Please describe the issue you faced', 'beyond-seo'),
                    ],
                    [
                        'code' => 'temporary',
                        'label' => esc_html__('It’s a temporary deactivation', 'beyond-seo'),
                        'details' => '',
                    ],
                    [
                        'code' => 'other',
                        'label' => esc_html__('Other', 'beyond-seo'),
                        'details' => esc_html__('Please share the reason', 'beyond-seo'),
                    ],
                ],
            ]
        );
    }

    public function handle_feedback(): void
    {
        check_ajax_referer('rankingcoach_deactivate_feedback_nonce', 'nonce');

        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(['message' => esc_html__('You do not have permission to perform this action.', 'beyond-seo')], 403);
        }

        $reasonCode = isset($_POST['reasonCode']) ? sanitize_text_field(wp_unslash($_POST['reasonCode'])) : '';
        $feedbackText = isset($_POST['feedbackText']) ? sanitize_textarea_field(wp_unslash($_POST['feedbackText'])) : '';
        $deleteProjectValue = isset($_POST['deleteProject']) ? sanitize_text_field(wp_unslash($_POST['deleteProject'])) : '';
        $deleteProject = $deleteProjectValue === 'true' || $deleteProjectValue === '1';

        // Submit feedback to the API
        try {
            $feedbackApiManager = FeedbackApiManager::getInstance([], null, false);
            $feedbackApiManager->submitFeedback(
                $reasonCode,
                $feedbackText,
                $deleteProject,
                false
            );
        } catch (\Exception $e) {
            // Log the error but don't fail the deactivation
            error_log('Failed to submit feedback to API: ' . $e->getMessage());
        } finally {
            wp_send_json_success(['message' => esc_html__('Thank you for your feedback!', 'beyond-seo')]);
        }
    }
}
