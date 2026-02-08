<?php

declare(strict_types=1);

namespace App\Domain\Common\Entities\Keywords;

use App\Domain\Common\Repo\InternalDB\Keywords\InternalDBKeyword;
use DDD\Domain\Base\Entities\Attributes\NoRecursiveUpdate;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Infrastructure\Libs\Datafilter;

/**
 * @method Keywords getParent()
 */
#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, InternalDBKeyword::class)]
#[NoRecursiveUpdate]
class Keyword extends Entity
{
    /** @var string|null The alias for the keyword */
    public ?string $alias;

    /** @var string|null The hashed value of the keyword name */
    public ?string $hash;

    /** @var string|null The keyword name */
    public ?string $name;

    /**
     * @param string|null $name
     * @return void
     */
    public function setName(?string $name = null): void
    {
        $this->name = self::cleanName($name);
        $this->alias = self::getAliasFromName($name);
        $this->hash = md5($this->name);
    }

    /**
     * @param string $name
     * @return string
     */
    public static function cleanName(string $name): string
    {
        return strtolower(Datafilter::clean_keyword($name));
    }

    public function uniqueKey(): string
    {
        return self::uniqueKeyStatic($this->getAlias());
    }

    public static function getAliasFromName(string $keywordName): string
    {
        return Datafilter::alias($keywordName, '_');
    }

    public function getAlias(): string
    {
        if (!isset($this->alias) && isset($this->name)) {
            $this->setName($this->name);
        }
        return $this->alias;
    }
}
