<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB;

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Abstract base class for database migrations
 */
abstract class AbstractMigration implements MigrationInterface
{
    use RcLoggerTrait;

    /** @var DatabaseManager Database manager instance */
    protected $dbManager;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->dbManager = DatabaseManager::getInstance();
    }

    /**
     * Execute raw SQL query
     *
     * @param string $sql SQL query to execute
     * @return bool Success status
     */
    protected function executeQuery(string $sql): bool
    {
        try {
            // Use the dbDelta method from DatabaseManager instead of calling dbDelta directly
            $this->dbManager->dbDelta($sql);
            return true;
        } catch (\Throwable $e) {
            $this->log('Migration error: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Get the charset collate string
     *
     * @return string
     */
    protected function getCharsetCollate(): string
    {
        return $this->dbManager->db()->getCharsetCollate();
    }

    /**
     * Get the table name with prefix
     *
     * @param string $tableName Table name without prefix
     * @return string Table name with prefix
     */
    protected function getTableName(string $tableName): string
    {
        // Use the DatabaseManager's prefixTable method
        return $this->dbManager->prefixTable($tableName);
    }
}
