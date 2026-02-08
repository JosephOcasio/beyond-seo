<?php

namespace RankingCoach\Inc\Core\Frontend\ViteApp\Assets;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\Frontend\ViteApp\ReactApp;
use RankingCoach\Inc\Core\Helpers\CoreHelper;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Plugin\RankingCoachPlugin;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use ReflectionException;

class Assets {
    use Resolver;

    /**
     * @action elementor/editor/after_enqueue_scripts
     */
    public function frontElementor( string $pages ): void {

        // handle the case when pro version is requested
        $value = WordpressHelpers::sanitize_input('GET', 'proVersion', [FILTER_SANITIZE_FULL_SPECIAL_CHARS], null, 'bool');
        $proVersion = wp_validate_boolean($value);

        // Enqueue all available styles and scripts
        $dependencies = $this->solveDependencies( ['main.tsx', 'App.tsx'] );
        $dependencies = array_unique($dependencies);
        foreach ( $dependencies as $idx => $dependency ) {
            wp_enqueue_style( 'vite_app_css_' . $idx, $dependency, [], ReactApp::get()?->config()->get( 'version' ) );
        }

        // Enqueue the main script and localize the data
        wp_enqueue_script( 'rankingcoach-react-vite-script', $this->resolve( 'main.tsx' ), ['wp-i18n'], ReactApp::get()?->config()->get( 'version' ), true );

        // Load translations for the script (must be called AFTER wp_enqueue_script)
        wp_set_script_translations(
            'rankingcoach-react-vite-script',
            'rankingcoach',
            plugin_dir_path(RANKINGCOACH_FILE) . 'languages/json'
        );

        $locale = WordpressHelpers::get_wp_locale();
        $localeParts = preg_split('/[_-]/', $locale);
        $countryShortCode = strtoupper($localeParts[1] ?? $localeParts[0]);

        $rankingCoachReactData = [
            'loadNextComponents' => implode(',', ReactApp::$loadComponents),
            'currentUserId' => get_current_user_id(),
            'proVersion' => $proVersion,
            'locale' => $locale,
            'version' => RANKINGCOACH_VERSION,
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'baseurl' => get_site_url(),
            'adminurl' => admin_url( 'admin.php' ),
            'endpoint' => esc_url_raw( rest_url( RANKINGCOACH_REST_API_BASE ) ),
            'appEndpoint' => esc_url_raw( rest_url( RANKINGCOACH_REST_APP_BASE ) ),
            'security' => wp_create_nonce( 'rankingcoach-ajax-nonce' ),
            'restNonce' => ( wp_installing() && ! is_multisite() ) ? '' : wp_create_nonce( 'wp_rest' ),
            'isEditingPost' => false,
            'isAddingPost' => false,
            'currentPostType' => '',
            'currentPostId' => null,
            'isUpgradedVersion' => RankingCoachPlugin::isUpgradedVersion(),
            'subscriptionVersion' => get_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION, null),
            'isActivationCompleted' => WordpressHelpers::isActivationCompleted(),
            'isOnboardingCompleted' => WordpressHelpers::isOnboardingCompleted(),
            'brandName' => RANKINGCOACH_BRAND_NAME,
            'brandSlug' => RANKINGCOACH_BRAND_SLUG,
            'countryShortCode' => $countryShortCode
        ];

        if (WordpressHelpers::is_edit_post_context()) {
            // Get the post ID (if editing an existing post).
            $post_id = WordpressHelpers::sanitize_input( 'GET', 'post' );
            if ( $post_id ) {
                $post_type = get_post_type( $post_id );
            } else {
                $post_type = null;
            }
            // Add context-specific keys to the global object when editing a post or page.
            $rankingCoachReactData['isEditingPost'] = true;
            $rankingCoachReactData['currentPostType'] = $post_type;
            $rankingCoachReactData['currentPostId'] = $post_id;
        }

