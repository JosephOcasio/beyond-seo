<?php

declare(strict_types=1);

namespace App\Domain\Common\Repo\InternalDB;

use App\Domain\Integrations\WordPress\Common\Entities\WPVariable;
use App\Domain\Integrations\WordPress\Common\Entities\WPVariables;
use DDD\Domain\Base\Entities\ChangeHistory\ChangeHistory;
use DDD\Domain\Base\Entities\ChangeHistory\ChangeHistoryTrait;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Repo\DatabaseRepoEntity;
use DDD\Domain\Base\Repo\DB\DBEntity;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineEntityRegistry;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineModel;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Domain\Base\Repo\DB\Doctrine\EntityManagerFactory;
use DDD\Infrastructure\Base\DateTime\DateTime;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Reflection\ReflectionClass;
use DDD\Infrastructure\Services\DDDService;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\TextType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\From;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

class InternalDBEntity extends DatabaseRepoEntity
{
    /** @var string */
    public const BASE_ENTITY_CLASS = null;

    /** @var string */
    public const BASE_ORM_MODEL = null;

    /**
     * @var bool defines if the class loads a singe row or multiple rows, e.g. some site_settings are stored
     * on multiple rows, e.g. opening_hours, opening_hour_notes etc.
     */
    public bool $isMultiRowEntity = false;

    /** @var DoctrineModel|DoctrineModel[]|null model storing all the loaded data */
    protected DoctrineModel|array|null $ormInstance;

