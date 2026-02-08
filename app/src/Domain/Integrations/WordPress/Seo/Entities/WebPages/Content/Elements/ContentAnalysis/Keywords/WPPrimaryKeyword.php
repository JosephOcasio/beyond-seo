<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords;

use App\Domain\Integrations\WordPress\Seo\Repo\RC\RCWebPageKeywordsAnalysis;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * Class WPSeoKeyword
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::RC, repoClass: RCWebPageKeywordsAnalysis::class)]
class WPPrimaryKeyword extends WPKeyword
{
    
    /** @var string|null $relevance_score The relevance score */
    public ?string $relevance_score = null;

    /** @var string|null $intent The intent */
    public ?string $intent = null;

    /** @var string|null $density The density */
    public ?string $density = null;

	/** @var string|null $name The name */
	public ?string $name = null;

	/** @var string|null $alias The alias */
	public ?string $alias = null;

	/** @var string|null $unique_key The unique key */
	public ?string $hash = null;

    /**
     * WPSeoKeyword constructor.
     * @param string|null $relevance_score
     * @param string|null $intent
     * @param string|null $density
     */
    public function __construct(?string $relevance_score = null, ?string $intent = null, ?string $density = null)
    {
        $this->relevance_score = $relevance_score ?? null;
        $this->intent = $intent ?? null;
        $this->density = $density ?? null;

        parent::__construct();
    }
}