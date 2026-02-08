<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

/**
 * Create MetaTags table
 */
class M20240701000001_CreateMetaTagsTable extends AbstractMigration
{
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_METATAGS);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            template TEXT NOT NULL,
            auto_generated BOOLEAN NOT NULL DEFAULT FALSE,
            variables TEXT NOT NULL,
            unique_key VARCHAR(255) NOT NULL,
            INDEX idx_post_id (post_id),
            INDEX idx_type (type),
            INDEX idx_unique_key (unique_key)
        ) $charsetCollate;";
        
        return $this->executeQuery($sql);
    }

    /**
     * Reverse the migration
     *
     * @return bool Success status
     */
    public function down(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_METATAGS);
        $sql = "DROP TABLE IF EXISTS $tableName;";
        
        return $this->executeQuery($sql);
    }

    /**
     * Get the migration description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create MetaTags table for storing page meta tags';
    }
}