    /**
     * Finds element either by id or by queryBuilder query and returns Entity
     * @param DoctrineQueryBuilder|string|int $idOrQueryBuilder
     * @param bool $useEntityRegistrCache
     * @param DoctrineModel|null $loadedOrmInstance
     * @param bool $deferredCaching
     * @param array $initiatorClasses
     * @return DefaultObject|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     * @throws Exception
     */
    public function find(
        DoctrineQueryBuilder|string|int $idOrQueryBuilder,
        bool $useEntityRegistrCache = true,
        ?DoctrineModel &$loadedOrmInstance = null,
        bool $deferredCaching = false,
        array $initiatorClasses = [],
    ): ?DefaultObject {
        if (!$this::BASE_ENTITY_CLASS) {
            /* translators: %s is the class name */
            throw new InternalErrorException(sprintf(__('No BASE_ENTITY_CLASS defined in %s', 'beyond-seo'), static::class));
        }
        if (!$this::BASE_ORM_MODEL) {
            /* translators: %s is the class name */
            throw new InternalErrorException(sprintf(__('No BASE_ORM_MODEL defined in %s', 'beyond-seo'), static::class));
        }
        $useEntityRegistrCache = $useEntityRegistrCache && !DoctrineEntityRegistry::$clearCache;
        $baseOrmModelAlias = (static::BASE_ORM_MODEL)::MODEL_ALIAS;

        // Register 'longtext' as a custom Doctrine type
        if (!Type::hasType('longtext')) {
            Type::addType('longtext', TextType::class);
        }

        $entityRegistry = DoctrineEntityRegistry::getInstance();

        if (!($idOrQueryBuilder instanceof DoctrineQueryBuilder)) {
            $entityManager = EntityManagerFactory::getInstance();
            $queryBuilder = $entityManager->createQueryBuilder();
            // apply id query
            //$queryBuilder->andWhere($baseOrmModelAlias . '.id = :find_id')->setParameter('find_id', $idOrQueryBuilder);
            $classMetadata = $entityManager->getClassMetadata(static::BASE_ORM_MODEL);
            $primaryKeyField = $classMetadata->getSingleIdentifierFieldName();
            $queryBuilder->andWhere($baseOrmModelAlias . '.' . $primaryKeyField . ' = :find_id')
                ->setParameter('find_id', $idOrQueryBuilder);
        } else {
            $queryBuilder = $idOrQueryBuilder;
        }

        $skipSelectFrom = false;
        // in case we define a join, the select from part needs to be added before the join
        // cause otherwise stupid doctrine throws an error. In case the select from is added before
        // it cannot be added twice, cause doctrine throws another supid error
        foreach ($queryBuilder->getDQLPart('from') as $fromPart) {
            /** @var From $fromPart */
            if ($fromPart->getFrom() == $this::BASE_ORM_MODEL) {
                $skipSelectFrom = true;
            }
        }
        if (!$skipSelectFrom) {
            // we apply the select and from clause based on model and alias definitions
            $queryBuilder->addSelect($baseOrmModelAlias)->from($this::BASE_ORM_MODEL, $baseOrmModelAlias);
        }

        // We apply the restrictions of the readRightsQuery
        static::applyReadRightsQuery($queryBuilder);

        //handle translations
        $queryBuilder = static::applyTranslationJoinToQueryBuilder($queryBuilder);

        if ($useEntityRegistrCache) {
            // we check if an element exists in the registry
            $entityInstance = $entityRegistry->get(static::class, $queryBuilder);
            if ($entityInstance) {
                return $this->postProcessAfterMapping($entityInstance);
            }
        }

        // if loaded Orm Instance is passed, object is not laoded but taken from isntance instead
        if ($loadedOrmInstance) {
            $ormInstance = $loadedOrmInstance;
        } elseif ($this->isMultiRowEntity) {
            // in case of multi row entities, we get a result list instead of a single row
            $query = $queryBuilder->getQuery();
            if (!$useEntityRegistrCache) {
                $query->disableResultCache()->useQueryCache(false)->setHint(Query::HINT_REFRESH, true);
            }
            $ormInstance = $query->getResult();
        } else {
            $query = $queryBuilder->getQuery();
            if (!$useEntityRegistrCache) {
                $query->disableResultCache()->useQueryCache(false)->setHint(Query::HINT_REFRESH, true);
            }
            $ormInstance = $query->setMaxResults(1)->getResult();
            /*echo $queryBuilder->getQuery()->getSQL()."\n<br />";
            foreach ($queryBuilder->getParameters() as $parameter){
                $parameterValue = is_array($parameter->getValue())? implode(', ', $parameter->getValue()): $parameter->getValue();
                echo $parameter->getName() . ' ' . $parameterValue ."\n";
            }
            echo "\n<br />\n<br />";*/
            $ormInstance = $ormInstance[0] ?? null;
        }
        if (!$ormInstance) {
            // store empty null result
            if ($useEntityRegistrCache) {
                $null = null;
                $entityRegistry->add($null, static::class, $queryBuilder, $deferredCaching);
            }
            return null;
        }

        $this->ormInstance = $ormInstance;
        $entityInstance = $this->mapToEntity($useEntityRegistrCache, $initiatorClasses);
        //if ($useEntityRegistrCache) {
        $entityRegistry->add($entityInstance, static::class, $queryBuilder, $deferredCaching);
        //}
        // Entity Manager's unit of work cache of various types especially loaded DoctrineModels can end up using
        // the whole allocated memory, so if the memory usage is high, we clear it
        if (DDDService::instance()->isMemoryUsageHigh()) {
            EntityManagerFactory::clearAllInstanceCaches();
        }
        // post processing needs to happen after storage!!!
        return $this->postProcessAfterMapping($entityInstance);
    }

