<?php
declare( strict_types=1 );

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\Tags;

use App\Domain\Base\Repo\RC\Attributes\RCLoad;
use App\Domain\Base\Repo\RC\Utils\RCApiOperations;
use App\Domain\Integrations\WordPress\Common\Entities\WPVariables;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\WPWebPageMetaTag;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\MetaTags\WPWebPageMetaTags;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\InternalDBWPWebPageMetaTagTitle;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\RCWebPageTitleMetaTags;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\ForbiddenException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use ReflectionException;

/**
 * Class WPWebPageTitleMetaTag
 * @property WPWebPageMetaTags $parent
 * @method WPWebPageMetaTags getParent()
 */
#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, InternalDBWPWebPageMetaTagTitle::class)]
#[LazyLoadRepo(LazyLoadRepo::RC, RCWebPageTitleMetaTags::class)]
class WPWebPageTitleMetaTag extends WPWebPageMetaTag {

    use RcLoggerTrait;

    /** @var string The type of the meta-tag */
    public string $type = WPWebPageMetaTag::TAG_TYPE_TITLE;

    /** @var string|null The parsed content */
    public ?string $parsed = null;

    /**
     * WPWebPageTitleMetaTag constructor.
     *
     * @param int $postId
     */
    public function __construct(int $postId = 0) {
        $this->type = WPWebPageMetaTag::TAG_TYPE_TITLE;
        parent::__construct($postId, $this->type);
    }

    /**
     * Parses the content of the meta tag by applying the template and replacing variables.
     *
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

    /**
     * @return Entity
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws JsonException
     * @throws ReflectionException
     * @throws \Doctrine\ORM\Mapping\MappingException
     */
    public function autoSuggest(): Entity
    {
        RCLoad::$logRCCalls = true;

        $repo = new RCWebPageTitleMetaTags();
        $repo->fromEntity($this);
        $repo->rcLoad(false, false);
        $entity = $repo->toEntity();

        $this->log_json([
            'operation_type' => 'meta_optimization',
            'operation_status' => 'success',
            'api_calls' => RCApiOperations::getExecutedRCCalls(),
            'context_entity' => 'post',
            'context_id' => $this->postId,
            'context_type' => get_post_type($this->postId) ?: 'unknown',
            'execution_time' => null,
            'error_details' => null,
            'metadata' => [
                'meta_tag_type' => 'title',
                'optimization_type' => 'auto_suggest',
                'original_content' => $this->content,
                'suggested_content' => $entity->content ?? null
            ]
        ], 'title_autosuggest');
        RCLoad::$logRCCalls = false;

        $repoDB = new InternalDBWPWebPageMetaTagTitle();
        $entity->autoGenerated = true;
        $entity->variables = new WPVariables();
        $updatedEntity = $repoDB->update($entity);
        
        // Sync auto-suggested title to wp_postmeta
        if ($entity->postId) {
            $parsedContent = $entity->parseByTemplate();
            update_post_meta($entity->postId, MetaTags::META_SEO_TITLE, $parsedContent);
            
            // Save template and variables for future reference
            if ($entity->template) {
                update_post_meta($entity->postId, MetaTags::META_SEO_TITLE . '_template', $entity->template);
            }
        }
        
        return $updatedEntity;
    }
}