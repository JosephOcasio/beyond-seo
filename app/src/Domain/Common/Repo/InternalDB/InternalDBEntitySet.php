<?php

declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB;

use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\QueryOptions\AppliedQueryOptions;
use DDD\Domain\Base\Entities\QueryOptions\QueryOptionsPropertyMapping;
use DDD\Domain\Base\Entities\QueryOptions\QueryOptionsTrait;
use DDD\Domain\Base\Repo\DatabaseRepoEntitySet;
use DDD\Domain\Base\Repo\DB\DBEntity;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineEntityRegistry;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Domain\Base\Repo\DB\Doctrine\EntityManagerFactory;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Reflection\ReflectionClass;
use DDD\Infrastructure\Traits\ReflectorTrait;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Query\Expr\From;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

abstract class InternalDBEntitySet extends DatabaseRepoEntitySet
{
    use ReflectorTrait;

    public const BASE_REPO_CLASS = null;
    public const BASE_ENTITY_SET_CLASS = null;

    /**
     * Get's the field name mapped from Entity to Database in order to be used on
     * filter or expand ooption
     *
     * @param string $inputField
     *
     * @return string
     */
    protected static function getQueryOptionsPropertyMapping(
        string $propertyName,
        string $propertyValue = null,
    ): QueryOptionsPropertyMapping {
        return new QueryOptionsPropertyMapping($propertyName, $propertyValue);
    }

    /**
     * Applies QueryOptions to QueryBuilder
     *
     * @param DoctrineQueryBuilder $queryBuilder
     *
     * @return DoctrineQueryBuilder
     * @throws ReflectionException
     */
    public static function applyQueryOptions(DoctrineQueryBuilder &$queryBuilder): DoctrineQueryBuilder
    {
        $entitySetClass = (string)static::BASE_ENTITY_SET_CLASS;
        if (!$entitySetClass || !class_exists($entitySetClass)) {
            return $queryBuilder;
        }
        $entitySetReflectionClass = ReflectionClass::instance($entitySetClass);
        if (!($entitySetReflectionClass) || !$entitySetReflectionClass->hasTrait(QueryOptionsTrait::class)) {
            return $queryBuilder;
        }
        /** @var QueryOptionsTrait $entitySetClass */
        /** @var AppliedQueryOptions $defaultQueryOptions */
        $defaultQueryOptions = $entitySetClass::getDefaultQueryOptions();
        if (!$queryBuilder->getMaxResults() && $defaultQueryOptions->getTop()) {
            $queryBuilder->setMaxResults($defaultQueryOptions->getTop());
        }
        if (!$queryBuilder->getFirstResult() && $defaultQueryOptions->getSkip()) {
            $queryBuilder->setFirstResult($defaultQueryOptions->getSkip());
        }
        if ($filters = $defaultQueryOptions->getFilters()) {
            $filters->applyFiltersToDoctrineQueryBuilder(
                queryBuilder: $queryBuilder,
                baseModelClass: self::getBaseModel(),
                mappingFunction: function (string $propetyName, string $value) {
                    return static::getQueryOptionsPropertyMapping($propetyName, $value);
                },
            );
        }
        if ($orderBy = $defaultQueryOptions->getOrderBy()) {
            $orderBy->applyOrderByToDoctrineQueryBuilder(
                queryBuilder: $queryBuilder,
                baseModelClass: self::getBaseModel(),
                mappingFunction: function (string $propetyName, string $value) {
                    return static::getQueryOptionsPropertyMapping($propetyName, $value);
                },
            );
        }
        return $queryBuilder;
    }

