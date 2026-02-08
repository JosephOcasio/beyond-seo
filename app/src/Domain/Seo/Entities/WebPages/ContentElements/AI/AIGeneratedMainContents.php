<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements\AI;

use App\Domain\Seo\Entities\WebPages\ContentElements\MainContent;
use App\Domain\Seo\Repo\Argus\WebPages\ContentElements\AI\ArgusAIGeneratedMainContents;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * @method MainContent getParent()
 * @property MainContent $parent
 * @property MainContent[] $elements;
 * @method MainContent getByUniqueKey(string $uniqueKey)
 * @method MainContent first()
 * @method MainContent[] getElements()
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusAIGeneratedMainContents::class)]
class AIGeneratedMainContents extends AIGeneratedContentElements
{
    public const MAIN_CONTENT_LENGTH_USED_IN_USER_CONTENT = 2000;
}
