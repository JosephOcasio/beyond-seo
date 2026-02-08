<?php /** @noinspection PhpLackOfCohesionInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

/**
 * Class CapabilityManager
 */
class CapabilityManager {

	/**
	 * Core capability constants
	 */
    // TODO: Implement the constants for the core capabilities checking
	public const CAPABILITY_IMPORT_CATEGORIES = 'rankingcoach_import_categories';
	public const CAPABILITY_READ_WEBSITE_PAGES = 'rankingcoach_read_website_pages';
	public const CAPABILITY_READ_PLUGIN_DATA = 'rankingcoach_read_plugin_data';
	public const CAPABILITY_READ_MODULES_LIST = 'rankingcoach_read_modules_list';
	public const CAPABILITY_READ_ACCOUNT_DETAILS = 'rankingcoach_read_account_details';
	public const CAPABILITY_READ_LOCATION_KEYWORDS = 'rankingcoach_read_location_keywords';
	public const CAPABILITY_READ_MODULE_DATA = 'rankingcoach_read_module_data';
	public const CAPABILITY_MADE_ONBOARDING = 'rankingcoach_made_onboarding';

    /**
     * SEO Factor capability constants
     */
    public const CAPABILITY_FACTOR_ASSIGN_KEYWORDS_FACTOR = 'rankingcoach_factor_assign_keywords';
    public const CAPABILITY_FACTOR_CONTENT_QUALITY_AND_LENGTH_FACTOR = 'rankingcoach_factor_content_quality_and_length';
    public const CAPABILITY_FACTOR_CONTENT_READABILITY_FACTOR = 'rankingcoach_factor_content_readability';
    public const CAPABILITY_FACTOR_FIRST_PARAGRAPH_KEYWORD_USAGE_FACTOR = 'rankingcoach_factor_first_paragraph_keyword_usage';
    public const CAPABILITY_FACTOR_HEADER_TAGS_STRUCTURE_FACTOR = 'rankingcoach_factor_header_tags_structure';
    public const CAPABILITY_FACTOR_LOCAL_KEYWORDS_IN_CONTENT_FACTOR = 'rankingcoach_factor_local_keywords_in_content';
    public const CAPABILITY_FACTOR_META_DESCRIPTION_FORMAT_OPTIMIZATION_FACTOR = 'rankingcoach_factor_meta_description_format_optimization';
    public const CAPABILITY_FACTOR_META_DESCRIPTION_KEYWORDS_FACTOR = 'rankingcoach_factor_meta_description_keywords';
    public const CAPABILITY_FACTOR_META_TITLE_FORMAT_OPTIMIZATION_FACTOR = 'rankingcoach_factor_meta_title_format_optimization';
    public const CAPABILITY_FACTOR_META_TITLE_KEYWORDS_FACTOR = 'rankingcoach_factor_meta_title_keywords';
    public const CAPABILITY_FACTOR_PAGE_CONTENT_KEYWORDS_FACTOR = 'rankingcoach_factor_page_content_keywords';
    public const CAPABILITY_FACTOR_ANALYZE_BACKLINK_PROFILE_FACTOR = 'rankingcoach_factor_analyze_backlink_profile';
    public const CAPABILITY_FACTOR_FIX_BROKEN_LINKS_ON_PAGE_FACTOR = 'rankingcoach_factor_fix_broken_links_on_page';
    public const CAPABILITY_FACTOR_ALT_TEXT_TO_IMAGES_FACTOR = 'rankingcoach_factor_alt_text_to_images';
    public const CAPABILITY_FACTOR_IMAGE_OPTIMIZATION_FACTOR = 'rankingcoach_factor_image_optimization';
    public const CAPABILITY_FACTOR_OPTIMIZE_PAGE_SPEED_FACTOR = 'rankingcoach_factor_optimize_page_speed';
	public const CAPABILITY_FACTOR_OPTIMIZE_URL_STRUCTURE_FACTOR = 'rankingcoach_factor_optimize_url_structure';
	public const CAPABILITY_FACTOR_SCHEMA_MARKUP_FACTOR = 'rankingcoach_factor_schema_markup';
	public const CAPABILITY_FACTOR_SEARCH_ENGINE_INDEXATION_FACTOR = 'rankingcoach_factor_search_engine_indexation';
	public const CAPABILITY_FACTOR_USE_CANONICAL_TAGS_FACTOR = 'rankingcoach_factor_use_canonical_tags';

