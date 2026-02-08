<?php

declare(strict_types=1);

namespace App\Domain\Common\Entities\Keywords;

use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\EntitySet;

/**
 * @property Keyword[] $elements;
 * @method Keyword|null first()
 * @method Keyword|null getByUniqueKey(string $uniqueKey)
 * @method Keyword[] getElements()
 */
class Keywords extends EntitySet
{
    /**
     * @param Keywords|null $other
     * @return bool
     */
    public function isEqualTo(?DefaultObject $other = null): bool
    {
        if (!$other) {
            return false;
        }
        return $this->containsSameElements($other);
    }

    public function getKeywordByName(string $keywordName): ?Keyword
    {
        $uniqueKey = Keyword::uniqueKeyStatic(Keyword::getAliasFromName($keywordName));
        return $this->getByUniqueKey($uniqueKey);
    }

    public function getCombinedKeywordsAsString(): string
    {
        $keywordsCombinedString = '';
        foreach ($this->getElements() as $keyword) {
            $keywordsCombinedString .= $keyword->name . ', ';
        }
        return substr($keywordsCombinedString, 0, -2);
    }

    public function getKeywordsAsArray(): array
    {
        $return = [];
        foreach ($this->getElements() as $keyword) {
            $return[] = $keyword->name;
        }
        return $return;
    }

    public static function createFromArray($keywordsArray): Keywords
    {
        $keywords = new static();
        foreach ($keywordsArray as $keyword) {
            $keywordObj = new Keyword();
            $keywordObj->name = $keyword->name;
            $keywordObj->hash = $keyword->hash;
            $keywordObj->alias = $keyword->alias;
            $keywordObj->id = $keyword->id;
            $keywords->add($keywordObj);
        }
        return $keywords;
    }

    public function uniqueKey(): string
    {
        $key = '';
        foreach ($this->elements as $keyword) {
            $key .= $keyword->uniqueKey();
        }
        $key = md5($key);
        return self::uniqueKeyStatic($key);
    }
}
