<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB;

use DirectoryIterator;
use Exception;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use ReflectionClass;
use Throwable;

/**
 * Database Manager for handling WordPress table creation
 * 
 * This class should only be instantiated and used by DatabaseManager
 * to avoid circular dependencies.
 */
class DatabaseTablesManager
{
    use RcLoggerTrait;
    
    /**
     * Static method to handle legacy getInstance() calls
     * 
     * @deprecated Use DatabaseManager::tables() instead
     * @return DatabaseTablesManager
     * @throws Exception
     */
    public static function getInstance(): self
    {
        // Return the instance from DatabaseManager to maintain backward compatibility
        return DatabaseManager::getInstance()->tables();
    }

    public const BULK_CHUNK_SIZE = 500;
    
    // Database table names as constants
    public const DATABASE_APP_KEYWORDS = 'rankingcoach_app_keywords';
    public const DATABASE_SETUP_COLLECTORS = 'rankingcoach_setup_collectors';
    public const DATABASE_SETUP_STEPS = 'rankingcoach_setup_steps';
    public const DATABASE_SETUP_COMPLETIONS = 'rankingcoach_setup_completions';
    public const DATABASE_SETUP_QUESTIONS = 'rankingcoach_setup_questions';
    public const DATABASE_SETUP_CATEGORIES = 'rankingcoach_setup_categories';
    public const DATABASE_SETUP = 'rankingcoach_setup';
    public const DATABASE_SEO_OPTIMISERS = 'rankingcoach_seo_optimisers';
    public const DATABASE_SEO_CONTEXTS = 'rankingcoach_seo_contexts';
    public const DATABASE_SEO_FACTORS = 'rankingcoach_seo_factors';
    public const DATABASE_SEO_OPERATIONS = 'rankingcoach_seo_operations';
    public const DATABASE_MIGRATIONS = 'rankingcoach_migrations';

    /**
     * Database table names for modules
     */
    public const DATABASE_MOD_REDIRECTS = 'rankingcoach_redirects';
    public const DATABASE_MOD_LINK_ANALYZER = 'rankingcoach_link_analyzer';
    public const DATABASE_MOD_BROKEN_LINK_CHECKER = 'rankingcoach_broken_link_checker';
    public const DATABASE_MOD_METATAGS = 'rankingcoach_metatags';

