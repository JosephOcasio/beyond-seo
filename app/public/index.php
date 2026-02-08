<?php
declare(strict_types=1);

use DDD\Symfony\Kernels\DDDKernel;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\Initializers\Hooks;
use RankingCoach\Inc\Core\HooksManager;

// we need to set this path prefix, otherwise symfony will not generate routes correctly
if (!function_exists('clearCache')) {
    // assign request URI
    $requestUri = $_SERVER['REQUEST_URI'];
	if (str_starts_with($requestUri, '/wp-json/rankingcoach')) {
        // alter/change the request URI so Symfony can handle it properly
		$_SERVER['REQUEST_URI'] = str_replace('/wp-json/rankingcoach', '', $requestUri);
	}
    // re-assign request URI
    $requestUri = $_SERVER['REQUEST_URI'];

    // we need to set https otherwise symfony will not notice that it is running https and when enforcing https
    $_SERVER['HTTPS'] = 'off';

	$routes = [
		'/api/' => 'api',
	];
	$isSymfonyRoot = false;
	foreach ($routes as $route => $kernelPrefix) {
		if (str_starts_with($requestUri, $route)) {
			$isSymfonyRoot = true;
			break;
		}
	}

	if (!$isSymfonyRoot) {
		echo 'Not a Symfony root';
		die();
	}

    $debug_param = WordpressHelpers::sanitize_input('GET', 'debug');
    if (!empty($debug_param)) {
        $debug = filter_var($debug_param, FILTER_VALIDATE_BOOLEAN);
        setcookie('symfony_debug', json_encode($debug), time() + 3600 * 24, '/');
        $_COOKIE['symfony_debug'] = $debug;
    }
	// Get the current working directory
	$currentDirectory = getcwd();

	$appPrefix = 'app';
	if (!defined('APP_PREFIX')) {
		define('APP_PREFIX', $appPrefix);
	}

	$debug = false;
	$cookie_debug = WordpressHelpers::sanitize_input('COOKIE', 'symfony_debug');
	if (!empty($cookie_debug)) {
		$debug = (bool)$cookie_debug;
	}

	$projectDir = realpath(__DIR__ . '/../');
	define('APP_ROOT_DIR', $projectDir);

	$clearCaches = !empty(WordpressHelpers::sanitize_input('GET', 'reset_caches'));
	function clearCache($kernel)
	{
		$environments = ['dev', 'prod']; // Environments to clear cache for
		// Execute cache:clear for each console and environment
		foreach ($environments as $env) {
			$console = dirname(__DIR__) . "/bin/console_api_{$kernel}";
			$cacheDir = dirname(__DIR__) . "/var/cache/{$kernel}/{$env}/";
			exec('rm -rf ' . escapeshellarg($cacheDir));
			exec("php $console cache:clear --env=$env");
		}
		exec('cd .. && composer dump-autoload');
		//apcu_clear_cache();
        if( function_exists('opcache_reset') )
		    opcache_reset();
	}
}

require_once realpath(__DIR__ . '/../../') . '/inc/Core/Plugin/functions.php';
require_once realpath(__DIR__ . '/../../') . '/inc/Core/Plugin/safe_polyfills.php';

$loader = rc_load_wrapped_autoloader(realpath(__DIR__ . '/../') . '/vendor/autoload.php');
try {
    (new Hooks(new HooksManager()))->initialize();
} catch (ReflectionException|Exception $e) {
    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    echo esc_html($e->getMessage());
    die();
}

$matchedKernelPrefix = 'api';
return function (array $context) use ($projectDir, $debug, $clearCaches, $matchedKernelPrefix) {
	if ($clearCaches) {
		clearCache($matchedKernelPrefix);
	}
	try {
		$kernel = new DDDKernel($context['APP_ENV'], (bool)$context['APP_DEBUG'] && $debug);
		$kernel->setProjectDir($projectDir);
		$kernel->setKernelPrefix($matchedKernelPrefix);
		return $kernel;
	} catch (Exception $e) {
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		echo esc_html($e->getMessage());
		die();
	}
};
