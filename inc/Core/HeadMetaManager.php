<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Helpers\CoreHelper;

/**
 * Class HeadMetaManager
 * Handles meta output and related hooks for the head section.
 *
 * @property array $headElements Collection of head elements to output.
 */
class HeadMetaManager {

	protected ?int $outputBufferLevel = 0;

	/**
	 * Initiates output buffering to capture content.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function initializeBuffer(): void {
		ob_start();
		$this->outputBufferLevel = ob_get_level();
	}

	/**
	 * Concludes output buffering and updates the title content.
	 *
	 * @return void
	 */
	public function finalizeBuffer(): void {
        if (0 === $this->outputBufferLevel || ob_get_level() !== $this->outputBufferLevel) {
            return;
        }
        $content = $this->alterTitle((string) ob_get_clean());
        $content = apply_filters('rankingcoach_head_meta_manager_content', $content);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $content;
	}

	/**
	 * Sets up the necessary hooks for title modification and output buffering.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function configureTitleHooks(): void {
		// Adds a filter for modifying the document title.
		add_filter('pre_get_document_title', [HeadManager::class, 'retrieveDocumentTitle' ], 99999);
		// Adds a deprecated filter for older title handling.
		add_filter('wp_title', [HeadManager::class, 'retrieveDocumentTitle' ], 99999);

		// If the theme lacks title-tag support, apply output buffering manually.
		if (!current_theme_supports('title-tag')) {
			add_action('template_redirect', [$this, 'initializeBuffer'], 99999);
			add_action('wp_head', [$this, 'finalizeBuffer'], 99999);
		}
	}

	/**
	 * Updates the document title within the buffered content.
	 *
	 * @param string $bufferContent The captured buffer content.
	 * @return string The updated buffer content with modified title.
	 */
	protected function alterTitle(string $bufferContent): string {
        $brand = 'rankingCoach';
        if (!str_contains($bufferContent, '<!-- ' . $brand)) {
            return $bufferContent;
        }

        $content = preg_replace('#<title.*?/title>#s', '', $bufferContent);
		$dynamicTitle = CoreHelper::sanitize_regex_pattern(static::retrieveDocumentTitle());

        $pluginConfig = PluginConfiguration::getInstance();
        $nameOfPlugin = $pluginConfig->getPluginName();

		return preg_replace(
			'/(<!--\s' . $nameOfPlugin . '[a-z0-9\s.]+\s\|\sPowered by ' . $brand . '\.com\s-->)/i',
			"$1\r\n\t<title>$dynamicTitle</title>",
			$content,
			1
		);
	}

    /**
     * Outputs meta elements in the head section.
     *
     * @return void
     */
	public function renderHeadElements(): void {
		// Remove WordPress default canonical and robots
		remove_action('wp_head', 'rel_canonical');
        
        // Only remove wp_robots for content types that support metabox editing (posts, pages, custom post types with editor support)
        if ($this->shouldOverrideRobotsMeta()) {
            remove_action('wp_head', 'wp_robots', 1);
            remove_action('login_head', 'wp_robots', 1);
            remove_action('embed_head', 'wp_robots');
            remove_all_filters('wp_robots');
        }

		$pluginConfig = PluginConfiguration::getInstance();
		$nameOfPlugin = $pluginConfig->getPluginName();
		$versionOfPlugin = $pluginConfig->getPluginVersion();
		$stopNoElements = false;

		if ($stopNoElements && empty($this->headElements)) {
			return;
		}

        echo "\n\t<!-- " . esc_html($nameOfPlugin) . ' v' . esc_html($versionOfPlugin) . ' | Powered by rankingcoach.com -->';
        foreach ($this->headElements as $entry) {
            echo "\n\t" . wp_kses($entry, [
                    'meta' => [
                        'name' => [],
                        'property' => [],
                        'content' => [],
                        'charset' => [],
                        'http-equiv' => [],
                        'data-postid' => [],
                        'data-source' => [],
                    ],
                    'link' => [
                        'rel' => [],
                        'href' => [],
                        'type' => [],
                        'sizes' => [],
                        'hreflang' => []
                    ],
                    'title' => [],
                    'style' => [
                        'type' => [],
                        'media' => [],
                        'href' => [],
                        'scoped' => [],
                    ],
                    'script' => [
                        'type' => [],
                        'src' => [],
                        'async' => [],
                        'defer' => [],
                        'crossorigin' => [],
                        'integrity' => [],
                        'class' => [],
                    ],
                    'noscript' => []
                ]);
		}
		echo "\n\t<!-- End of " . esc_html($nameOfPlugin) . " -->\n\n";
	}

    /**
     * Determines if robots meta should be overridden based on current context.
     * Only override for content types that can have metabox settings (posts, pages, custom post types with editor).
     *
     * @return bool
     */
    private function shouldOverrideRobotsMeta(): bool {
        // Only override on singular content (posts, pages, custom post types)
        if (!is_singular()) {
            return false;
        }

        $post_type = get_post_type();
        if (!$post_type) {
            return false;
        }

        // Check if post type supports editor (which typically means it can have metaboxes)
        if (!post_type_supports($post_type, 'editor')) {
            return false;
        }

        // Additional check: ensure it's a public post type that can be edited
        $post_type_object = get_post_type_object($post_type);
        if (!$post_type_object || !$post_type_object->public) {
            return false;
        }

        return true;
    }
}
