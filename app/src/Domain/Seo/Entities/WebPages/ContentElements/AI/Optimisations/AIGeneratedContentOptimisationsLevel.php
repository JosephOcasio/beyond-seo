<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements\AI\Optimisations;

use App\Domain\Seo\Entities\AISeoTexts\Optimisations\AISeoTextOptimisationLevel;
use App\Domain\Seo\Repo\Argus\WebPages\ContentElements\AI\Optimisations\ArgusAIGeneratedContentOptimisationsLevel;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusAIGeneratedContentOptimisationsLevel::class)]
class AIGeneratedContentOptimisationsLevel extends AISeoTextOptimisationLevel
{

}
