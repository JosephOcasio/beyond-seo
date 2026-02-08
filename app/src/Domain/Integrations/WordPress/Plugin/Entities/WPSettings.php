<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Plugin\Entities;

use DDD\Domain\Base\Entities\ValueObject;
use RankingCoach\Inc\Core\Base\BaseConstants;

/**
 * Class WPSettings
 */
class WPSettings extends ValueObject
{
    /** @var string The option key for database storage */
    protected string $option_key = BaseConstants::OPTION_PLUGIN_SETTINGS;

    /** @var bool Allows the plugin to automatically onboard new users */
    public bool $allow_auto_onboarding = true;

    /** @var bool Allows the plugin to sync keywords to the RankingCoach platform */
    public bool $allow_sync_keywords_to_rankingcoach = true;

    /** @var bool Enable Account Sync Job */
    public bool $enable_account_sync = true;

    /** @var bool Cleans old logs */
    public bool $enable_log_cleanup = true;

    /** @var bool Allows the SEO Optimiser to run on saved posts or pages or any other post type */
    public bool $allow_seo_optimiser_on_saved_posts = true;

    /** @var bool Allows the plugin to collect anonymous usage data */
    public bool $enable_user_action_and_event_logs_sharing = true;

    /** @var bool Enables the use of the WP-Cron service for scheduling tasks */
    public bool $enable_wp_cron_service = false;

    /** @var bool Remove plugin settings on deactivation */
    public bool $remove_settings_on_deactivation = false;

    /** @var bool Enable the broken link checker job */
    public bool $enable_broken_link_checker_job = true;

    /** @var bool Disable WordPress heartbeat admin-ajax service */
    public bool $disable_wp_heartbeat_service = false;

    /** @var bool Enable the viewport meta tag for responsive design */
    public bool $enable_viewport = false;

    /** @var bool Open rC dashboard in a new tab */
    public bool $open_rc_dashboard_in_new_tab = false;

    /** @var string Google verification code for site ownership verification */
    public string $google_verification_code = '';

    /** @var string Bing verification code for site ownership verification */
    public string $bing_verification_code = '';

    // Separators
    public array $separators = [
        'pipe'       => '|',
        'dash'       => '-',
        'en_dash'    => '–',
        'em_dash'    => '—',
        'dot'        => '·',
        'colon'      => ':',
        'bullet'     => '•',
        'angle_double' => '»',
        'angle_single' => '›',
        'tilde'      => '~',
        'asterisk'   => '*',
        'plus'       => '+',
        'slash'      => '/',
        'backslash'  => '\\',
        'equals'     => '=',
        'ellipsis'   => '…',
    ];

    /**
     * @var array<string,string> List of allowed countries (ISO 3166-1 alpha-2 codes, UPPERCASE)
     */
    public array $allowed_countries = [
        'US' => 'United States',
        'CA' => 'Canada',
        'GB' => 'United Kingdom',
        'IE' => 'Ireland',
        'DE' => 'Germany',
        'AT' => 'Austria',
        'CH' => 'Switzerland',
        'FR' => 'France',
        'BE' => 'Belgium',
        'NL' => 'Netherlands',
        'LU' => 'Luxembourg',
        'MC' => 'Monaco',
        'IT' => 'Italy',
        'ES' => 'Spain',
        'PT' => 'Portugal',
        'AD' => 'Andorra',
        'DK' => 'Denmark',
        'SE' => 'Sweden',
        'NO' => 'Norway',
        'FI' => 'Finland',
        'IS' => 'Iceland',
        'PL' => 'Poland',
        'CZ' => 'Czech Republic',
        'SK' => 'Slovakia',
        'HU' => 'Hungary',
        'RO' => 'Romania',
        'BG' => 'Bulgaria',
        'GR' => 'Greece',
        'EE' => 'Estonia',
        'LV' => 'Latvia',
        'LT' => 'Lithuania',
        'SI' => 'Slovenia',
        'HR' => 'Croatia',
        'RS' => 'Serbia',
        'BA' => 'Bosnia and Herzegovina',
        'MK' => 'North Macedonia',
        'AL' => 'Albania',
        'TR' => 'Turkey',
        'CY' => 'Cyprus',
        'MT' => 'Malta',
        'AU' => 'Australia',
        'NZ' => 'New Zealand',
        'MX' => 'Mexico',
        'AR' => 'Argentina',
        'BR' => 'Brazil',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'PE' => 'Peru',
        'UY' => 'Uruguay',
        'ZA' => 'South Africa',
        'AE' => 'United Arab Emirates',
        'SA' => 'Saudi Arabia',
        'IL' => 'Israel',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'MY' => 'Malaysia',
        'PH' => 'Philippines',
        'SG' => 'Singapore',
        'TH' => 'Thailand',
        'JP' => 'Japan',
    ];

