<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use JetBrains\PhpStorm\NoReturn;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\Helpers\Traits\RcApiTrait;

/**
 * Singleton ExceptionHandler
 */
class ExceptionHandler {

	use RcLoggerTrait;
	use RcApiTrait;

	/**
	 * Returns the singleton instance of ExceptionHandler.
	 *
	 * @return ExceptionHandler
	 */
	private static ?self $instance = null;

	private string $errorStylePath;
	private string $errorTemplatePath;
	private string $plugin;

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct(string $plugin)
	{
		$this->plugin = $plugin;

		// Dynamically determine the directory of the current class
		$baseDir = plugin_dir_url(__FILE__); // Directory where the class is located

		// Configure paths relative to the `assets` directory
		$this->errorStylePath = plugins_url('assets/css/error-styles.css', __FILE__); // Keep URL for enqueuing styles
		$this->errorTemplatePath = plugin_dir_path(__FILE__) . 'templates/error-template.php'; // File system path for template rendering
	}

	/**
	 * Register error hooks
	 */
	public static function registerErrorHooks(string $plugin): void
	{
		$instance = self::getInstance($plugin);
		add_action('admin_enqueue_scripts', [$instance, 'enqueueErrorStyles']);

	}

	/**
	 * Get the singleton instance
	 */
	public static function getInstance(string $plugin): self
	{
		if (self::$instance === null) {
			self::$instance = new self($plugin);
		}
		return self::$instance;
	}

	/**
	 * Handle an exception and render an error page
	 * @throws Exception
	 */
	#[NoReturn]
	public function error(?Exception $exception = null, ?string $additionalContent = null): void
	{
		if($additionalContent) {
			$additionalContent = json_encode($additionalContent, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		}

		if($exception === null) {
			return;
		}

		// Load the plugin data
		if (!function_exists('get_plugin_data')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Deactivate the plugin if needed, based on the exception that occurred
		$this->deactivatePluginIfNeeded($exception);

		// Build the error context, means the data that will be displayed on the error page
		$context = $this->buildErrorContext($exception, $additionalContent);

		// Log the exception in the error log and custom log file
		$this->logException($exception, $context, $additionalContent);

		$triggeredOnRequestContext = false;
		// Check if the request is an AJAX request
		if (defined('DOING_AJAX') && DOING_AJAX) {
			$triggeredOnRequestContext = true;
		}

		// Check for a Postman request (Postman often sets a custom user agent or headers)
		$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
		if ( str_contains( $user_agent, 'PostmanRuntime' ) ) {
			$triggeredOnRequestContext = true;
		}

        if($exception instanceof BaseException && $exception->throwException) {
            $triggeredOnRequestContext = true;
        }

        if($triggeredOnRequestContext) {
            throw new Exception(esc_html($exception->getMessage() . ($additionalContent ? ' >>> ' . $additionalContent : '')));
        }

		// Render the error page, based on the error context and the additional content
		$this->renderErrorPage($context);
	}

	/**
	 * Enqueue the error styles
	 */
	public function enqueueErrorStyles(): void
	{
		wp_enqueue_style('rankingcoach-error-styles', $this->errorStylePath, [], '1.0.0');
	}

	/**
	 * Deactivate the plugin if needed
	 */
	private function deactivatePluginIfNeeded(Exception $exception): void
	{
		$allowedDeactivatePluginFor = [PluginActivationException::class];
		if (in_array(get_class($exception), $allowedDeactivatePluginFor, true)) {
			deactivate_plugins($this->plugin);
		}
	}

	/**
	 * Build the error context
	 */
	private function buildErrorContext(Exception $exception, ?string $additionalData = null): array
	{
		$pluginData = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin, false, false);
		$context = ErrorContextFactory::create($exception);
		$context['plugin_name'] = $pluginData['Name'] ?? 'Plugin';
		$context['additional_data'] = $additionalData;
		return $context;
	}

	/**
	 * Log the exception
	 */
	private function logException(Exception $exception, array $context, ?string $additionalContent): void
	{
		$message = $this->buildExceptionMessage($exception, $context, $additionalContent);
		$this->log($message, 'ERROR');
	}

	/**
	 * Render the error page
	 */
	#[NoReturn]
	private function renderErrorPage(array $context): void
	{
        // Ignore PHPCS warnings for output escaping, as the template is designed to handle it
        $content = TemplateRenderer::render($this->errorTemplatePath, $context);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        wp_die($content, esc_html($context['title']), ['back_link' => true]);
	}
}
