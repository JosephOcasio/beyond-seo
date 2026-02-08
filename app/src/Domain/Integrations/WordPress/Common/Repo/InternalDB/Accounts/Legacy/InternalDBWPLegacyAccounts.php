<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Common\Repo\InternalDB\Accounts\Legacy;

use App\Domain\Common\Repo\InternalDB\Accounts\InternalDBAccounts;
use App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy\WPLegacyAccounts;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * WordPress Account
 * @method WPLegacyAccounts find(DoctrineQueryBuilder $queryBuilder = null, $useEntityRegistryCache = true)
 */
class InternalDBWPLegacyAccounts extends InternalDBAccounts
{
    public const BASE_REPO_CLASS = InternalDBWPLegacyAccount::class;
    public const BASE_ENTITY_SET_CLASS = WPLegacyAccounts::class;

    /**
     * @param bool $useCache
     * @return WPLegacyAccounts|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function getAllAccounts(bool $useCache = true): ?WPLegacyAccounts
    {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder
            ->select('users')
            ->orderBy('users.ID', 'DESC');
        return $this->find($queryBuilder, $useCache);
    }

    /**
     * @param int $id
     * @param bool $useCache
     * @return WPLegacyAccounts|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function findAccountById(int $id, bool $useCache = true): ?WPLegacyAccounts
    {
        $queryBuilder = static::createQueryBuilder();
        $queryBuilder
            ->where('users.ID = :id')
            ->setParameter('id', $id);
        return $this->find($queryBuilder, $useCache);
    }
}