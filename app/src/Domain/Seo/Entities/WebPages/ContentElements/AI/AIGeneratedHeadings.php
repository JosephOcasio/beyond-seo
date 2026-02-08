<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements\AI;

use App\Domain\Seo\Entities\WebPages\ContentElements\Headings;
use App\Domain\Seo\Repo\Argus\WebPages\ContentElements\AI\ArgusAIGeneratedHeadings;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * @method Headings getParent()
 * @property Headings $parent
 * @property Headings[] $elements;
 * @method Headings getByUniqueKey(string $uniqueKey)
 * @method Headings[] getElements()
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusAIGeneratedHeadings::class)]
class AIGeneratedHeadings extends AIGeneratedContentElements
{
    public const MAIN_CONTENT_LENGTH_USED_IN_USER_CONTENT = 2000;
}