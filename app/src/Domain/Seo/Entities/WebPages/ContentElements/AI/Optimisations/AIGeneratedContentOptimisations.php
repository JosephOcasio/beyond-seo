<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages\ContentElements\AI\Optimisations;

use App\Domain\Common\Entities\Keywords\Keywords;
use DDD\Domain\Base\Entities\ObjectSet;

/**
 * @property AIGeneratedContentOptimisation[] $elements;
 * @method AIGeneratedContentOptimisation|null getByUniqueKey(string $uniqueKey)
 * @method AIGeneratedContentOptimisation[] getElements()
 * @method AIGeneratedContentOptimisation|null first()
 */
class AIGeneratedContentOptimisations extends ObjectSet
{
    /**
     * Prompt Parameters
     */

    /** @var string Prompt parameter for the content type */
    public const PROMPT_PARAMETER_CONTENT_TYPE = 'CONTENT_TYPE';

    /** @var string Prompt parameter for the original content */
    public const PROMPT_PARAMETER_ORIGINAL_CONTENT = 'ORIGINAL_CONTENT';

    /** @var string Prompt parameter for the keywords to be used in the content */
    public const PROMPT_PARAMETER_KEYWORDS_TO_BE_USED_IN_CONTENT = 'KEYWORDS_TO_BE_USED_IN_CONTENT';

    /** @var string|null The original content */
    public ?string $originalContent = null;

    /** @var string|null The content type */
    public ?string $contentType = null;

    /** @var Keywords|null The keywords that had to be used in content */
    public ?Keywords $keywordsThatHadToBeUsedInContent = null;

    /** @var string|null The optimised content */
    public ?string $optimisedContent = null;

    /** @var AIGeneratedContentOptimisationsLevel|null The level of the optimisations that were made to the content */
    public ?AIGeneratedContentOptimisationsLevel $optimisationsLevel = null;
}
