<?php
declare( strict_types=1 );

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis;

use App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy\WPLegacyAccount;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\WPWebPage;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\RCWebPageKeywordsAnalysis;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\ValueObject;

/**
 * Class WPContentAnalysis
 */
class WPContentAnalysis extends ValueObject {

    /** @var int|null $postId The post ID */
    public ?int $postId;

    /** @var WPWebPage|null $post The post entity */
    #[LazyLoad]
    public ?WPWebPage $post = null;

    /** @var WPKeywordsAnalysis $keywordsAnalysis The keywords analysis from post content */
    #[LazyLoad(repoType: LazyLoadRepo::RC, useCache: false, repoClass: RCWebPageKeywordsAnalysis::class)]
	public WPKeywordsAnalysis $keywordsAnalysis;

    /**
     * WPContentAnalysis constructor.
     *
     * @param int|null $postId The post ID
     */
    public function __construct(?int $postId = null, ?WPLegacyAccount $wpUser = null)
    {
        $this->postId = $postId;

        parent::__construct();
    }
}
