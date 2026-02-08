<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements\AI;

use App\Domain\Seo\Entities\WebPages\ContentElements\MetaDescription;
use App\Domain\Seo\Repo\Argus\WebPages\ContentElements\AI\ArgusAIGeneratedMetaDescriptions;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * @method MetaDescription getParent()
 * @property MetaDescription $parent
 * @property MetaDescription[] $elements;
 * @method MetaDescription getByUniqueKey(string $uniqueKey)
 * @method MetaDescription[] getElements()
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusAIGeneratedMetaDescriptions::class)]
class AIGeneratedMetaDescriptions extends AIGeneratedContentElements
{
    public const MAIN_CONTENT_LENGTH_USED_IN_USER_CONTENT = 400;
}
