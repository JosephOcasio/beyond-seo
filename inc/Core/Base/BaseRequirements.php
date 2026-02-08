<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Base;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;

/**
 * Class BaseRequirements
 */
class BaseRequirements {

	/**
	 * Minimum WordPress version required.
	 */
	public string $min_wordpress_version;

	/**
	 * Minimum PHP version required.
	 */
    public string $min_php_version;

    /**
     * PDO MySQL extension availability.
     */
    private bool $has_pdo_mysql;

    /**
	 * Constructor to initialize version requirements.
	 *
	 * @param string $min_wordpress_version - Minimum WordPress version required.
	 * @param string $min_php_version       - Minimum PHP version required.
	 */
	public function __construct(string $min_wordpress_version, string $min_php_version)
	{
		$this->min_wordpress_version = $min_wordpress_version;
		$this->min_php_version = $min_php_version;
        $this->has_pdo_mysql = extension_loaded('pdo_mysql');
	}

	/**
	 * Checks if the requirements are met.
	 *
	 * @return bool True if requirements are met, otherwise false.
	 */
	public function check(): bool
	{
		return version_compare(get_bloginfo('version'), $this->min_wordpress_version, '>=')
			&& version_compare(PHP_VERSION, $this->min_php_version, '>=')
            //&& $this->has_pdo_mysql
        ;
	}

	/**
	 * Enforces the requirements. If not met, deactivates the plugin and shows an admin notice.
	 *
	 * @throws Exception if requirements are not met.
     * @return void
	 */
	public function setup(): void {
		if (!$this->check()) {
            //$this->deactivate_plugin();
            if(!$this->has_pdo_mysql) {
                throw new Exception('Requires the PDO MySQL driver to be installed and enabled.');
            }
            throw new Exception('Your WordPress or PHP version does not meet the requirements for this plugin.');
        }
	}

	/**
	 * Deactivates the plugin if requirements are not met.
	 */
	private function deactivate_plugin(): void {
		if (!function_exists('deactivate_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		deactivate_plugins(plugin_basename(RANKINGCOACH_FILE));

		if (isset($_GET['activate'])) {
			unset($_GET['activate']);
		}
	}
}