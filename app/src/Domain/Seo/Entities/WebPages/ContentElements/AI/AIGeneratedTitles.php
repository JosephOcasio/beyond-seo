<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements\AI;

use App\Domain\Integrations\WordPress\Seo\Repo\RC\RCWebPageTitleMetaTags;
use App\Domain\Seo\Entities\WebPages\ContentElements\Title;
use App\Domain\Seo\Repo\Argus\WebPages\ContentElements\AI\ArgusAIGeneratedTitles;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * @method Title getParent()
 * @property Title $parent
 * @property Title[] $elements;
 * @method Title getByUniqueKey(string $uniqueKey)
 * @method Title[] getElements()
 */
#[LazyLoadRepo(LazyLoadRepo::RC, RCWebPageTitleMetaTags::class)]
class AIGeneratedTitles extends AIGeneratedContentElements
{
    public const MAIN_CONTENT_LENGTH_USED_IN_USER_CONTENT = 400;
}