    /**
     * @var array<string,string> List of supported languages (ISO 639-1 codes, lowercase)
     */
    public array $supported_languages = [
        'en' => 'English',
        'de' => 'German',
        'fr' => 'French',
        'it' => 'Italian',
        'es' => 'Spanish',
        'pt' => 'Portuguese',
        'pl' => 'Polish',
        'nl' => 'Dutch',
    ];

    /**
     * @var array<string,string> List of supported locales (language + region, lowercase keys)
     */
    public array $supported_locales = [
        'en'    => 'en_US', // default English → US
        'en_gb' => 'en_GB', // UK English
        'en_ca' => 'en_CA', // Canada English
        'de'    => 'de_DE',
        'de_at' => 'de_AT', // Austria German
        'fr'    => 'fr_FR',
        'fr_ca' => 'fr_CA', // French Canada
        'fr_mq' => 'fr_MQ', // Martinique
        'fr_yt' => 'fr_YT', // Mayotte
        'fr_re' => 'fr_RE', // Réunion
        'fr_gp' => 'fr_GP', // Guadeloupe
        'fr_gf' => 'fr_GF', // French Guiana
        'it'    => 'it_IT',
        'es'    => 'es_ES',
        'es_mx' => 'es_MX', // Mexico Spanish
        'pt'    => 'pt_PT',
        'pl'    => 'pl_PL',
        'nl'    => 'nl_NL',
    ];

    // Cache
    public int $account_details_cache_seconds = 10800;
    public int $gmb_categories_cache_seconds = 10800;

    // Tables column name
    public string $top_toolbar_menu_name = RANKINGCOACH_BRAND_NAME;

    // General SEO Analysis
    public bool $seo_analysis = true;
    public int $seo_score_threshold = 76;
    public bool $enable_readability_check = true;

    // Keyword Optimization
    public int $focus_keyword_limit = 5;
    public bool $focus_keyword_analysis = true;

    // Google Analytics Integration
    public bool $google_analytics_integration = true;
    public string $ga_tracking_id = '';

    // Schema Markup
    public bool $enable_schema_markup = true;
    public string $default_schema_type = 'BlogPosting';
    public string $site_represents = 'organization';
    public bool $site_links = true;

    // Organization Knowledge Graph Settings
    public string $organisation_or_person_name = '';
    public string $organisation_email = '';
    public string $organisation_phone = '';
    public string $organisation_logo = '';
    public string $organisation_founding_date = '';
    public array $organisation_number_of_employees = [
        'isRange' => false,
        'from' => 0,
        'to' => 0,
        'number' => 0
    ];


    public bool $run_shortcodes = false;
    public string $website_alternate_name = '';

    // Person Knowledge Graph Settings
    public string $person_manual_name = '';
    public string $person_manual_image = '';

