<?php /** @noinspection PhpUndefinedClassInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\CLI\Commands;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ExampleCommand class.
 */
class TestCommand {
	/**
	 * Execute an example command.
	 *
	 * @param array $args Positional arguments
	 * @param array $assoc_args Associative arguments
	 */
	public static function handle( array $args, array $assoc_args ): void {
		$name = $args[0] ?? 'World';
		$greeting = $assoc_args['greeting'] ?? 'Hello';

		WP_CLI::success( "$greeting, $name!" );
	}
}