    /**
     * The main function that should be called from a repository to save everything to the db.
     * This will map the current entity data to the repository data which will then update the db using
     * Kohana ORM. It will also update any entity that is contained in this entity.
     * The depth is used to specify how many levels you want to go down with the update of your entity.
     * E.g. If the depth is 1, the entity will also save the first depth entities inside
     * @param Entity $entity
     * @param int $depth
     * @return Entity|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws ForbiddenException
     * @throws Exception
     * @throws \Doctrine\Persistence\Mapping\MappingException
     * @throws JsonException
     * @throws MappingException
     */
    public function update(
        Entity &$entity,
        int $depth = 1
    ): ?Entity {
        $validationResults = $entity->validate(depth: $depth);
        if ($validationResults !== true) {
            $badRequestException = new BadRequestException(__('Request contains invalid data', 'beyond-seo'));
            $badRequestException->validationErrors = $validationResults;
            throw $badRequestException;
        }
        if (self::$applyRightsRestrictions && !self::canUpdateOrDeleteBasedOnRoles()) {
            return $entity;
        }
        $updatedChildProperties = $this->updateDependentEntities($entity, $depth, false);
        $loadedChildPropertiesAfterUpdate = [];
        // we need the name of the updated column in case of DBENtity
        $changeHistoryAttributeInstance = null;
        if (
            is_a($this, DBEntity::class) && method_exists(
                $entity::class,
                'getChangeHistoryAttribute'
            )
        ) { // this trait is present
            /** @var ChangeHistoryTrait $entityClassName */
            $entityClassName = $entity::class;
            /** @var ChangeHistory $changeHistoryAttributeInstance */
            $changeHistoryAttributeInstance = $entityClassName::getChangeHistoryAttribute();
        }

        // Update the main entity
        if (is_a($entity::class, (string)$this::BASE_ENTITY_CLASS, true)) {
            // in case of an existing enity we first load the current row from the db into an model
            // in order to avoid setting some fields empty
            $translationAttributeInstance = static::getTranslationAttributeInstance();
            $entityManager = EntityManagerFactory::getInstance();
            $reflectionClass = ReflectionClass::instance(static::class);

            $entityId = $entity->id ?? null;
            $hasTranslations = $translationAttributeInstance && $translationAttributeInstance->hasPropertiesToTranslate(
                );
            $translationIsInDefaultLanguage = $translationAttributeInstance && $translationAttributeInstance::isCurrentLanguageCodeDefaultLanguage(
                );

            $modelName = static::BASE_ORM_MODEL;
            if (self::$applyRightsRestrictions) {
                $updateRightsQueryBuilder = static::createQueryBuilder(true);
                $updateRightsQueryApplied = static::applyUpdateRightsQuery($updateRightsQueryBuilder);
                $updateRightsQueryBuilder = $updateRightsQueryApplied ? $updateRightsQueryBuilder : null;
            } else {
                $updateRightsQueryBuilder = null;
            }

            if (!$entityId || ($entityId && (!$hasTranslations || ($hasTranslations && $translationIsInDefaultLanguage)))) {
                // if we have a new entity, we need to persist it anyway
                // if it is not a new entity and the entity has no translation to be cared of (or we are in the default language), we persist it
                // create new entity
                $mapped = $this->mapToRepository($entity);
                if ($mapped) {
                    $updatedID = $entityManager->upsert(
                        $this->ormInstance,
                        $updateRightsQueryBuilder,
                        $changeHistoryAttributeInstance ? $changeHistoryAttributeInstance?->getCreatedColumn() : null,
                        $changeHistoryAttributeInstance ? $changeHistoryAttributeInstance?->getModifiedColumn() : ''
                    );
                    if ($updatedID) {
                        if ($entityId) {
                            // if we are not inserting a new entity, but updating it, we want to return a fully loaded entity back
                            $entityManager->clear();

                            $classMetadata = $entityManager->getClassMetadata(static::BASE_ORM_MODEL);
                            $primaryKeyField = $classMetadata->getSingleIdentifierFieldName();
                            $updatedEntity = $this->find($this->ormInstance->$primaryKeyField, false);

                            foreach ($updatedEntity as $propertyName => $value) {
                                if (!isset($updatedChildProperties[$propertyName])) {
                                    // we put all properties that are not updated child properties from updatedEntity to entity
                                    $entity->$propertyName = $value;
                                    if ($value instanceof DefaultObject) {
                                        $loadedChildPropertiesAfterUpdate[$propertyName] = $propertyName;
                                        if ($value->getParent() === $updatedEntity) {
                                            // As $entity is used further it is critical to either add the $value as child
                                            // and as well implicitely set $entity as parent of $value
                                            // otherwise the parent or $value will remain $updatedEntity and stay in Nirvana
                                            $entity->addChildren($value);
                                        }
                                    }
                                }
                            }
                        } else {
                            $entity->id = $updatedID;
                        }
                        if ($hasTranslations) {
                            $translationAttributeInstance->updateOrCreateTranslation($entity, $this);
                        }
                    }
                }
            } elseif ($entityId && $hasTranslations) {
                if ($changeHistoryAttributeInstance) {
                    // in this case we need to upate the created and updated time and persist the main entity anyway, indifferent from translation

                    // we load current data and update created and updated columns
                    $this->ormInstance = isset($this->ormInstance) && $this->ormInstance ? $this->ormInstance : new (static::BASE_ORM_MODEL)(
                    );
                    $this->ormInstance->id = $entityId;
                    $this->mapCreatedAndUpdatedTime($entity);
                    $entityManager->upsert(
                        $this->ormInstance,
                        $updateRightsQueryBuilder,
                        $changeHistoryAttributeInstance ? $changeHistoryAttributeInstance?->getCreatedColumn() : null,
                        $changeHistoryAttributeInstance ? $changeHistoryAttributeInstance?->getModifiedColumn() : ''
                    );
                }
                $translationAttributeInstance->updateOrCreateTranslation($entity, $this);
            }
        }
        $this->updateDependentEntities(
            $entity,
            $depth,
            true,
            $updatedChildProperties,
            $loadedChildPropertiesAfterUpdate
        );
        return $entity;
    }

