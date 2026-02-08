<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB;

/**
 * Interface for database migrations
 */
interface MigrationInterface
{
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool;

    /**
     * Reverse the migration
     *
     * @return bool Success status
     */
    public function down(): bool;

    /**
     * Get the migration description
     *
     * @return string
     */
    public function getDescription(): string;
}