    // Organization Social Media Profiles  
    public string $organization_social_facebook = '';
    public string $organization_social_twitter = '';
    public string $organization_social_instagram = '';
    public string $organization_social_linkedin = '';
    public string $organization_social_youtube = '';
    public string $organization_social_tiktok = '';
    public string $organization_social_pinterest = '';
    public string $organization_social_github = '';
    public string $organization_social_tumblr = '';
    public string $organization_social_snapchat = '';
    public string $organization_social_wikipedia = '';
    public string $organization_social_personal_website = '';

    // Additional Social URLs (array of URLs)
    public array $organization_additional_social_urls = [];

    // Redirects and 404 Error Monitoring
    public bool $redirect_manager = true;
    public bool $redirect_404_to_home = false;
    public bool $monitoring_404 = true;

    // Indexing Control
    public bool $default_noindex_posts = false;
    public bool $default_noindex_pages = false;
    public bool $index_categories = true;
    public bool $index_tags = false;

    // Social Media Optimization
    public bool $enable_social_optimization = true;
    public string $default_og_image = '';
    public string $default_twitter_card = 'summary';

    // XML Sitemaps
    public array $sitemap = [
        'enabled' => true,
        'includeImages' => true,
        'maxLinks' => 1000,
        'pingGoogle' => true,
        'pingBing' => true
    ];

    // Local SEO
    public bool $enable_local_seo = true;
    public string $default_business_type = 'LocalBusiness';
    public string $business_latitude = '';
    public string $business_longitude = '';

    // Internal Link Optimization
    public bool $internal_link_suggestions = true;

    // Breadcrumbs
    // =======================================================
    public bool $enable_breadcrumbs = true;
    public array $breadcrumb_settings = [
        'home_text' => 'Home', // Translatable in output
        'separator' => ' » ', // Translatable in output
        'enable_schema_markup' => true,
        'max_depth' => 4, // 0 = unlimited
        'show_current_as_link' => false,
        'allow_filters' => true,
        'prefix' => 'You are here:', // Translatable in output
        'suffix' => '', // Translatable in output
        'show_on_posts' => true,
        'show_on_pages' => true,
        'show_on_search' => true,
        'show_on_404' => true,
        'show_on_archives' => true,
        'show_on_categories' => true,
        'show_on_tags' => true,
        'show_on_custom_post_types' => true,
        'show_on_taxonomies' => true,
        'enabled_post_types' => [ 'post', 'page' ], // e.g. ['post', 'page', 'product']
        'enabled_taxonomies' => [ 'category', 'post_tag', 'product_cat' ], // e.g. ['category', 'product_cat']
        'suffixes' => [
            'archive'         => 'Archives for',
            'search'          => 'Search results for',
            '404'             => 'Error 404: Page not found',
            'custom_post'     => 'Custom post type archives for',
            'category'        => 'Categories',
            'taxonomy'        => 'Taxonomy archives for',
        ],
        'prefixes' => [
            'archive'     => '',
            'search'      => '',
            '404'         => '',
            'custom_post' => '',
            'taxonomy'    => '',
        ]
    ];

    // SEO Security
    public bool $security_noopen = true;
    public bool $security_nosnippet = false;

    // Page Speed Optimization
    public bool $enable_lazy_loading = false;
    public bool $minify_html = false;

    // Robots.txt Settings
    public bool $enable_robots_txt = true;
    public bool $include_sitemap_in_robots = false;

    // RSS Settings
    public bool $enable_rss = true;
    public array $rss = [
        'feeds' => [
            'cleanupEnable' => false,
            'global' => true,
            'globalComments' => true,
            'postComments' => true,
            'attachments' => true,
            'authors' => true,
            'search' => true,
            'archivesIncluded' => [
                'post',
                'page',
                'attachment'
            ],
            'archivesAll' => false,
            'taxonomiesIncluded' => [
                'category',
                'post_tag'
            ],
            'taxonomiesAll' => false,
            'atom' => true,
            'rdf' => true,
            'staticBlogPage' => true,
            'paginated' => true
        ],
        'content' => [
            'before' => '',
            'after' => ''
        ]
    ];
}
