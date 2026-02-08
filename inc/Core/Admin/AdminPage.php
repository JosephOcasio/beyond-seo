<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Admin;

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class AdminPage
 */
abstract class AdminPage {

    use RcLoggerTrait;
	/**
	 * Handles the generation or processing of page content within the application.
     * @return void
	 */
	abstract public function page_content(): void;

    /**
     * Returns the name of the page.
     * @return string
     */
	abstract public function page_name(): string;

    public function __construct() {
        // add admin related hooks here
        remove_filter( 'update_footer', 'core_update_footer' );
        add_filter( 'admin_footer_text', function () {
            return '';
        } );
    }

    /**
     * Returns the instance of the AdminManager.
     * @param AdminManager $adminManager
     * @return static
     */
    public function setManager(AdminManager $adminManager): static {
        static::$managerInstance = $adminManager;
        return static::getInstance();
    }

    /**
     * Redirects to the admin page.
     *
     * @param string|null $queries - Optional queries to append to the URL.
     */
    public function redirect(?string $queries = null): void
    {
        wp_redirect(admin_url( 'admin.php?page=rankingcoach-' . static::page_name(). ($queries ?? '')));
    }
}