    public const TABLE_MIGRATIONS = [
        'schema' => "CREATE TABLE {table_name} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration_name VARCHAR(255) NOT NULL,
            migration_file VARCHAR(255) NOT NULL,
            batch INT UNSIGNED NOT NULL,
            executed_at DATETIME NOT NULL,
            execution_time FLOAT NOT NULL,
            UNIQUE KEY migration_name (migration_name)
        ) {charset_collate};"
    ];

    /** @var DatabaseManager Database manager instance */
    private DatabaseManager $dbManager;

    /** @var array Table definitions */
    private array $tables;
    
    /** @var string Path to migrations directory */
    private string $migrationsPath;
    
    /** @var array Cached list of available migrations */
    private array $availableMigrations = [];
    
    /** @var array Cached list of applied migrations */
    private array $appliedMigrations = [];

    /**
     * Constructor
     * 
     * @param DatabaseManager $dbManager The database manager instance
     */
    public function __construct(DatabaseManager $dbManager)
    {
        // Store the provided database manager instance
        $this->dbManager = $dbManager;

        // Define all tables
        $this->initializeTables();
        
        // Set migrations path
        $this->migrationsPath = plugin_dir_path(__FILE__) . 'Migrations';
    }
    
    /**
     * Get DatabaseManager instance
     * 
     * @return DatabaseManager
     */
    private function getDbManager(): DatabaseManager
    {
        return $this->dbManager;
    }

    /**
     * Check if table exists
     *
     * @param string $tableName Table name
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        return $this->getDbManager()->tableExists($tableName);
    }

    /**
     * Initialize tables
     * 
     * This method is kept for backward compatibility but no longer initializes table definitions
     * as they are now handled by the migration system.
     */
    private function initializeTables(): void
    {
        // Tables are now handled by the migration system
        $this->tables = [];
    }

    /**
     * Create all tables during plugin activation
     *
     * @return void
     * @throws Exception
     */
    public function createAllTables(): void
    {
        // Run migrations to create all tables
        $this->runMigrations();
    }
    
    /**
     * Create migrations table directly (needed before we can run migrations)
     *
     * @return void
     */
    private function createMigrationsTable(): void
    {
        // Check if table already exists
        if ($this->getDbManager()->tableExists(self::DATABASE_MIGRATIONS)) {
            return;
        }
        
        $tableName = $this->dbManager->prefixTable(self::DATABASE_MIGRATIONS);
        
        // Prepare and execute schema
        $charsetCollate = $this->dbManager->db()->getCharsetCollate();
        $schema = str_replace(
            ['{table_name}', '{charset_collate}'],
            [$tableName, $charsetCollate],
            self::TABLE_MIGRATIONS['schema']
        );
        
        // Use the dbDelta method from DatabaseManager instead of calling dbDelta directly
        $this->dbManager->dbDelta($schema);
    }



    /**
     * Update setup requirements
     * 
     * @param string $requirement The requirement to update
     * @param mixed $value The value to set
     * @throws Exception
     */
    public function updateSetupRequirements(string $requirement, $value): void
    {
        $tableName = self::DATABASE_SETUP;
        
        $this->getDbManager()->update(
            $tableName,
            ['value' => $value],
            ['setupRequirement' => $requirement]
        );
    }

    /**
     * Save categories to database
     * @param mixed $categories
     */
    public function saveCategoriesToDatabase(mixed $categories): void
    {
        $tableName = self::DATABASE_SETUP_CATEGORIES;
        $dbManager = $this->getDbManager();
        
        // Truncate the table
        $dbManager->truncate($tableName);

        $allCategories = [];

        // First, collect all valid categories
        foreach ($categories as $category) {
            $category = (object)$category;

            // Skip empty category names
            if (empty($category->name)) {
                continue;
            }

            $allCategories[] = [
                'categoryId' => $category->id,
                'name' => empty($category->display_name) ? $category->name : $category->display_name,
                'externalId' => $category->external_id ?? $category->externalId
            ];
        }

        $chunks = array_chunk($allCategories, self::BULK_CHUNK_SIZE);

        // Insert chunks
        foreach ($chunks as $chunk) {
            foreach ($chunk as $categoryData) {
                try {
                    $dbManager->insert($tableName, $categoryData);
                } catch (Throwable $exception) {
                    $this->log('Error inserting category: ' . $exception->getMessage(), 'ERROR');
                }
            }
        }
    }

    /**
     * Remove all tables during plugin deactivation
     * @throws Exception
     */
    public function removeAllTables(): void
    {
        $class = new ReflectionClass(__CLASS__);
        $constants = $class->getConstants();
        $tables = [];
        foreach ($constants as $name => $value) {
            if (str_starts_with($name, 'DATABASE_')) {
                $tables[] = $value;
            }
        }
        $this->dbManager->dropTables($tables);
    }

    /**
     * Get all plugin table names (without prefix)
     *
     * @return array<string>
     */
    public function getAllPluginTables(): array
    {
        $class = new ReflectionClass(__CLASS__);
        $constants = $class->getConstants();
        $tables = [];
        foreach ($constants as $name => $value) {
            if (str_starts_with($name, 'DATABASE_')) {
                $tables[] = $value;
            }
        }
        return $tables;
    }

    /**
     * Remove all user and post metadata containing 'rankingcoach_'.
     *
     * @param string $terms
     * @return void
     */
    public function removeUsersAndPostsMetadata(string $terms = 'rankingcoach_'): void
    {
        try {
            $dbManager = $this->getDbManager();
            $db = $dbManager->db();
            
            // Remove user metadata containing the specified terms using builder pattern
            $dbManager->table('usermeta')
                ->delete()
                ->whereLike('meta_key', $terms)
                ->get();
            
            // Remove post metadata containing 'rankingcoach_' using builder pattern
            $dbManager->table('postmeta')
                ->delete()
                ->whereLike('meta_key', 'rankingcoach_')
                ->get();
        } catch (Exception $e) {
            $this->log('Error removing metadata: ' . $e->getMessage() . ' in ' . __METHOD__ . ':' . __LINE__, 'ERROR');
        }
    }
    
    /**
     * Run all pending migrations
     *
     * @return array Array of executed migrations with status
     * @throws Exception
     */
    public function runMigrations(): array
    {
        // Ensure migrations table exists
        $this->createMigrationsTable();
        
        $pendingMigrations = $this->getPendingMigrations();
        $results = [];
        $batch = $this->getNextBatchNumber();
        
        foreach ($pendingMigrations as $migrationFile) {
            $migrationName = $this->getMigrationNameFromFile($migrationFile);
            $migrationClass = $this->getMigrationClassFromFile($migrationFile);
            
            try {
                $startTime = microtime(true);
                
                /** @var MigrationInterface $migration */
                $migration = new $migrationClass();
                $success = $migration->up();
                
                $executionTime = microtime(true) - $startTime;
                
                if ($success) {
                    $this->recordMigration($migrationName, $migrationFile, $batch, $executionTime);
                    $results[$migrationName] = [
                        'status' => 'success',
                        'time' => $executionTime,
                        'description' => $migration->getDescription()
                    ];
                } else {
                    $results[$migrationName] = [
                        'status' => 'failed',
                        'time' => $executionTime,
                        'description' => $migration->getDescription()
                    ];
                }
            } catch (Throwable $e) {
                $this->log('Migration error: ' . $e->getMessage(), 'ERROR');
                $results[$migrationName] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'description' => 'Migration failed with exception'
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Rollback the last batch of migrations
     *
     * @return array Array of rolled back migrations with status
     */
    public function rollbackMigrations(): array
    {
        $lastBatch = $this->getLastBatchNumber();
        if ($lastBatch === 0) {
            return [];
        }
        
        $migrationsToRollback = $this->getMigrationsByBatch($lastBatch);
        $results = [];
        
        // Rollback in reverse order
        $migrationsToRollback = array_reverse($migrationsToRollback);
        
        foreach ($migrationsToRollback as $migration) {
            $migrationClass = $this->getMigrationClassFromFile($migration['migration_file']);
            
            try {
                $startTime = microtime(true);
                
                /** @var MigrationInterface $migrationInstance */
                $migrationInstance = new $migrationClass();
                $success = $migrationInstance->down();
                
                $executionTime = microtime(true) - $startTime;
                
                if ($success) {
                    $this->removeMigrationRecord($migration['migration_name']);
                    $results[$migration['migration_name']] = [
                        'status' => 'success',
                        'time' => $executionTime,
                        'description' => $migrationInstance->getDescription()
                    ];
                } else {
                    $results[$migration['migration_name']] = [
                        'status' => 'failed',
                        'time' => $executionTime,
                        'description' => $migrationInstance->getDescription()
                    ];
                }
            } catch (Throwable $e) {
                $this->log('Rollback error: ' . $e->getMessage(), 'ERROR');
                $results[$migration['migration_name']] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'description' => 'Rollback failed with exception'
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Get all available migrations
     *
     * @return array Array of migration file paths
     */
    public function getAvailableMigrations(): array
    {
        if (!empty($this->availableMigrations)) {
            return $this->availableMigrations;
        }
        
        $migrations = [];
        
        if (is_dir($this->migrationsPath)) {
            $directory = new DirectoryIterator($this->migrationsPath);
            
            foreach ($directory as $file) {
                if ($file->isFile() && $file->getExtension() === 'php' && strpos($file->getFilename(), 'M') === 0) {
                    $migrations[] = $file->getPathname();
                }
            }
            
            // Sort migrations by name (which should be timestamp-based)
            sort($migrations);
        }
        
        $this->availableMigrations = $migrations;
        return $migrations;
    }
    
    /**
     * Get all applied migrations
     *
     * @return array Array of applied migration records
     */
    public function getAppliedMigrations(): array
    {
        if (!empty($this->appliedMigrations)) {
            return $this->appliedMigrations;
        }
        
        $tableName = self::DATABASE_MIGRATIONS;
        
        // Check if migrations table exists
        if (!$this->tableExists($tableName)) {
            return [];
        }
        
        $migrations = $this->getDbManager()->getAll(
            $tableName,
            ['*'],
            [],
            'id',
            'ASC'
        );
        
        $this->appliedMigrations = $migrations ?: [];
        return $this->appliedMigrations;
    }
    
    /**
     * Get pending migrations that haven't been applied yet
     *
     * @return array Array of pending migration file paths
     */
    public function getPendingMigrations(): array
    {
        $availableMigrations = $this->getAvailableMigrations();
        $appliedMigrations = $this->getAppliedMigrations();
        
        $appliedNames = [];
        foreach ($appliedMigrations as $migration) {
            $appliedNames[] = $migration->migration_name;
        }
        
        $pendingMigrations = [];
        foreach ($availableMigrations as $migrationFile) {
            $migrationName = $this->getMigrationNameFromFile($migrationFile);
            
            if (!in_array($migrationName, $appliedNames, true)) {
                $pendingMigrations[] = $migrationFile;
            }
        }
        
        return $pendingMigrations;
    }
    
    /**
     * Get migration status summary
     *
     * @return array Migration status summary
     */
    public function getMigrationStatus(): array
    {
        $availableMigrations = $this->getAvailableMigrations();
        $appliedMigrations = $this->getAppliedMigrations();
        
        $appliedNames = [];
        $appliedByName = [];
        
        foreach ($appliedMigrations as $migration) {
            $appliedNames[] = $migration['migration_name'];
            $appliedByName[$migration['migration_name']] = $migration;
        }
        
        $status = [];
        
        foreach ($availableMigrations as $migrationFile) {
            $migrationName = $this->getMigrationNameFromFile($migrationFile);
            $migrationClass = $this->getMigrationClassFromFile($migrationFile);
            
            try {
                /** @var MigrationInterface $migration */
                $migration = new $migrationClass();
                $description = $migration->getDescription();
            } catch (Throwable $e) {
                $description = 'Error loading migration: ' . $e->getMessage();
            }
            
            $status[$migrationName] = [
                'file' => basename($migrationFile),
                'description' => $description,
                'status' => in_array($migrationName, $appliedNames) ? 'applied' : 'pending',
            ];
            
            if (in_array($migrationName, $appliedNames)) {
                $status[$migrationName]['executed_at'] = $appliedByName[$migrationName]['executed_at'];
                $status[$migrationName]['batch'] = $appliedByName[$migrationName]['batch'];
            }
        }
        
        return $status;
    }
    
    /**
     * Create a new migration file
     *
     * @param string $name Migration name
     * @return string|false Path to created migration file or false on failure
     */
    public function createMigration(string $name): string|false
    {
        $timestamp = gmdate('YmdHis');
        $className = 'M' . $timestamp . '_' . $this->formatMigrationName($name);
        $filePath = $this->migrationsPath . '/' . $className . '.php';
        
        // Create migrations directory if it doesn't exist
        if (!is_dir($this->migrationsPath)) {
            if (!wp_mkdir_p($concurrentDirectory = $this->migrationsPath) && !is_dir($concurrentDirectory)) {
                $this->log('Failed to create migrations directory', 'ERROR');
                return false;
            }
        }
        
        $template = $this->getMigrationTemplate($className, $name);
        
        if (file_put_contents($filePath, $template) === false) {
            $this->log('Failed to create migration file', 'ERROR');
            return false;
        }
        
        // Clear cached available migrations
        $this->availableMigrations = [];
        
        return $filePath;
    }
    
    /**
     * Get migration template content
     *
     * @param string $className Migration class name
     * @param string $name Migration name for description
     * @return string Migration template content
     */
    private function getMigrationTemplate(string $className, string $name): string
    {
        return '<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

use RankingCoach\Inc\Core\DB\AbstractMigration;

/**
 * ' . $name . '
 */
class ' . $className . ' extends AbstractMigration
{
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        // Implement your migration logic here
        // Example:
        // $tableName = $this->getTableName(\'your_table_name\');
        // $charsetCollate = $this->getCharsetCollate();
        // $sql = "CREATE TABLE IF NOT EXISTS $tableName (
        //     id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        //     name VARCHAR(255) NOT NULL
        // ) $charsetCollate;";
        // return $this->executeQuery($sql);
        
        return true;
    }

    /**
     * Reverse the migration
     *
     * @return bool Success status
     */
    public function down(): bool
    {
        // Implement your rollback logic here
        // Example:
        // $tableName = $this->getTableName(\'your_table_name\');
        // $sql = "DROP TABLE IF EXISTS $tableName;";
        // return $this->dbManager->db()->query($sql) !== false;
        
        return true;
    }

    /**
     * Get the migration description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return \'' . addslashes($name) . '\';
    }
}';
    }
    
    /**
     * Format migration name to a valid class name
     *
     * @param string $name Migration name
     * @return string Formatted migration name
     */
    private function formatMigrationName(string $name): string
    {
        // Replace spaces and special characters with underscores
        $name = preg_replace('/[^a-zA-Z0-9]/', '_', $name);
        
        // Ensure first character is uppercase
        $name = ucfirst($name);
        
        return $name;
    }
    
    /**
     * Get migration name from file path
     *
     * @param string $filePath Migration file path
     * @return string Migration name
     */
    private function getMigrationNameFromFile(string $filePath): string
    {
        return pathinfo($filePath, PATHINFO_FILENAME);
    }
    
    /**
     * Get migration class from file path
     *
     * @param string $filePath Migration file path
     * @return string Fully qualified class name
     */
    private function getMigrationClassFromFile(string $filePath): string
    {
        $className = pathinfo($filePath, PATHINFO_FILENAME);
        return 'RankingCoach\\Inc\\Core\\DB\\Migrations\\' . $className;
    }
    
    /**
     * Record a successful migration
     *
     * @param string $migrationName Migration name
     * @param string $migrationFile Migration file path
     * @param int $batch Batch number
     * @param float $executionTime Execution time in seconds
     * @return bool Success status
     */
    private function recordMigration(string $migrationName, string $migrationFile, int $batch, float $executionTime): bool
    {
        $tableName = self::DATABASE_MIGRATIONS;
        $data = [
            'migration_name' => $migrationName,
            'migration_file' => basename($migrationFile),
            'batch' => $batch,
            'executed_at' => current_time('mysql'),
            'execution_time' => $executionTime
        ];
        
        $result = $this->getDbManager()->insert($tableName, $data);
        $success = $result !== false;
        
        // Clear cached applied migrations
        $this->appliedMigrations = [];
        
        return $success;
    }
    
    /**
     * Remove a migration record
     *
     * @param string $migrationName Migration name
     * @return bool Success status
     */
    private function removeMigrationRecord(string $migrationName): bool
    {
        $tableName = self::DATABASE_MIGRATIONS;
        
        $result = $this->getDbManager()->delete(
            $tableName,
            ['migration_name' => $migrationName]
        );
        
        $success = $result !== false;
        
        // Clear cached applied migrations
        $this->appliedMigrations = [];
        
        return $success;
    }
    
    /**
     * Get the next batch number
     *
     * @return int Next batch number
     */
    private function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }
    
    /**
     * Get the last batch number
     *
     * @return int Last batch number
     */
    private function getLastBatchNumber(): int
    {
        $tableName = self::DATABASE_MIGRATIONS;
        
        // Check if migrations table exists
        if (!$this->tableExists($tableName)) {
            return 0;
        }
        
        $result = $this->getDbManager()->getValue(
            $tableName,
            'MAX(batch) as max_batch'
        );
        
        return $result ? (int)$result : 0;
    }
    
    /**
     * Get migrations by batch number
     *
     * @param int $batch Batch number
     * @return array Migrations in the specified batch
     */
    private function getMigrationsByBatch(int $batch): array
    {
        $tableName = self::DATABASE_MIGRATIONS;
        
        $migrations = $this->getDbManager()->getAll(
            $tableName,
            ['*'],
            ['batch' => $batch],
            'id',
            'ASC'
        );
        
        return $migrations ?: [];
    }
}

