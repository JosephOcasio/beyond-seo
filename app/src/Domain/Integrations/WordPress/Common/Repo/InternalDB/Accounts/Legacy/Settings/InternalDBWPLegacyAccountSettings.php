<?php

namespace App\Domain\Integrations\WordPress\Common\Repo\InternalDB\Accounts\Legacy\Settings;

use App\Domain\Common\Repo\InternalDB\InternalDBEntitySet;
use App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy\Settings\WPLegacyAccountSettings;
use App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy\WPLegacyAccount;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * WordPress Account
 * @method WPLegacyAccountSettings find(DoctrineQueryBuilder $queryBuilder = null, $useEntityRegistryCache = true)
 */
class InternalDBWPLegacyAccountSettings extends InternalDBEntitySet
{
    public const BASE_REPO_CLASS = InternalDBWPLegacyAccountSetting::class;
    public const BASE_ENTITY_SET_CLASS = WPLegacyAccountSettings::class;

    /**
     * loads active Projects for account
     * @param WPLegacyAccount $account
     * @param LazyLoad $lazyloadPropertyInstance
     * @return WPLegacyAccountSettings|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws MappingException
     */
    public function lazyload(
        WPLegacyAccount &$account,
        LazyLoad &$lazyloadPropertyInstance
    ): ?WPLegacyAccountSettings {
        $queryBuilder = self::createQueryBuilder();
        $queryBuilder
            ->where('usermeta.user_id = :current_user_id')
            ->setParameter('current_user_id', $account->id);
        return $this->find($queryBuilder, $lazyloadPropertyInstance->useCache);
    }
}