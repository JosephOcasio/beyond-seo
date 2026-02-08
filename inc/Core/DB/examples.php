<?php
/**
 * Examples of using the Database builder pattern
 * 
 * This file contains examples of how to use the new Database builder pattern.
 * It is not meant to be included in the plugin, but rather to serve as a reference.
 */

// Get the DatabaseManager instance
$dbManager = \RankingCoach\Inc\Core\DB\DatabaseManager::getInstance();

// Example 1: Simple SELECT query
$keywords = $dbManager->table('rankingcoach_app_keywords')
    ->select(['id', 'name', 'alias'])
    ->where('name', 'example keyword')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();

// Example 2: INSERT query
$newKeywordId = $dbManager->table('rankingcoach_app_keywords')
    ->insert()
    ->set([
        'name' => 'New Keyword',
        'alias' => 'new-keyword',
        'hash' => md5('New Keyword')
    ])
    ->get();

// Example 3: UPDATE query
$updatedRows = $dbManager->table('rankingcoach_app_keywords')
    ->update()
    ->set(['alias' => 'updated-keyword'])
    ->where('id', 1)
    ->get();

// Example 4: DELETE query
$deletedRows = $dbManager->table('rankingcoach_app_keywords')
    ->delete()
    ->where('id', 1)
    ->get();

// Example 5: JOIN query with table alias
$joinResults = $dbManager->table('rankingcoach_app_keywords', 'k')
    ->select(['k.id', 'k.name', 'c.name AS category_name'])
    ->join('rankingcoach_setup_categories AS c', 'k.id = c.categoryId')
    ->where('k.name', 'example keyword')
    ->get();

// Example 5b: JOIN query with table alias and array-based join conditions
$joinArrayResults = $dbManager->table('rankingcoach_app_keywords', 'k')
    ->select(['k.id', 'k.name', 'c.name AS category_name'])
    ->join('rankingcoach_setup_categories AS c', ['id' => 'categoryId'])
    ->where('k.name', 'example keyword')
    ->get();

// Example 6: COUNT query
$count = $dbManager->table('rankingcoach_app_keywords')
    ->count();

// Example 7: Using the facade methods
$allKeywords = $dbManager->getAll(
    'rankingcoach_app_keywords',
    ['id', 'name', 'alias'],
    ['name' => 'example keyword'],
    'id',
    'DESC',
    10
);

$singleKeyword = $dbManager->getRow(
    'rankingcoach_app_keywords',
    ['id', 'name', 'alias'],
    ['id' => 1]
);

$keywordName = $dbManager->getValue(
    'rankingcoach_app_keywords',
    'name',
    ['id' => 1]
);

$keywordCount = $dbManager->count(
    'rankingcoach_app_keywords',
    ['name' => 'example keyword']
);

// Example 8: Transactions
$dbManager->beginTransaction();

try {
    $dbManager->insert('rankingcoach_app_keywords', [
        'name' => 'Transaction Keyword 1',
        'alias' => 'transaction-keyword-1'
    ]);
    
    $dbManager->insert('rankingcoach_app_keywords', [
        'name' => 'Transaction Keyword 2',
        'alias' => 'transaction-keyword-2'
    ]);
    
    $dbManager->commit();
} catch (Exception $e) {
    $dbManager->rollback();
    // Handle error
}

// Example 9: Creating and running migrations
$migrationPath = $dbManager->createMigration('AddNewColumnToKeywords');
$migrationStatus = $dbManager->getMigrationStatus();
$migrationResults = $dbManager->runMigrations();

// Example 10: Creating tables
$dbManager->createTable('keywords');

// Example 11: Complex query with multiple conditions
$complexQueryResults = $dbManager->table('rankingcoach_app_keywords')
    ->select(['id', 'name', 'alias'])
    ->where('name', 'example keyword')
    ->whereIn('id', [1, 2, 3])
    ->whereLike('alias', 'keyword')
    ->whereNotNull('hash')
    ->groupBy('name')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();

// Example 12: INSERT with ON DUPLICATE KEY UPDATE
$id = $dbManager->table('rankingcoach_app_keywords')
    ->insert()
    ->set([
        'name' => 'Duplicate Keyword',
        'alias' => 'duplicate-keyword'
    ])
    ->onDuplicate([
        'alias' => 'updated-duplicate-keyword'
    ])
    ->get();

