<?php
declare( strict_types=1 );

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Social;

use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\WPWebPageMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\WPWebPageMetaTags;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageSocialMetaTagTitle;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\RCWebPageTitleMetaTags;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/**
 * Class WPWebPageTitleMetaTag
 * @property WPWebPageMetaTags $parent
 * @method WPWebPageMetaTags getParent()
 */
#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, InternalDBWPWebPageSocialMetaTagTitle::class)]
#[LazyLoadRepo(LazyLoadRepo::RC, RCWebPageTitleMetaTags::class)]
class WPWebPageSocialTitleMetaTag extends WPWebPageMetaTag {

    /** @var string The type of the meta-tag */
    public string $type = WPWebPageMetaTag::TAG_TYPE_SOCIAL_TITLE;

    /** @var string|null The parsed content */
    public ?string $parsed = null;

    /**
     * WPWebPageTitleMetaTag constructor.
     *
     * @param int $postId
     */
    public function __construct(int $postId = 0) {
        $this->type = WPWebPageMetaTag::TAG_TYPE_SOCIAL_TITLE;
        parent::__construct($postId, $this->type);
    }

    /**
     * @return string
     */
    public function parseByTemplate(): string
    {
        if ($this->variables && !empty($this->variables->elements)) {
            $result = '';
            
            foreach ($this->variables->elements as $variable) {
                if ($variable->type === 'text' || $variable->type === 'separator') {
                    $result .= $variable->value . ' ';
                } elseif ($variable->type === 'variable') {
                    $post = get_post($this->postId);
                    $wpVariables = WordpressHelpers::get_available_WPVariables(['post' => $post]);
                    
                    $baseKey = preg_replace('/_[a-zA-Z0-9]+$/', '', $variable->key);
                    
                    foreach ($wpVariables as $wpVariable) {
                        if ($wpVariable['key'] === $baseKey) {
                            $result .= html_entity_decode((string)$wpVariable['value']) . ' ';
                            break;
                        }
                    }
                }
            }
            
            return trim($result);
        }

	    $parsed = $this->content;
        
        if (str_contains($parsed, '{') && str_contains($parsed, '}')) {
            $post = get_post($this->postId);
            $variables = WordpressHelpers::get_available_WPVariables([ 'post' => $post]);
            
            preg_match_all('/{([^}]+)}/', $parsed, $matches);
            $placeholders = $matches[1] ?? [];
            
            foreach ($placeholders as $placeholder) {
                $baseKey = preg_replace('/_\d+$/', '', $placeholder);
                
                foreach ($variables as $variable) {
                    if ($variable['key'] === $baseKey) {
                        $parsed = str_replace('{' . $placeholder . '}', html_entity_decode((string)$variable['value']), $parsed);
                        break;
                    }
                }
            }
        }
        
        return $parsed;
    }
}