        // Localize the script with data
        wp_localize_script( 'rankingcoach-react-vite-script', 'rankingCoachReactData', $rankingCoachReactData);
    }

    /**
     * @action admin_enqueue_scripts
     */
    public function front( string $pages ): void {
        // Prevent duplicate execution
        static $executed = [];
        
        if ( isset( $executed[$pages] ) ) {
            return;
        }
        
        // avoid it to load the assets in other pages
        if ( ! in_array( $pages, ALLOWED_RANKINGCOACH_PAGES ) ) {
            return;
        }
        
        // Mark as executed for this page
        $executed[$pages] = true;

        // handle the case when pro version is requested
        $proVersion = wp_validate_boolean(WordpressHelpers::sanitize_input('GET', 'proVersion', [FILTER_SANITIZE_FULL_SPECIAL_CHARS], null, 'bool'));

        // Enqueue all available styles and scripts
        $dependencies = $this->solveDependencies( ['main.tsx', 'App.tsx'] );
        $dependencies = array_unique($dependencies);
        foreach ( $dependencies as $idx => $dependency ) {
            wp_enqueue_style( 'vite_app_css_' . $idx, $dependency, [], ReactApp::get()?->config()->get( 'version' ) );
        }

        // Enqueue the editor script only if we are in the context of adding or editing a post
        if(WordpressHelpers::is_add_post_context() || WordpressHelpers::is_edit_post_context()) {
            wp_enqueue_script('rankingcoach-react-editor-script', plugin_dir_url(RANKINGCOACH_PLUGIN_ADMIN_DIR) . 'Admin/assets/js/admin-editor.js', ['wp-blocks', 'wp-editor', 'wp-data'], RANKINGCOACH_VERSION, true);
        }

        // Enqueue the main script and localize the data
        wp_enqueue_script( 'rankingcoach-react-vite-script', $this->resolve( 'main.tsx' ), ['wp-i18n'], ReactApp::get()?->config()->get( 'version' ), true );

        // Load translations for the script (must be called AFTER wp_enqueue_script)
        wp_set_script_translations(
            'rankingcoach-react-vite-script',
            'rankingcoach',
            plugin_dir_path(RANKINGCOACH_FILE) . 'languages/json'
        );

        $locale = WordpressHelpers::get_wp_locale();
        $localeParts = preg_split('/[_-]/', $locale);
        $countryShortCode = strtoupper($localeParts[1] ?? $localeParts[0]);

        $rankingCoachReactData = [
            'loadNextComponents' => implode(',', ReactApp::$loadComponents),
            'currentUserId' => get_current_user_id(),
            'proVersion' => $proVersion,
            'locale' => $locale,
            'version' => RANKINGCOACH_VERSION,
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'baseurl' => get_site_url(),
            'adminurl' => admin_url( 'admin.php' ),
            'endpoint' => esc_url_raw( rest_url( RANKINGCOACH_REST_API_BASE ) ),
            'appEndpoint' => esc_url_raw( rest_url( RANKINGCOACH_REST_APP_BASE ) ),
            'security' => wp_create_nonce( 'rankingcoach-ajax-nonce' ),
            'restNonce' => ( wp_installing() && ! is_multisite() ) ? '' : wp_create_nonce( 'wp_rest' ),
            'isEditingPost' => false,
            'currentPostType' => '',
            'currentPostId' => null,
            'isUpgradedVersion' => RankingCoachPlugin::isUpgradedVersion(),
            'subscriptionVersion' => get_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION, null),
            'subscriptionLevel' => CoreHelper::getCurrentPlanLevel(),
            'isHighestPlan' => CoreHelper::isHighestPaid(),
            'isActivationCompleted' => WordpressHelpers::isActivationCompleted(),
            'isOnboardingCompleted' => WordpressHelpers::isOnboardingCompleted(),
            'brandName' => RANKINGCOACH_BRAND_NAME,
            'brandSlug' => RANKINGCOACH_BRAND_SLUG,
            'iframeMapUrl' => esc_url_raw($this->generateMapUrl()),
            'countryShortCode' => $countryShortCode
        ];

        $post_id = WordpressHelpers::sanitize_input('GET', 'post');
        $post_type = $post_id ? get_post_type($post_id) : null;

        if (WordpressHelpers::is_edit_post_context()) {
            // Get the post ID (if editing an existing post).
            // Add context-specific keys to the global object when editing a post or page.
            $rankingCoachReactData['isEditingPost'] = true;
            $rankingCoachReactData['isAddingPost'] = false;
            $rankingCoachReactData['currentPostType'] = $post_type;
            $rankingCoachReactData['currentPostId'] = $post_id;
        }

        if(WordpressHelpers::is_add_post_context()) {
            // Add context-specific keys to the global object when adding a new post or page.
            $rankingCoachReactData['isAddingPost'] = true;
            $rankingCoachReactData['currentPostType'] = $post_type;
        }

        // Localize the script with data
	    wp_localize_script( 'rankingcoach-react-vite-script', 'rankingCoachReactData', $rankingCoachReactData);
    }

    /**
     * Convert locale format from en_US to en-us for map URL
     * @param string $locale
     * @return string
     */
    private function formatLocaleForMapUrl(string $locale): string {
        // Convert underscore to hyphen and make lowercase
        return strtolower(str_replace('_', '-', $locale));
    }

    /**
     * Iframe MAP URL Generator
     * @param bool $reload
     * @return string
     * @throws HttpApiException
     * @throws ReflectionException
     */
    private function generateMapUrl(bool $reload = false): string {
        // Load the configuration
        $config = require RANKINGCOACH_PLUGIN_APP_DIR . 'config/app/externalIntegrations.php';
        $rawLocale = WordpressHelpers::get_wp_locale() ?? 'en_US';
        $locale = $this->formatLocaleForMapUrl($rawLocale);

        // if activation is completed
        if(!WordpressHelpers::isActivationCompleted()) {
            return '';
        }

        /** @var TokensManager $tokensManager */
        $tokensManager = TokensManager::instance();
        $accessToken = $tokensManager->getStoredAccessToken();
        if($reload) {
            $refreshToken = $tokensManager->getStoredRefreshToken();
            $tokensManager->generateAndSaveAccessToken($refreshToken);
            $accessToken = $tokensManager->getStoredAccessToken();
        }

        $projectBase = $config[RankingCoachPlugin::isProductionMode() ? 'liveEnv' : 'devEnv'];
        $projectId = WordpressHelpers::getProjectId();
        $iframeMapUrl = sprintf( $config['iframeMapUrl'], $projectBase, $projectId, $accessToken, $projectBase, $locale );
        return $iframeMapUrl;
    }
}
