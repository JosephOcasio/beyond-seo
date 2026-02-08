<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Traits\SingletonManager;

/**
 * Class FrontendManager
 */
class FrontendManager {

	use SingletonManager;

	/**
	 * FrontendManager constructor.
	 */
	public function __construct() {
		//
	}

	/**
	 * Initialize the frontend manager.
	 */
	public function init(): void {
        wp_enqueue_style('rankingcoach-common-style', plugin_dir_url(dirname(__FILE__)) . 'Admin/assets/css/common-style.css', [], RANKINGCOACH_VERSION);
	}
}