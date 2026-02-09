<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Admin;

/**
 * SettingsPage
 *
 * Manages plugin settings admin page and forms.
 * Provides UI for configuring plugin behavior.
 *
 * @since 1.1.6
 */
class SettingsPage
{
    /**
     * Admin page slug
     */
    private const PAGE_SLUG = 'beyond-seo-settings';

    /**
     * Nonce action
     */
    private const NONCE_ACTION = 'beyond_seo_settings_nonce';

    /**
     * Hook into WordPress
     */
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addAdminMenu']);
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueScripts']);
    }

    /**
     * Add settings page to admin menu
     */
    public static function addAdminMenu(): void
    {
        add_submenu_page(
            'tools.php', // Parent menu: Tools
            'BeyondSEO Settings',
            'BeyondSEO Settings',
            'manage_options',
            self::PAGE_SLUG,
            [self::class, 'renderSettingsPage']
        );
    }

    /**
     * Register plugin settings
     */
    public static function registerSettings(): void
    {
        // General settings
        register_setting('beyond_seo_general', 'beyond_seo_enabled');
        register_setting('beyond_seo_general', 'beyond_seo_debug_mode');
        register_setting('beyond_seo_general', 'beyond_seo_cache_enabled');
        register_setting('beyond_seo_general', 'beyond_seo_cache_ttl', [
            'sanitize_callback' => 'absint',
        ]);

        // SEO settings
        register_setting('beyond_seo_seo', 'beyond_seo_enable_xml_sitemap');
        register_setting('beyond_seo_seo', 'beyond_seo_enable_breadcrumbs');
        register_setting('beyond_seo_seo', 'beyond_seo_enable_schema_markup');
        register_setting('beyond_seo_seo', 'beyond_seo_default_separator', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        // API settings
        register_setting('beyond_seo_api', 'beyond_seo_api_enabled');
        register_setting('beyond_seo_api', 'beyond_seo_api_rate_limit', [
            'sanitize_callback' => 'absint',
        ]);

        // Add settings sections
        add_settings_section(
            'beyond_seo_general',
            'General Settings',
            [self::class, 'renderGeneralSection'],
            'beyond_seo_general'
        );

        add_settings_section(
            'beyond_seo_seo',
            'SEO Settings',
            [self::class, 'renderSeoSection'],
            'beyond_seo_seo'
        );

        add_settings_section(
            'beyond_seo_api',
            'API Settings',
            [self::class, 'renderApiSection'],
            'beyond_seo_api'
        );

        // Add settings fields
        self::addGeneralFields();
        self::addSeoFields();
        self::addApiFields();
    }

    /**
     * Enqueue admin styles and scripts
     */
    public static function enqueueScripts(string $hook): void
    {
        if ($hook !== 'tools_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'beyond-seo-settings',
            plugins_url('assets/css/admin-settings.css', RANKINGCOACH_FILE),
            [],
            RANKINGCOACH_VERSION
        );

        wp_enqueue_script(
            'beyond-seo-settings',
            plugins_url('assets/js/admin-settings.js', RANKINGCOACH_FILE),
            ['jquery'],
            RANKINGCOACH_VERSION,
            true
        );

        wp_localize_script('beyond-seo-settings', 'BeyondSEOSettings', [
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'apiUrl' => rest_url('beyond-seo/v1/settings'),
        ]);
    }

    /**
     * Render settings page
     */
    public static function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('BeyondSEO Settings', 'beyond-seo'); ?></h1>
            
            <div class="beyond-seo-settings-container">
                <nav class="nav-tab-wrapper wp-clearfix">
                    <a href="#general" class="nav-tab nav-tab-active" data-tab="general">
                        <?php esc_html_e('General', 'beyond-seo'); ?>
                    </a>
                    <a href="#seo" class="nav-tab" data-tab="seo">
                        <?php esc_html_e('SEO', 'beyond-seo'); ?>
                    </a>
                    <a href="#api" class="nav-tab" data-tab="api">
                        <?php esc_html_e('API', 'beyond-seo'); ?>
                    </a>
                </nav>

                <form method="post" action="options.php" class="settings-form">
                    <?php wp_nonce_field(self::NONCE_ACTION); ?>

                    <!-- General Settings Tab -->
                    <div class="tab-content" id="general" style="display: block;">
                        <?php
                        do_settings_sections('beyond_seo_general');
                        submit_button();
                        ?>
                    </div>

                    <!-- SEO Settings Tab -->
                    <div class="tab-content" id="seo" style="display: none;">
                        <?php
                        do_settings_sections('beyond_seo_seo');
                        submit_button();
                        ?>
                    </div>

                    <!-- API Settings Tab -->
                    <div class="tab-content" id="api" style="display: none;">
                        <?php
                        do_settings_sections('beyond_seo_api');
                        submit_button();
                        ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render general settings section description
     */
    public static function renderGeneralSection(): void
    {
        echo '<p>' . esc_html__('Configure general plugin behavior and caching.', 'beyond-seo') . '</p>';
    }

    /**
     * Render SEO settings section description
     */
    public static function renderSeoSection(): void
    {
        echo '<p>' . esc_html__('Configure SEO features and output.', 'beyond-seo') . '</p>';
    }

    /**
     * Render API settings section description
     */
    public static function renderApiSection(): void
    {
        echo '<p>' . esc_html__('Configure API access and rate limiting.', 'beyond-seo') . '</p>';
    }

    /**
     * Add general setting fields
     */
    private static function addGeneralFields(): void
    {
        add_settings_field(
            'beyond_seo_enabled',
            __('Enable Plugin', 'beyond-seo'),
            [self::class, 'renderCheckboxField'],
            'beyond_seo_general',
            'beyond_seo_general',
            ['name' => 'beyond_seo_enabled', 'label' => 'Enable BeyondSEO functionality']
        );

        add_settings_field(
            'beyond_seo_debug_mode',
            __('Debug Mode', 'beyond-seo'),
            [self::class, 'renderCheckboxField'],
            'beyond_seo_general',
            'beyond_seo_general',
            ['name' => 'beyond_seo_debug_mode', 'label' => 'Enable debug logging (not for production)']
        );

        add_settings_field(
            'beyond_seo_cache_enabled',
            __('Enable Caching', 'beyond-seo'),
            [self::class, 'renderCheckboxField'],
            'beyond_seo_general',
            'beyond_seo_general',
            ['name' => 'beyond_seo_cache_enabled', 'label' => 'Cache API responses']
        );

        add_settings_field(
            'beyond_seo_cache_ttl',
            __('Cache TTL (seconds)', 'beyond-seo'),
            [self::class, 'renderNumberField'],
            'beyond_seo_general',
            'beyond_seo_general',
            ['name' => 'beyond_seo_cache_ttl', 'min' => 60, 'max' => 86400, 'step' => 60]
        );
    }

    /**
     * Add SEO setting fields
     */
    private static function addSeoFields(): void
    {
        add_settings_field(
            'beyond_seo_enable_xml_sitemap',
            __('XML Sitemap', 'beyond-seo'),
            [self::class, 'renderCheckboxField'],
            'beyond_seo_seo',
            'beyond_seo_seo',
            ['name' => 'beyond_seo_enable_xml_sitemap', 'label' => 'Generate XML sitemap']
        );

        add_settings_field(
            'beyond_seo_enable_breadcrumbs',
            __('Breadcrumbs', 'beyond-seo'),
            [self::class, 'renderCheckboxField'],
            'beyond_seo_seo',
            'beyond_seo_seo',
            ['name' => 'beyond_seo_enable_breadcrumbs', 'label' => 'Show breadcrumb navigation']
        );

        add_settings_field(
            'beyond_seo_enable_schema_markup',
            __('Schema Markup', 'beyond-seo'),
            [self::class, 'renderCheckboxField'],
            'beyond_seo_seo',
            'beyond_seo_seo',
            ['name' => 'beyond_seo_enable_schema_markup', 'label' => 'Add schema.org markup']
        );

        add_settings_field(
            'beyond_seo_default_separator',
            __('Title Separator', 'beyond-seo'),
            [self::class, 'renderTextField'],
            'beyond_seo_seo',
            'beyond_seo_seo',
            ['name' => 'beyond_seo_default_separator', 'placeholder' => '-']
        );
    }

    /**
     * Add API setting fields
     */
    private static function addApiFields(): void
    {
        add_settings_field(
            'beyond_seo_api_enabled',
            __('Enable REST API', 'beyond-seo'),
            [self::class, 'renderCheckboxField'],
            'beyond_seo_api',
            'beyond_seo_api',
            ['name' => 'beyond_seo_api_enabled', 'label' => 'Enable REST API endpoints']
        );

        add_settings_field(
            'beyond_seo_api_rate_limit',
            __('API Rate Limit', 'beyond-seo'),
            [self::class, 'renderNumberField'],
            'beyond_seo_api',
            'beyond_seo_api',
            ['name' => 'beyond_seo_api_rate_limit', 'min' => 100, 'max' => 10000, 'step' => 100]
        );
    }

    /**
     * Render checkbox field
     */
    public static function renderCheckboxField(array $args): void
    {
        $value = get_option($args['name']);
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($args['name']); ?>" 
                   value="1" <?php checked($value, 1); ?> />
            <?php echo esc_html($args['label']); ?>
        </label>
        <?php
    }

    /**
     * Render text field
     */
    public static function renderTextField(array $args): void
    {
        $value = get_option($args['name']);
        ?>
        <input type="text" name="<?php echo esc_attr($args['name']); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               placeholder="<?php echo esc_attr($args['placeholder'] ?? ''); ?>" 
               class="regular-text" />
        <?php
    }

    /**
     * Render number field
     */
    public static function renderNumberField(array $args): void
    {
        $value = get_option($args['name']);
        ?>
        <input type="number" name="<?php echo esc_attr($args['name']); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               min="<?php echo esc_attr($args['min']); ?>" 
               max="<?php echo esc_attr($args['max']); ?>" 
               step="<?php echo esc_attr($args['step'] ?? 1); ?>" 
               class="small-text" />
        <?php
    }
}
