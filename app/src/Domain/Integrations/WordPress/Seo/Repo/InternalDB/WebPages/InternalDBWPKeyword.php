<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages;

use App\Domain\Common\Repo\InternalDB\InternalDBEntity;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBAppKeywordModel;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords\WPKeyword;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Domain\Base\Repo\DB\Doctrine\EntityManagerFactory;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\TransactionRequiredException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * @method WPKeyword find(DoctrineQueryBuilder|string|int $idOrQueryBuilder, bool $useEntityRegistryCache = false, ?InternalDBAppKeywordModel &$loadedOrmInstance = null, bool $deferredCaching = false)
 * @method save(WPKeyword $keyword)
 * @property InternalDBAppKeywordModel $ormInstance
 */
class InternalDBWPKeyword extends InternalDBEntity
{
    public const BASE_ENTITY_CLASS = WPKeyword::class;
    public const BASE_ORM_MODEL = InternalDBAppKeywordModel::class;

    /**
     * Lazy loads a question entity
     *
     * @param DefaultObject $initiatingEntity The entity requesting the lazy load
     * @param LazyLoad $lazyloadAttributeInstance The LazyLoad attribute
     * @return WPKeyword The loaded question entity
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function lazyload(
        DefaultObject &$initiatingEntity,
        LazyLoad &$lazyloadAttributeInstance
    ): WPKeyword {
        parent::lazyload($initiatingEntity, $lazyloadAttributeInstance);
        return $this->mapToEntity($lazyloadAttributeInstance->useCache);
    }

    /**
     * @param bool $useEntityRegistryCache
     *
     * @return WPKeyword
     * @throws ReflectionException
     */
    public function mapToEntity( bool $useEntityRegistryCache = false ): WPKeyword
    {
        /** @var WPKeyword $entity */
        $entity = parent::mapToEntity( $useEntityRegistryCache );
        $ormInstance = $this->ormInstance;

        $entity->id = $ormInstance->id;
        $entity->name = $ormInstance->name;
        $entity->alias = $ormInstance->alias;
        $entity->hash = $ormInstance->hash;
        $entity->externalId = $ormInstance->externalId;
        return $entity;
    }

    /**
     * Maps an entity to the ORM instance
     *
     * @param WPKeyword $entity The entity to map
     * @return bool True if mapping was successful
     * @throws ReflectionException
     */
    protected function mapToRepository(DefaultObject &$entity): bool
    {
        /** @var WPKeyword $entity */
        parent::mapToRepository($entity);
        $ormInstance = $this->ormInstance;

        if(isset($entity->id)) {
            $ormInstance->id = (int) $entity->id;
        }

        if (isset($entity->externalId)) {
            $ormInstance->externalId = $entity->externalId;
        }

        if (isset($entity->name)) {
            $ormInstance->name = $entity->name;
        }

        if (isset($entity->alias)) {
            $ormInstance->alias = $entity->alias;
        }

        if (isset($entity->hash)) {
            $ormInstance->hash = $entity->hash;
        }

        $this->ormInstance = $ormInstance;
        return true;
    }

    /**
     * Deletes a keyword entity from the database
     *
     * @param WPKeyword $entity The entity to delete
     * @return bool True if deletion was successful
     * @throws ORMException
     * @throws MappingException
     * @throws OptimisticLockException
     * @throws TransactionRequiredException
     */
    public function delete(DefaultObject &$entity): bool
    {
        if (empty($entity->id)) {
            return false;
        }


        $entityManager = EntityManagerFactory::getInstance();
        $classMetadata = $entityManager->getClassMetadata(self::BASE_ORM_MODEL);
        $primaryKeyField = $classMetadata->getSingleIdentifierFieldName();
        
        // Find the entity in the database
        $ormInstance = $entityManager->find(self::BASE_ORM_MODEL, $entity->id);
        
        if (!$ormInstance) {
            return false;
        }
        
        // Remove the entity
        $entityManager->remove($ormInstance);
        $entityManager->flush();
        
        return true;
    }
}