    /**
     * @param bool $useEntityRegistryCache
     * @return DefaultObject|null
     * @throws ReflectionException
     */
    public function mapToEntity(bool $useEntityRegistryCache = true): ?DefaultObject
    {
        $entityClass = static::BASE_ENTITY_CLASS;
        $entityInstance = new $entityClass();

        // Apply created and updated properties
        $entityInstance = $this->mapCreatedAndUpdatedTimesToEntity($entityInstance, $this->ormInstance);
        // apply translation content if applicable
        // ....
        return $entityInstance;
    }

    /**
     * @param DefaultObject $entityInstance
     * @param DoctrineModel|array|null $ormInstance
     * @return DefaultObject
     * @throws ReflectionException
     */
    protected function mapCreatedAndUpdatedTimesToEntity(
        DefaultObject &$entityInstance,
        DoctrineModel|array|null &$ormInstance = null
    ): DefaultObject {
        $entityReflectionClass = ReflectionClass::instance($entityInstance::class);
        if (!$entityReflectionClass->hasTrait(ChangeHistoryTrait::class)) {
            return $entityInstance;
        }

        $reflectionClass = ReflectionClass::instance(static::class);
        /** @var ChangeHistory $changeHistoryAttributeInstance */
        $changeHistoryAttributeInstance = $reflectionClass->getAttributeInstance(ChangeHistory::class);
        // configuration for change and creation date values
        if (!$changeHistoryAttributeInstance) {
            return $entityInstance;
        }

        $createdColumn = $changeHistoryAttributeInstance->getCreatedColumn();
        $modifiedColumn = $changeHistoryAttributeInstance->getModifiedColumn();
        $createdTime = null;
        if (isset($ormInstance->$createdColumn) && $ormInstance->$createdColumn) {
            if ($changeHistoryAttributeInstance->getCreatedColumnStyle() == ChangeHistory::TIMESTAMP) {
                $createdTime = DateTime::fromTimestamp($ormInstance->$createdColumn);
            } elseif ($changeHistoryAttributeInstance->getCreatedColumnStyle() == ChangeHistory::DATETIME_ATOM) {
                $createdTime = DateTime::fromString($ormInstance->$createdColumn);
            } elseif ($changeHistoryAttributeInstance->getCreatedColumnStyle() == ChangeHistory::DATETIME_SIMPLE) {
                $createdTime = DateTime::fromTimestamp($this->ormInstance->$createdColumn->getTimestamp(),);
            }
        }
        $modifiedTime = null;
        if (isset($ormInstance->$modifiedColumn) && $ormInstance->$modifiedColumn) {
            if ($changeHistoryAttributeInstance->getModifiedColumnStyle() == ChangeHistory::TIMESTAMP) {
                $modifiedTime = DateTime::fromTimestamp($ormInstance->$modifiedColumn);
            } elseif ($changeHistoryAttributeInstance->getModifiedColumnStyle() == ChangeHistory::DATETIME_ATOM) {
                $modifiedTime = DateTime::fromString($ormInstance->$modifiedColumn);
            } elseif ($changeHistoryAttributeInstance->getModifiedColumnStyle() == ChangeHistory::DATETIME_SIMPLE) {
                $modifiedTime = DateTime::fromTimestamp($ormInstance->$modifiedColumn->getTimestamp());
            }
        }
        /** @var DefaultObject&ChangeHistoryTrait $entityInstance */
        $entityInstance->changeHistory = $changeHistoryAttributeInstance;
        if ($createdTime) {
            $entityInstance->changeHistory->createdTime = $createdTime;
        }
        if ($modifiedTime) {
            $entityInstance->changeHistory->modifiedTime = $modifiedTime;
        }

        return $entityInstance;
    }

