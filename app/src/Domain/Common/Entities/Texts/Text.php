<?php

declare (strict_types=1);

namespace App\Domain\Common\Entities\Texts;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Libs\Datafilter;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use DDD\Infrastructure\Validation\Constraints\Choice;

/**
 * @property Texts $parent
 * @method Texts getParent()
 */
class Text extends ValueObject
{
    /** @var string Formal writing style */
    public const WRITING_STYLE_FORMAL = 'FORMAL';

    /** @var string Informal writing style */
    public const WRITING_STYLE_INFORMAL = 'INFORMAL';

    /** @var string The context of the translation is singular */
    public const CONTEXT_ONE = 'ONE';

    /** @var string The context of the translation is plural */
    public const CONTEXT_MANY = 'MANY';

    /** @var string|null The content of the text */
    public ?string $content;

    /** @var string|null Optional hint to provide more context for translations */
    public ?string $translationHint;

    /** @var string|null The locale of the text */
    #[HideProperty]
    public ?string $locale;

    /** @var string Writing style of the content (personal or formal) */
    #[Choice([self::WRITING_STYLE_INFORMAL, self::WRITING_STYLE_FORMAL])]
    public string $writingStyle = self::WRITING_STYLE_INFORMAL;

    /** @var int|string|null External id to reference the text, e.g. id of AppTranslationKey */
    public int|string|null $externalId;

    /** @var bool If true, the main subject of the translation key requires a context, means one and many */
    public bool $requiresContext = false;

    /** @var string The translation context, one or many for singular and plural differentiation (e.g. for Project, Projects depending on number) */
    #[Choice([self::CONTEXT_ONE, self::CONTEXT_MANY])]
    public string $context = self::CONTEXT_ONE;

    public function __construct(
        string $content = null,
        string $language = null,
        string $countryShortCode = null,
        string $locale = null,
        string $writingStyle = self::WRITING_STYLE_FORMAL,
        int|string|null $externalId = null,
        bool $requiresContext = false,
        ?string $translationHint = null,
        string $context = self::CONTEXT_ONE
    ) {
        parent::__construct();
        $this->content = $content;
        $this->locale = $locale;
        $this->writingStyle = $writingStyle;
        $this->externalId = $externalId;
        $this->requiresContext = $requiresContext;
        $this->translationHint = $translationHint;
    }

    public function getWordCount()
    {
        return Datafilter::wordcount($this->content);
    }

    public function uniqueKey(): string
    {
        return self::getUniqueKeyForParameters(
            $this->externalId ?? null,
            $this->content ?? null,
            $this->locale ?? null,
            $this->writingStyle ?? self::WRITING_STYLE_INFORMAL,
            $this->context ?? self::CONTEXT_ONE

        );
    }

    public static function getUniqueKeyForParameters(
        string|int|null $externalId = null,
        ?string $content = null,
        ?string $locale = null,
        ?string $writingStyle = self::WRITING_STYLE_INFORMAL,
        ?string $context = self::CONTEXT_ONE
    ): string {
        $key = (string)$externalId ?? '';
        if (!$key) {
            $key = $content ?? '';
        }
        $key .= '_' . ($locale ?? '') . '_' . ($writingStyle ?? '') . '_' . $context;
        return Text::uniqueKeyStatic($key);
    }
}