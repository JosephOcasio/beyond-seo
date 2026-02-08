<?php /** @noinspection PhpUndefinedClassInspection */
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\CLI\Commands;

use RankingCoach\Inc\Core\DB\DatabaseManager;
use WP_CLI;
use WP_CLI_Command;

/**
 * Manages database migrations for the RankingCoach plugin.
 *
 * ## EXAMPLES
 *
 *     # Create a new migration
 *     wp rankingcoach migration create create_users_table
 *
 *     # Run all pending migrations
 *     wp rankingcoach migration run
 *
 *     # Rollback the last batch of migrations
 *     wp rankingcoach migration rollback
 *
 *     # Show migration status
 *     wp rankingcoach migration status
 */
class MigrationCommand extends WP_CLI_Command {
    /**
     * Creates a new migration file.
     *
     * ## OPTIONS
     *
     * <name>
     * : The name of the migration (e.g., create_users_table).
     *
     * [--table=<table>]
     * : Optional. The table name for the migration.
     *
     * [--column=<column>]
     * : Optional. The column name when adding a column to a table.
     *
     * [--column-type=<type>]
     * : Optional. The column type definition (e.g., "VARCHAR(255) NOT NULL").
     *
     * [--type=<type>]
     * : Optional. The migration type (create_table, add_column, etc.).
     *
     * ## EXAMPLES
     *
     *     # Create a basic migration
     *     wp rankingcoach migration create create_users_table
     *
     *     # Create a migration for adding a column to a table
     *     wp rankingcoach migration create add_email_to_users --table=users --column=email --column-type="VARCHAR(255) NOT NULL"
     *
     *     # Create a migration with a specific type
     *     wp rankingcoach migration create create_users_table --type=create_table
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function create( array $args, array $assoc_args ): void {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( 'Migration name is required.' );
            return;
        }

        $name = $args[0];
        $dbManager = DatabaseManager::getInstance();
        
        // Check for specific migration types
        $type = $assoc_args['type'] ?? '';
        $table = $assoc_args['table'] ?? '';
        $column = $assoc_args['column'] ?? '';
        $columnType = $assoc_args['column-type'] ?? '';
        
        $migrationPath = '';
        
        // Handle different migration types
        if ( $type === 'create_table' || ( empty( $type ) && strpos( $name, 'create_' ) === 0 ) ) {
            // Create table migration
            $migrationPath = $dbManager->createMigration( $name );
            WP_CLI::success( "Created table migration: $migrationPath" );
        } elseif ( $type === 'add_column' || ( !empty( $table ) && !empty( $column ) && !empty( $columnType ) ) ) {
            // Add column migration
            if ( empty( $table ) || empty( $column ) || empty( $columnType ) ) {
                WP_CLI::error( 'Table name, column name, and column type are required for add_column migrations.' );
                return;
            }
            
            $migrationPath = $dbManager->createAddColumnMigration( $table, $column, $columnType );
            WP_CLI::success( "Created add column migration: $migrationPath" );
        } else {
            // Generic migration
            $migrationPath = $dbManager->createMigration( $name );
            WP_CLI::success( "Created migration: $migrationPath" );
        }
        
        if ( !$migrationPath ) {
            WP_CLI::error( 'Failed to create migration file.' );
            return;
        }
        
        // Display the path to the created migration
        WP_CLI::log( "Migration file created at: $migrationPath" );
        
        // Display next steps
        WP_CLI::log( "" );
        WP_CLI::log( "Next steps:" );
        WP_CLI::log( "1. Edit the migration file to implement your database changes" );
        WP_CLI::log( "2. Run migrations with: wp rankingcoach migration run" );
    }

    /**
     * Runs pending migrations.
     *
     * ## EXAMPLES
     *
     *     # Run all pending migrations
     *     wp rankingcoach migration run
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     * @throws \Exception
     */
    public function run( array $args, array $assoc_args ): void {
        $dbManager = DatabaseManager::getInstance();
        
        WP_CLI::log( "Running migrations..." );
        
        $results = $dbManager->runMigrations();
        
        if ( empty( $results ) ) {
            WP_CLI::success( "No pending migrations to run." );
            return;
        }
        
        foreach ( $results as $name => $result ) {
            if ( $result['status'] === 'success' ) {
                WP_CLI::success( "Migrated: $name - " . $result['description'] );
            } else {
                WP_CLI::error( "Failed: $name - " . ( $result['message'] ?? 'Unknown error' ) );
            }
        }
        
        WP_CLI::success( "Migrations completed." );
    }
    
    /**
     * Rolls back the last batch of migrations.
     *
     * ## EXAMPLES
     *
     *     # Rollback the last batch of migrations
     *     wp rankingcoach migration rollback
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function rollback( array $args, array $assoc_args ): void {
        $dbManager = DatabaseManager::getInstance();
        
        WP_CLI::log( "Rolling back migrations..." );
        
        $results = $dbManager->rollbackMigrations();
        
        if ( empty( $results ) ) {
            WP_CLI::success( "No migrations to rollback." );
            return;
        }
        
        foreach ( $results as $name => $result ) {
            if ( $result['status'] === 'success' ) {
                WP_CLI::success( "Rolled back: $name" );
            } else {
                WP_CLI::error( "Failed to rollback: $name - " . ( $result['message'] ?? 'Unknown error' ) );
            }
        }
        
        WP_CLI::success( "Rollback completed." );
    }
    
    /**
     * Shows migration status.
     *
     * ## EXAMPLES
     *
     *     # Show migration status
     *     wp rankingcoach migration status
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function status( array $args, array $assoc_args ): void {
        $dbManager = DatabaseManager::getInstance();
        
        $status = $dbManager->getMigrationStatus();
        
        if ( empty( $status ) ) {
            WP_CLI::log( "No migrations found." );
            return;
        }
        
        // Format for WP_CLI table
        $items = [];
        foreach ( $status as $name => $info ) {
            $items[] = [
                'Migration' => $name,
                'Status' => $info['status'],
                'Batch' => $info['status'] === 'applied' ? $info['batch'] : '-',
                'Description' => $info['description'],
            ];
        }
        
        // Display as table
        WP_CLI\Utils\format_items( 'table', $items, ['Migration', 'Status', 'Batch', 'Description'] );
    }
    
    /**
     * Handles the default command (shows help).
     *
     * ## EXAMPLES
     *
     *     # Show help for migration commands
     *     wp rankingcoach migration
     *
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function __invoke( array $args, array $assoc_args ): void {
        WP_CLI::runcommand( 'help rankingcoach migration' );
    }
}