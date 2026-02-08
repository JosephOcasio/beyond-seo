<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Repo\InternalDB;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Integrations\WordPress\Setup\Entities\WPSetup;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Base\BaseConstants;
use ReflectionException;

/**
 * Class InternalDBWPSetup
 * @method WPSetup find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = true, array $initiatorClasses = [])
 */
//#[EntityCache(useExtendedRegistryCache: false, ttl: 300, cacheGroup: Cache::CACHE_GROUP_PHPFILES, cacheScopes: [])]
class InternalDBWPSetup extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPSetup::class;

    /**
     * @param DefaultObject $initiatingEntity
     * @param LazyLoad $lazyloadAttributeInstance
     *
     * @return WPSetup
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): WPSetup {

        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return WPSetup
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): WPSetup
    {
        /** @var WPSetup $setup */
        $setup = parent::mapToEntity($useEntityRegistryCache);

        $setup->isPluginOnboarded =
            get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP, false) == true &&
            !empty(get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE, null));
        $setup->lastPluginUpdate = (int)get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE, null);
        $setup->isApplicationOnboarded =
            get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC, false) == true &&
            !empty(get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE, null));
        $setup->lastApplicationUpdate = (int)get_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE, null);

        return $setup;
    }
}
