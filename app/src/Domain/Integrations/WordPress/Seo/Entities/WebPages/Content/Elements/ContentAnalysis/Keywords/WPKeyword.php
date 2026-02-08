<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords;

use App\Domain\Common\Entities\Keywords\Keyword;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPKeyword;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Class WPKeyword
 *
 * @method WPKeywords getParent()
 * @property WPKeywords $parent
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPKeyword::class)]
class WPKeyword extends Keyword
{
    /** @var int|null The database ID of the keyword */
    public ?int $id = null;

    /** @var int|null The RankingCoach ID of this keyword */
    public ?int $externalId = null;

    /**
     * Creates a WPKeyword instance from a Keyword entity.
     */
    public static function createFromKeyword(Keyword $keyword): WPKeyword
    {
        $wpKeyword = new self();
        $wpKeyword->name = $keyword->name;
        $wpKeyword->hash = $keyword->hash;
        $wpKeyword->alias = $keyword->alias;
        $wpKeyword->externalId = $keyword->id;

        return $wpKeyword;
    }

    /**
     * Returns a unique key for the keyword.
     * If the keyword has an ID, it uses that; otherwise, it uses the hash.
     *
     * @return string
     */
    public function uniqueKey(): string
    {
        if($this->id) {
            return parent::uniqueKeyStatic($this->hash);
        }
        return parent::uniqueKey();
    }
}
