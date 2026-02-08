<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages;

use App\Domain\Common\Repo\InternalDB\InternalDBEntitySet;
use App\Domain\Common\Repo\InternalDB\Models\InternalDBContentModel;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\WPWebPages;
use DDD\Domain\Base\Repo\DB\Doctrine\DoctrineQueryBuilder;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Represents a set of WordPress pages.
 *
 * @method WPWebPages find(DoctrineQueryBuilder $queryBuilder = null, $useEntityRegistryCache = true)
 */
class InternalDBWPWebPages extends InternalDBEntitySet
{
    public const BASE_REPO_CLASS = InternalDBWPWebPage::class;
    public const BASE_ENTITY_SET_CLASS = WPWebPages::class;

    /**
     * Loads the content of a WordPress page by its type.
     *
     * @param string $type
     * @return mixed
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function findWebPagesByType(string $type): WPWebPages
    {
        $queryBuilder = self::createQueryBuilder();
        $queryBuilder
            ->select('p')
            ->from(InternalDBContentModel::class, 'p')
            ->where('p.post_type = :postType')
            ->setParameter('postType', $type);
        return $this->find($queryBuilder);
    }
}