// Example 13: Using the direct insert/update/delete methods
$newId = $dbManager->insert('rankingcoach_app_keywords', [
    'name' => 'Direct Insert',
    'alias' => 'direct-insert'
]);

$updatedRows = $dbManager->update(
    'rankingcoach_app_keywords',
    ['alias' => 'updated-direct-insert'],
    ['id' => $newId]
);

$deletedRows = $dbManager->delete(
    'rankingcoach_app_keywords',
    ['id' => $newId]
);

// Example 14: Using raw SQL
$rawSqlResults = $dbManager->query(
    "SELECT * FROM {$dbManager->db()->prefix}rankingcoach_app_keywords WHERE name LIKE '%keyword%'"
);

// Example 15: Using the tables manager directly
$tableExists = $dbManager->tables()->tableExists('rankingcoach_app_keywords');

// Example 16: Using whereOr with a closure for OR conditions
$whereOrResults = $dbManager->table('rankingcoach_app_keywords')
    ->select(['id', 'name', 'alias'])
    ->where('is_active', 1)
    ->whereOr(function($query) {
        // These conditions will be combined with OR
        $query->where('name', 'keyword1')
              ->where('name', 'keyword2')
              ->whereLike('alias', 'special');
    })
    ->get();
// Generates: SELECT id, name, alias FROM rankingcoach_app_keywords 
// WHERE 1 = 1 AND `is_active` = 1 AND (`name` = 'keyword1' OR `name` = 'keyword2' OR `alias` LIKE '%special%')

// Example 17: Using whereOr for search functionality
$searchTerm = 'example';
$searchResults = $dbManager->table('rankingcoach_app_keywords')
    ->select(['id', 'name', 'alias'])
    ->whereOr(function($query) use ($searchTerm) {
        $query->whereLike('name', $searchTerm)
              ->whereLike('alias', $searchTerm)
              ->whereLike('description', $searchTerm);
    })
    ->orderBy('name', 'ASC')
    ->limit(20)
    ->get();
// Generates: SELECT id, name, alias FROM rankingcoach_app_keywords 
// WHERE 1 = 1 AND (`name` LIKE '%example%' OR `alias` LIKE '%example%' OR `description` LIKE '%example%')
// ORDER BY name ASC LIMIT 20

// Example 18: Combining multiple whereOr conditions
$combinedOrResults = $dbManager->table('rankingcoach_app_keywords')
    ->select(['id', 'name', 'alias'])
    ->where('is_deleted', 0)
    ->whereOr(function($query) {
        $query->where('status', 'active')
              ->where('status', 'pending');
    })
    ->whereOr(function($query) {
        $query->where('type', 'keyword')
              ->where('type', 'phrase');
    })
    ->get();
// Generates: SELECT id, name, alias FROM rankingcoach_app_keywords 
// WHERE 1 = 1 AND `is_deleted` = 0 
// AND (`status` = 'active' OR `status` = 'pending') 
// AND (`type` = 'keyword' OR `type` = 'phrase')

// Example 19: Using whereOr with whereIn, whereNull and other conditions
$complexOrResults = $dbManager->table('rankingcoach_app_keywords')
    ->select(['id', 'name', 'alias'])
    ->whereOr(function($query) {
        $query->whereIn('category_id', [1, 2, 3])
              ->whereNull('parent_id')
              ->where('is_featured', 1);
    })
    ->orderBy('id', 'DESC')
    ->get();
// Generates: SELECT id, name, alias FROM rankingcoach_app_keywords 
// WHERE 1 = 1 AND (`category_id` IN (1, 2, 3) OR `parent_id` IS NULL OR `is_featured` = 1)
// ORDER BY id DESC

// Example 20: Using whereOr with raw conditions
$rawOrResults = $dbManager->table('rankingcoach_app_keywords')
    ->select(['id', 'name', 'alias'])
    ->whereOr(function($query) {
        $query->whereRaw("YEAR(created_at) = 2023")
              ->whereRaw("MONTH(created_at) = 6")
              ->whereRaw("name REGEXP '^[A-Z]'");
    })
    ->get();
// Generates: SELECT id, name, alias FROM rankingcoach_app_keywords 
// WHERE 1 = 1 AND (YEAR(created_at) = 2023 OR MONTH(created_at) = 6 OR name REGEXP '^[A-Z]')
