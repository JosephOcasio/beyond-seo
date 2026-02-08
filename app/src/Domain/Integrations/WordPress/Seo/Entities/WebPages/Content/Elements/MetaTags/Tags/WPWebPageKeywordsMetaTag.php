<?php
declare( strict_types=1 );

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\WPWebPageMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\WPWebPageMetaTags;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageMetaTagKeyword;
use App\Domain\Integrations\WordPress\Seo\Services\WPWebPageService;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Infrastructure\Exceptions\BadRequestException;
use Throwable;

/**
 * Class WPWebPageKeywordsMetaTag
 * @property string[] $additionalKeywords
 * @property WPWebPageMetaTags $parent
 * @method WPWebPageMetaTags getParent()
 */
#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, InternalDBWPWebPageMetaTagKeyword::class)]
class WPWebPageKeywordsMetaTag extends WPWebPageMetaTag {

    /** @var string The type of the meta tag */
    public string $type = WPWebPageMetaTag::TAG_TYPE_KEYWORDS;

	/** @var string|null $primaryKeyword The primary keyword */
	public ?string $primaryKeyword = null;

	/** @var string[] $additionalKeywords Additional keywords */
	public array $additionalKeywords = [];

    /**
     * WPWebPageKeywordsMetaTag constructor.
     *
     * @param int $postId
     */
    public function __construct(int $postId = 0) {
        $this->type = WPWebPageMetaTag::TAG_TYPE_KEYWORDS;
        parent::__construct(
            postId: $postId,
            type: $this->type
        );
    }

    /**
     * Load keywords from the database and populate the meta tags object.
     * @return void
     * @throws BadRequestException
     */
	public function loadKeywords(): void {
		if(!$this->postId) {
			throw new BadRequestException( 'Content ID is required to load keywords.' );
		}
		if ( ! empty( $this->content ) ) {
			$keywordsArray            = explode( ',', $this->content );
			$this->primaryKeyword     = $keywordsArray[0] ?? null;
			$this->additionalKeywords = array_map('trim', array_slice( $keywordsArray, 1 ));
		}
	}

    /**
     * Save the keywords to the database.
     * @return void
     * @throws Throwable
     */
	public function saveKeywords(): void {
		if(!$this->postId) {
			throw new BadRequestException( 'Content ID is required to save keywords.' );
		}
		$service = new WPWebPageService();
		/** @var WPWebPageMetaTag $keywords */
		$keywords = $service->getMetaTagByTypeAndId( $this->postId, WPWebPageMetaTag::TAG_TYPE_KEYWORDS );
        if(!$keywords) {
            $keywords = new WPWebPageKeywordsMetaTag( $this->postId );
        }

        // update the meta-tags in the rC tables
		$keywords->content = implode( ',', array_merge(!empty($this->primaryKeyword) ? [ $this->primaryKeyword ] : [], $this->additionalKeywords ));

        // update the meta-tags in the WordPress tables
        $payload = [
            'keywords' => $keywords,
        ];
        $service->updateMetaTags($payload, $this->postId);
	}


	/**
	 * Add a new primary keyword and append to the list of additional keywords the old primary keyword.
	 *
	 * @param string $primaryKeyword
	 *
	 * @return void
	 */
	public function addPrimaryKeyword( string $primaryKeyword ): void {
		if ( $this->primaryKeyword ) {
            array_unshift($this->additionalKeywords, $this->primaryKeyword);
        }
        $this->primaryKeyword = $primaryKeyword;
    }

    /**
     * Add a new additional keyword.
     *
     * @param string $keyword
     *
     * @return void
     */
    public function addKeyword( string $keyword ): void
    {
        if ( empty($this->primaryKeyword) ) {
            $this->addPrimaryKeyword( $keyword );
        } elseif ( ! in_array( $keyword, $this->additionalKeywords, true ) ) {
            $this->additionalKeywords[] = $keyword;
        }
    }

    /**
     * Swap a keyword from additional one to primary one.
     *
     * @param string $keyword
     *
     * @return void
     */
	public function swapKeyword( string $keyword ): void {
		$oldKeyword = $this->primaryKeyword;
		if ( in_array( $keyword, $this->additionalKeywords, true ) ) {
			$index                    = array_search( $keyword, $this->additionalKeywords, true );
			$this->primaryKeyword = $keyword;
			$this->additionalKeywords = array_merge(
				array_slice( $this->additionalKeywords, 0, $index ),
				[ $index => $oldKeyword ],
				array_slice( $this->additionalKeywords, $index + 1 )
			);
		}
	}

	/**
     * Delete the primary keyword.
     *
     * @return void
     */
	public function deletePrimaryKeyword(): void {
        $this->primaryKeyword = null;
    }

	/**
	 * Delete an additional keyword.
	 *
	 * @param string $keyword
	 *
	 * @return void
	 */
	public function deleteAdditionalKeyword( string $keyword ): void {
		$index = array_search( $keyword, $this->additionalKeywords, true );
        if ( $index!== false ) {
            unset( $this->additionalKeywords[ $index ] );
            $this->additionalKeywords = array_values( $this->additionalKeywords );
        }
	}

    /**
     * Delete a keyword from both primary and additional keywords.
     *
     * @param string $keyword
     *
     */
	public function deleteKeywordByName(string $keyword): void {
		if( $this->primaryKeyword === $keyword  ) {
			$this->deletePrimaryKeyword();
		}
		if(in_array( $keyword, $this->additionalKeywords, true )) {
			$this->deleteAdditionalKeyword( $keyword );
		}
    }

	/**
     * Convert the keywords to a string.
     *
     * @return string
     */
	public function keywordsToString(): string {
		// exclude the primary keyword from the additional keywords array before joining them into a string
		// unique the array before joining them into a string
		return implode( ',', array_unique( array_merge( $this->primaryKeyword? [ $this->primaryKeyword ] : [], $this->additionalKeywords )));
	}
}
