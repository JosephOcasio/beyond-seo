<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/**
 * Configure plugin-check tool to ignore third-party dependencies
 */
add_filter('wp_plugin_check_ignore_directories', function(array $directories): array {
    // Add action-scheduler directory to ignored directories
    $directories[] = 'inc/Core/Plugin/action-scheduler';
    // Add shell directory to ignored directories
    $directories[] = 'shell';
    // Add tools directory to ignored directories
    $directories[] = 'tools';
    // Add app/var directory to ignored directories
    $directories[] = 'app/var';
    // Add react directory to ignored directories
    $directories[] = 'react';
    // Add react directory to ignored directories
    $directories[] = 'extension';

    return $directories;
}, 10, 1);

/**
 * Configure plugin-check tool to ignore specific third-party files
 * Additional file-level exclusions for external dependencies
 */
add_filter('wp_plugin_check_ignore_files', function(array $files): array {

    // Add all PHP files in action-scheduler directory to ignored files
    $action_scheduler_files = glob(RANKINGCOACH_PLUGIN_INCLUDES_DIR . 'Core/Plugin/action-scheduler/**/*.php');
    if ($action_scheduler_files) {
        foreach ($action_scheduler_files as $file) {
            // Convert absolute path to relative path from plugin root
            $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', $file);
            $files[] = ltrim($relative_path, '/');
        }
    }

    // Add shell directory to ignored directories
    $shell_files = glob(RANKINGCOACH_PLUGIN_DIR . 'shell/*.php');
    if ($shell_files) {
        foreach ($shell_files as $file) {
            // Convert absolute path to relative path from plugin root
            $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', $file);
            $files[] = ltrim($relative_path, '/');
        }
    }

    // Add tools directory to ignored directories
    $tools_files = glob(RANKINGCOACH_PLUGIN_DIR . 'tools/*.php');
    if ($tools_files) {
        foreach ($tools_files as $file) {
            // Convert absolute path to relative path from plugin root
            $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', $file);
            $files[] = ltrim($relative_path, '/');
        }
    }

    // Add app/var directory to ignored directories
    $app_var_files = glob(RANKINGCOACH_PLUGIN_DIR . 'app/var/*.php');
    if ($app_var_files) {
        foreach ($app_var_files as $file) {
            // Convert absolute path to relative path from plugin root
            $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', $file);
            $files[] = ltrim($relative_path, '/');
        }
    }

    // Add react directory to ignored directories
    // Note: This assumes that the react directory contains all kind of files that should be ignored
    $react_files = glob(RANKINGCOACH_PLUGIN_DIR . 'react/**/*.*');
    if ($react_files) {
        foreach ($react_files as $file) {
            // Convert absolute path to relative path from plugin root
            $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', $file);
            $files[] = ltrim($relative_path, '/');
        }
    }

    // Add extension directory to ignored directories
    $extension_files = glob(RANKINGCOACH_PLUGIN_DIR . 'extension/**/*.*');
    if ($extension_files) {
        foreach ($extension_files as $file) {
            // Convert absolute path to relative path from plugin root
            $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', $file);
            $files[] = ltrim($relative_path, '/');
        }
    }

    // Add specific files to ignored files
    $specific_files = [
        '.DS_Store',
        '.gitignore',
        'app/.DS_Store',
        'app/.gitignore',
        'app/.env',
    ];
    foreach ($specific_files as $file) {
        // Convert absolute path to relative path from plugin root
        $relative_path = str_replace(RANKINGCOACH_PLUGIN_DIR, '', RANKINGCOACH_PLUGIN_DIR . $file);
        $files[] = ltrim($relative_path, '/');
    }

    return $files;
}, 10, 1);

// Check if Action Scheduler is already loaded (possibly by WooCommerce or another plugin)
// Only load our version if no other version is loaded or if our version is newer
// AND ensure our plugin still exists and is active
if (
    defined('RANKINGCOACH_PLUGIN_INCLUDES_DIR') &&
    file_exists(RANKINGCOACH_PLUGIN_INCLUDES_DIR . 'Core/Plugin/action-scheduler/action-scheduler.php') &&
    is_plugin_active(plugin_basename(RANKINGCOACH_FILE)) &&
    !function_exists('action_scheduler_register_3_dot_9_dot_2') &&
    (
        !class_exists('ActionScheduler_Versions', false) ||
        (
            class_exists('ActionScheduler_Versions', false) &&
            ActionScheduler_Versions::instance()->latest_version() &&
            version_compare((string)ActionScheduler_Versions::instance()->latest_version(), '3.9.2', '<')
        )
    )
) {
    require_once RANKINGCOACH_PLUGIN_INCLUDES_DIR . 'Core/Plugin/action-scheduler/action-scheduler.php';
}