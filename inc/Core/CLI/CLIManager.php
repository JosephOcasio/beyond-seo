<?php /** @noinspection PhpUndefinedClassInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\CLI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use WP_CLI;

/**
 * Class CliManager
 *
 * @package RankingCoach\Inc\Commands
 */
class CLIManager {

	/**
	 * Initialize the CLI Manager.
	 */
	public function __construct() {
		// Check if WP-CLI is available
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_commands();
		}
	}

	/**
	 * Dynamically load and register commands.
	 */
	private function register_commands(): void {
		// Load all command classes and register them with WP-CLI
		$commands = [
			'test' => 'TestCommand',
			'migration' => 'MigrationCommand',
		];

		foreach ( $commands as $command => $class ) {
			$class_name = RANKINGCOACH_PLUGIN_CLI_DIR . "Commands\\" . $class;
			if ( class_exists( $class_name ) ) {
				// For MigrationCommand, we register the whole class since it extends WP_CLI_Command
				// This automatically registers all public methods as subcommands
				if ( $class === 'MigrationCommand' ) {
					WP_CLI::add_command( "rankingcoach $command", $class_name );
				} else {
					// For other commands, use the legacy approach
					WP_CLI::add_command( "rankingcoach $command", [ $class_name, 'handle' ] );
				}
			}
		}
	}
}