    /**
     * Finds elements either by queryBuilder query and returns EntitySet
     *
     * @param DoctrineQueryBuilder|null $queryBuilder
     * @param bool $useEntityRegistrCache
     * @param array $initiatorClasses
     *
     * @return EntitySet|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws MappingException
     */
    public function find(
        ?DoctrineQueryBuilder $queryBuilder = null,
        bool $useEntityRegistrCache = true,
        array $initiatorClasses = [],
    ): ?EntitySet {
        if (!$this::BASE_REPO_CLASS) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            /* translators: %s is the class name */
            throw new InternalErrorException(sprintf(__('No BASE_REPO_CLASS defined in %s', 'beyond-seo'), static::class));
        }
        if (!$this::BASE_ENTITY_SET_CLASS) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            /* translators: %s is the class name */
            throw new InternalErrorException(sprintf(__('No BASE_ENTITY_SET_CLASS defined in %s', 'beyond-seo'), static::class));
        }
        /** @var InternalDBEntity $baseRepoClass */
        $baseRepoClass = $this::BASE_REPO_CLASS;
        $baseEntityClass = $baseRepoClass::BASE_ENTITY_CLASS;
        $baseEntitySetClass = $this::BASE_ENTITY_SET_CLASS;
        $baseOrmModel = $baseRepoClass::BASE_ORM_MODEL;
        $baseOrmModelAlias = $baseOrmModel::MODEL_ALIAS;

        $entityRegistry = DoctrineEntityRegistry::getInstance();
        $entityManager = EntityManagerFactory::getInstance();

        if (!$queryBuilder) {
            $queryBuilder = $entityManager->createQueryBuilder();
        }

        $skipSelectFrom = false;
        // in case we define a join, the select from part needs to be added before the join
        // cause otherwise stupid doctrine throws an error. In case the select from is added before
        // it cannot be added twice, cause doctrine throws another supid error
        foreach ($queryBuilder->getDQLPart('from') as $fromPart) {
            /** @var From $fromPart */
            if ($fromPart->getFrom() == $baseOrmModel) {
                $skipSelectFrom = true;
            }
        }
        if (!$skipSelectFrom) {
            // we apply the select and from clause based on model and alias definitions
            $queryBuilder->addSelect($baseOrmModelAlias)->from($baseOrmModel, $baseOrmModelAlias);
        }

        // We apply the restrictions of the readRightsQuery
        /** @var DoctrineQueryBuilder $queryBuilder */
        $baseRepoClass::applyReadRightsQuery($queryBuilder);

        // We apply query options
        $queryBuilder = self::applyQueryOptions($queryBuilder);

        //handle translations
        $queryBuilder = $baseRepoClass::applyTranslationJoinToQueryBuilder($queryBuilder);

        if ($useEntityRegistrCache) {
            $className = (string)$this::BASE_ENTITY_SET_CLASS;
            // we check if an element exists in the registry
            $entitySetInstance = $entityRegistry->get(static::class, $queryBuilder);
            if ($entitySetInstance) {
                // we need to restore parent / child relationShips as we do not serialize them
                $entitySetInstance->addChildren(...$entitySetInstance->getElements());
                return $this->postProcessAfterMapping($entitySetInstance);
            }
        }

        /*
        echo $queryBuilder->getQuery()->getSQL()."\n<br />";
        foreach ($queryBuilder->getParameters() as $parameter){
            $parameterValue = is_array($parameter->getValue())? implode(', ', $parameter->getValue()): $parameter->getValue();
            echo $parameter->getName() . ' ' . $parameterValue ."\n";
        }
        echo "\n<br />\n<br />";*/
        $ormInstances = $queryBuilder->applyDistinctSubqueryIfNeededAndGetResult();

        /** @var EntitySet $entitySetInstance */
        $entitySetInstance = new $baseEntitySetClass();
        foreach ($ormInstances as $ormInstance) {
            /** @var InternalDBEntity $baseRepoInstance */
            $baseRepoInstance = new $baseRepoClass();

            $classMetadata = $entityManager->getClassMetadata($baseOrmModel);
            $primaryKeyField = $classMetadata->getSingleIdentifierFieldName();

            // in cases of queries like
            // $queryBuilder->addSelect("MATCH({$searchField},{$searchFieldInverted}) AGAINST (:search_string in boolean mode) as relevance")
            // we receive the ormInstance in an array of [0 => LegacyDBModel, 'relevance' => 1.234]
            if (is_array($ormInstance)) {
                $ormInstance = $ormInstance[0];
            }
            $entityInstance = $baseRepoInstance->find($ormInstance->{$primaryKeyField}, $useEntityRegistrCache, $ormInstance, true);
            if ($entityInstance) {
                $entitySetInstance->add($entityInstance);
            }
        }
        $entityRegistry->add($entitySetInstance, static::class, $queryBuilder, true);
        // since we load many instances and call find on their repo with loaded OrmInstance we defer cache commit to the end
        $entityRegistry::commit();
        return $this->postProcessAfterMapping($entitySetInstance);
    }

    /**
     * Update each Entity in given EntitySet and then return it back
     *
     * @param EntitySet $entitySet
     *
     * @return EntitySet
     */
    public function update(EntitySet $entitySet): EntitySet
    {
        foreach ($entitySet->getElements() as &$entity) {
            $repoClass = $entity::getRepoClass();
            $repo = $repoClass && class_exists($repoClass) ? new $repoClass() : null;
            if ($repo) {
                if (method_exists($repo, 'update')) {
                    $entity = $repo->update($entity);
                }
            }
        }
        return $entitySet;
    }

    /**
     * Deletes each Entity in given EntitySet
     *
     * @param EntitySet $entitySet
     *
     * @return void
     * @throws BadRequestException
     * @throws NonUniqueResultException
     * @throws ReflectionException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function delete(EntitySet $entitySet): void
    {
        foreach ($entitySet->getElements() as &$entity) {
            $repoClass = $entity::getRepoClass(LazyLoadRepo::INTERNAL_DB);
            /** @var DBEntity $repo */
            $repo = $repoClass && class_exists($repoClass) ? new $repoClass() : null;
            if ($repo) {
                if (method_exists($repo, 'delete')) {
                    $repo->delete($entity);
                }
            }
        }
    }

    /**
     * Shorthand method for creation of a LegacyDBQueryBuilder for internal use
     *
     * @param bool $includeModelSelectFromClause
     *
     * @return DoctrineQueryBuilder
     */
    public static function createQueryBuilder(bool $includeModelSelectFromClause = false): DoctrineQueryBuilder
    {
        /** @var InternalDBEntity $baseRepoClass */
        $baseRepoClass = static::BASE_REPO_CLASS;
        return $baseRepoClass::createQueryBuilder($includeModelSelectFromClause);
    }
}
