<?php
declare( strict_types=1 );

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\Websites;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Integrations\WordPress\Seo\Entities\Websites\WPWebsiteDatabase;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Attributes\EntityCache;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Cache\Cache;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use ReflectionException;

/**
 * Represents a WordPress site options entity.
 * @method WPWebsiteDatabase find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = true, array $initiatorClasses = [])
 */
#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBWPWebsiteDatabase extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPWebsiteDatabase::class;
    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPWebsiteDatabase|null
     * @throws ReflectionException
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): ?WPWebsiteDatabase {
        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * MapToEntity
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = false): WPWebsiteDatabase
    {
        $dbManager = DatabaseManager::getInstance();

        /** @var WPWebsiteDatabase $siteDatabase */
        $siteDatabase = parent::mapToEntity($useEntityRegistryCache);

        $tablesResult = $dbManager->db()->queryRaw('SHOW TABLES', 'ARRAY_N');
        $tables = [];
        if (is_array($tablesResult)) {
            foreach ($tablesResult as $row) {
                if (isset($row[0])) {
                    $tables[] = $row[0];
                }
            }
        }
        $siteDatabase->tables = $tables;

        $sizeResult = $dbManager->db()->queryRaw("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = " . $dbManager->db()->escapeValue($dbManager->db()->dbname), 'ARRAY_N');
        $size = '0 MB';
        if (is_array($sizeResult) && isset($sizeResult[0]) && isset($sizeResult[0][0])) {
            $size = $sizeResult[0][0] . ' MB';
        }
        $siteDatabase->size = $size;

        return $siteDatabase;
    }
}