    /**
     * Maps the Entity to the Repository orm instance
     * @param Entity $entity
     * @return bool
     * @throws ReflectionException
     */
    protected function mapToRepository(Entity &$entity): bool
    {
        $this->ormInstance = isset($this->ormInstance) && $this->ormInstance ? $this->ormInstance : new (static::BASE_ORM_MODEL)();
        $this->mapCreatedAndUpdatedTime($entity);
        return false;
    }

    /**
     * @param Entity $entity
     * @return void
     * @throws ReflectionException
     */
    public function mapCreatedAndUpdatedTime(Entity &$entity): void
    {
        $reflectionClass = ReflectionClass::instance(static::class);
        $this->ormInstance = isset($this->ormInstance) && $this->ormInstance ? $this->ormInstance : new (static::BASE_ORM_MODEL)();
        /** @var ChangeHistory $changeHistoryAttributeInstance */
        $changeHistoryAttributeInstance = $reflectionClass->getAttributeInstance(ChangeHistory::class);
        if (!$changeHistoryAttributeInstance) {
            return;
        }

        $createdColumn = $changeHistoryAttributeInstance->getCreatedColumn();
        $modifiedColumn = $changeHistoryAttributeInstance->getModifiedColumn();
        /** @var ChangeHistoryTrait $entity */
        $createdTime = null;
        if (!$entity->id) {
            $entityCreatedTime = new DateTime();
            if ($changeHistoryAttributeInstance->getCreatedColumnStyle() == ChangeHistory::TIMESTAMP) {
                $createdTime = $entityCreatedTime->getTimestamp();
            } elseif ($changeHistoryAttributeInstance->getCreatedColumnStyle() == ChangeHistory::DATETIME_ATOM) {
                $createdTime = $entityCreatedTime->format(DateTime::ATOM);
            } elseif ($changeHistoryAttributeInstance->getCreatedColumnStyle() == ChangeHistory::DATETIME_SIMPLE) {
                $createdTime = $entityCreatedTime->format(DateTime::SIMPLE);
            }
        }

        $modifiedTime = null;
        $entityModifiedTime = new DateTime();
        if ($changeHistoryAttributeInstance->getModifiedColumnStyle() == ChangeHistory::TIMESTAMP) {
            $modifiedTime = $entityModifiedTime->getTimestamp();
        } elseif ($changeHistoryAttributeInstance->getModifiedColumnStyle() == ChangeHistory::DATETIME_ATOM) {
            $modifiedTime = $entityModifiedTime->format(DateTime::ATOM);
        } elseif ($changeHistoryAttributeInstance->getModifiedColumnStyle() == ChangeHistory::DATETIME_SIMPLE) {
            $modifiedTime = $entityModifiedTime->format(DateTime::SIMPLE);
        }
        if ($createdTime && property_exists($this->ormInstance, $createdColumn) && !($this->ormInstance->$createdColumn ?? null)) {
            $this->ormInstance->$createdColumn = $createdTime;
        }
        if ($modifiedTime && (!$createdTime && property_exists($this->ormInstance, $modifiedColumn)
                || ($entity->id && !isset($entity->changeHistory->createdTime))
            )
        ) {
            $this->ormInstance->$modifiedColumn = $modifiedTime;
        }
    }

    /**
     * Processes variables from JSON string and returns WPVariables object
     *
     * @param string|null $variablesJson JSON string containing variables data
     * @return WPVariables|null
     */
    protected function processVariablesFromJson(?string $variablesJson): ?WPVariables
    {
        if (!$variablesJson) {
            return null;
        }

        $dbVariables = json_decode($variablesJson);
        $variables = new WPVariables();
        
        if ($dbVariables && !empty($dbVariables->elements)) {
            foreach ($dbVariables->elements as $dbVariable) {
                switch ($dbVariable->type ?? null) {
                    case 'separator':
                        $variableType = 'separator';
                        break;
                    case 'text':
                        $variableType = 'text';
                        break;
                    default:
                        $variableType = 'variable';
                        break;
                }
                $variable = new WPVariable($dbVariable->post_id, $dbVariable->key, $dbVariable->value, $variableType);
                $variables->addVariable($variable);
            }
        }
        
        return $variables;
    }
}