	/**
	 * The singleton instance of the class.
	 *
	 * @var CapabilityManager|null
	 */
	private static ?CapabilityManager $instance = null;
	/**
	 * Stores registered capabilities.
	 *
	 * @var array
	 */
    private array $registered_caps = [];

	/**
	 * Returns the singleton instance of the CapabilitiesManager class.
	 *
	 * @return CapabilityManager Instance of CapabilitiesManager.
	 */
	public static function instance(): CapabilityManager {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initializes default capabilities to be assigned to roles.
	 *
	 * @return void
	 */
	private function initializeCapabilities(): void {
        // Core capabilities
        $this->addCapability(self::CAPABILITY_IMPORT_CATEGORIES, 'Import translated categories');
        $this->addCapability(self::CAPABILITY_READ_WEBSITE_PAGES, 'Read Website All Pages');
        $this->addCapability(self::CAPABILITY_READ_PLUGIN_DATA, 'Read Plugin Data');
        $this->addCapability(self::CAPABILITY_READ_MODULES_LIST, 'Read List of Modules');
		$this->addCapability(self::CAPABILITY_READ_ACCOUNT_DETAILS, 'Read Account Details');
		$this->addCapability(self::CAPABILITY_READ_LOCATION_KEYWORDS, 'Read Location Keywords');
		$this->addCapability(self::CAPABILITY_READ_MODULE_DATA, 'Read Distinct Module Data');
        $this->addCapability(self::CAPABILITY_MADE_ONBOARDING, 'Made The Plugin Onboarding');
        
        // SEO Factor capabilities
        $this->addCapability(self::CAPABILITY_FACTOR_ASSIGN_KEYWORDS_FACTOR, 'Access to Assign Keywords SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_CONTENT_QUALITY_AND_LENGTH_FACTOR, 'Access to Improve Content Quality And Length SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_CONTENT_READABILITY_FACTOR, 'Access to Optimize Readability On Page SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_FIRST_PARAGRAPH_KEYWORD_USAGE_FACTOR, 'Access to First Paragraph Keyword Usage SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_HEADER_TAGS_STRUCTURE_FACTOR, 'Access to Header Tags Structure SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_LOCAL_KEYWORDS_IN_CONTENT_FACTOR, 'Access to Add Local Keywords In Content SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_META_DESCRIPTION_FORMAT_OPTIMIZATION_FACTOR, 'Access to Create Meta Description Tag SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_META_DESCRIPTION_KEYWORDS_FACTOR, 'Access to Meta Description Keywords SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_META_TITLE_FORMAT_OPTIMIZATION_FACTOR, 'Access to Create Meta Title Tag SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_META_TITLE_KEYWORDS_FACTOR, 'Access to Meta Title Keywords SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_PAGE_CONTENT_KEYWORDS_FACTOR, 'Access to Optimize Page Content Keywords SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_ANALYZE_BACKLINK_PROFILE_FACTOR, 'Access to Analyze Backlink Profile SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_FIX_BROKEN_LINKS_ON_PAGE_FACTOR, 'Access to Fix Broken Links On Page SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_ALT_TEXT_TO_IMAGES_FACTOR, 'Access to Alt Text To Images SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_IMAGE_OPTIMIZATION_FACTOR, 'Access to Optimize Image File Sizes SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_OPTIMIZE_PAGE_SPEED_FACTOR, 'Access to Optimize Page Speed SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_OPTIMIZE_URL_STRUCTURE_FACTOR, 'Access to Optimize URL Structure SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_SCHEMA_MARKUP_FACTOR, 'Access to Schema Markup SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_SEARCH_ENGINE_INDEXATION_FACTOR, 'Access to Search Engine Indexation SEO operations');
        $this->addCapability(self::CAPABILITY_FACTOR_USE_CANONICAL_TAGS_FACTOR, 'Access to Use Canonical Tags SEO operations');
	}

	/**
	 * Registers a new capability for use within the plugin.
	 *
	 * @param string $capability The capability identifier.
	 * @param string $description The description of the capability.
	 */
	public function addCapability(string $capability, string $description ): void {
		$this->registered_caps[ $capability ] = $description;
	}

	/**
	 * Resets capabilities by revoking and reassigning them.
	 *
	 * @return void
	 */
	public function resetAllCapabilities(): void {
		$this->revokeCapabilities();
		$this->assignCapabilities();
	}

	/**
	 * Removes capabilities from roles during plugin uninstallation.
	 *
	 * @return void
	 */
	public function revokeCapabilities(): void {
		$caps_to_remove = $this->getRegisteredCaps( true );
		foreach ($this->getAvailableRoles() as $role_slug => $role_object ) {
			$role_object = get_role( $role_slug );
			if ( ! $role_object ) {
				continue;
			}

			$this->applyCapabilities( $caps_to_remove, 'remove_cap', $role_object );
		}
	}

	/**
	 * Retrieves all registered capabilities based on the specified format.
	 *
	 * @param bool $as_keys If true, returns only the capability keys.
	 *
	 * @return array List of registered capabilities.
	 */
	public function getRegisteredCaps(bool $as_keys = false ): array {
		return $as_keys ? array_keys( $this->registered_caps ) : $this->registered_caps;
	}

	/**
	 * Retrieves the list of available roles in the system.
	 *
	 * @return array List of available WordPress roles.
	 */
	private function getAvailableRoles(): array {
		return [
			'administrator' => get_role( 'administrator' ),
			'editor'        => get_role( 'editor' ),
			'author'        => get_role( 'author' ),
		];
	}

	/**
	 * Iterates over the capability list and applies the specified action to the given role.
	 *
	 * @param array $capabilities List of capabilities.
	 * @param string $action Action to apply (e.g., 'add_cap' or 'remove_cap').
	 * @param object $role WordPress role object.
	 */
	private function applyCapabilities(array $capabilities, string $action, object $role ): void {
		foreach ( $capabilities as $cap ) {
			$role->$action( $cap );
		}
	}

	/**
	 * Assigns capabilities to roles during plugin installation.
	 *
	 * @return void
	 */
	public function assignCapabilities(): void {
        $this->initializeCapabilities();

		foreach ($this->getAvailableRoles() as $role_slug => $role_object ) {
			$role_object = get_role( $role_slug );
			if ( ! $role_object ) {
				continue;
			}

			$this->applyCapabilities( $this->getCapabilitiesForRole( $role_slug ), 'add_cap', $role_object );
		}
	}

	/**
	 * Retrieves default capabilities assigned to each role.
	 *
	 * @param string $role_slug The role identifier.
	 *
	 * @return array List of default capabilities for the specified role.
	 */
	private function getCapabilitiesForRole(string $role_slug ): array {
		return match ( $role_slug ) {
			'administrator', 'editor', 'author' => $this->getRegisteredCaps( true ),
			default => [],
		};
	}
	
	/**
	 * Checks if the current user has a specific factor capability.
	 *
	 * @param string $factorName The name of the SEO factor.
	 * @return bool True if the user has the capability, false otherwise.
	 */
	public function hasFactorCapability(string $factorName): bool {
		$capability = $this->getFactorCapability($factorName);
		
		if ($capability === null) {
			return false;
		}
		
		return current_user_can($capability);
	}
	
	/**
	 * Get the capability name for a specific factor.
	 *
	 * @param string $factorName The name of the SEO factor.
	 * @return string|null The capability name or null if not found.
	 */
	public function getFactorCapability(string $factorName): ?string {
		$constantName = 'CAPABILITY_FACTOR_' . strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $factorName));
		
		// Check if the constant exists in this class
		if (!defined(self::class . '::' . $constantName)) {
			return null;
		}
		
		return constant(self::class . '::' . $constantName);
	}
	
	/**
	 * Checks if the current user has a specific core capability.
	 *
	 * @param string $capabilityName The name of the core capability (without the prefix).
	 * @return bool True if the user has the capability, false otherwise.
	 */
	public function hasCoreCapability(string $capabilityName): bool {
		$capability = $this->getCoreCapability($capabilityName);
		
		if ($capability === null) {
			return false;
		}
		
		return current_user_can($capability);
	}
	
	/**
	 * Get the capability name for a specific core capability.
	 *
	 * @param string $capabilityName The name of the core capability (without the prefix).
	 * @return string|null The capability name or null if not found.
	 */
	public function getCoreCapability(string $capabilityName): ?string {
		$constantName = 'CAPABILITY_' . strtoupper($capabilityName);
		
		// Check if the constant exists in this class
		if (!defined(self::class . '::' . $constantName)) {
			return null;
		}
		
		return constant(self::class . '::' . $constantName);
	}
}
