<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Services;

use App\Domain\Common\Entities\Keywords\Keywords;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords\WPKeyword;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords\WPKeywords;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPKeywords;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Services\EntitiesService;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;

/**
 * Class WPKeywordsService
 */
class WPKeywordsService extends EntitiesService
{
    /** @var string DEFAULT_ENTITY_CLASS The default entity class. */
    public const DEFAULT_ENTITY_CLASS = WPKeyword::class;

    /**
     * Gets all keywords.
     *
     * @return WPKeywords|null
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws MappingException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    public function getAllKeywords(): ?WPKeywords
    {
        $repo = new InternalDBWPKeywords();
        return $repo->getAllKeywords();
    }

    /**
     * @param WPKeywords $newKeywords
     * @return EntitySet
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function addOnboardingKeywords(Keywords $newKeywords): EntitySet
    {
        $repoItems = new InternalDBWPKeywords();
        $currentOnboardingKeywords = $repoItems->getAllKeywords() ?? new WPKeywords();
        $keywords = WPKeywords::addOnboardingKeywords($currentOnboardingKeywords, $newKeywords);
        return $repoItems->update($keywords);
    }
}
