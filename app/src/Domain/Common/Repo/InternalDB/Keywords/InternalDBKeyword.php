<?php

declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB\Keywords;

use App\Domain\Common\Entities\Keywords\Keyword;
use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBKeywordModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Services\AuthService;

/**
 * @method Keyword find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = true, ?DoctrineModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 * @property InternalDBKeywordModel $ormInstance
 */
class InternalDBKeyword extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = Keyword::class;
    public const BASE_ORM_MODEL = InternalDBKeywordModel::class;

    /**
     * Applies restrictions based on Auth::instance()->getAccount
     * @param DoctrineQueryBuilder $queryBuilder
     * @return bool
     */
    public static function applyReadRightsQuery(DoctrineQueryBuilder &$queryBuilder): bool
    {
        $class = static::class;
        $keywordAlias = static::getBaseModelAlias();

        if (!self::$applyRightsRestrictions) {
            return false;
        }

        $authAccount = AuthService::instance()->getAccount();
        if (!$authAccount) {
            $queryBuilder->andWhere("{$keywordAlias}.id is null");
            return true;
        }

        return false;
    }

    public function mapToEntity(bool $useEntityRegistryCache = true): Keyword
    {
        /** @var Keyword $keyword */
        $keyword = parent::mapToEntity($useEntityRegistryCache);
        $keyword->id = $this->ormInstance->id;
        $keyword->setName($this->ormInstance->keyword);
        return $keyword;
    }
}
