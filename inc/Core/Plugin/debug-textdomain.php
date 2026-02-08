<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Debug file to track early text domain loading for the rankingcoach plugin
 * 
 * This file adds hooks to track when the 'rankingcoach' text domain is loaded
 * before the 'init' action, which can cause issues with WordPress 6.7+
 */

/**
 * Initialize the text domain debugging hooks
 */
function rc_debug_textdomain_init(): void {
    // Add a filter to intercept get_translations_for_domain calls for our domain
    add_filter('pre_get_translations_for_domain', 'rc_debug_textdomain_tracker', 10, 2);
    
    // Add a filter to the _doing_it_wrong function to capture detailed logs
    add_filter('doing_it_wrong_trigger_error', 'rc_debug_textdomain_logger', 10, 3);
}

/**
 * Tracks when the rankingcoach text domain is requested
 * 
 * @param null|object $translations Translations object
 * @param string $domain Text domain being requested
 * @return null|object Original translations value
 */
function rc_debug_textdomain_tracker(?object $translations, string $domain): ?object {
    // Only track our domain
    if ($domain !== 'beyond-seo') {
        return $translations;
    }
    
    // Only log if we're before init
    if (!did_action('init')) {
        // Get the call stack
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        
        // Format the trace for logging
        $formatted_trace = [];
        foreach ($trace as $i => $call) {
            if ($i === 0) continue; // Skip the current function
            
            $class = $call['class'] ?? '';
            $type = $call['type'] ?? '';
            $function = $call['function'] ?? '';
            $file = $call['file'] ?? 'unknown';
            $line = $call['line'] ?? 'unknown';
            
            // Include all files to see the full trace
            $formatted_trace[] = sprintf(
                '#%d %s%s%s() called at [%s:%s]',
                $i,
                $class,
                $type,
                $function,
                $file,
                $line
            );
        }
        
        // Log the trace information
        if (!empty($formatted_trace)) {
            $log_message = "Early 'rankingcoach' text domain request detected (before init). Call stack:\n" . 
                           implode("\n", $formatted_trace);
            
            rc_log_textdomain_issue($log_message);
        }
    }
    
    return $translations;
}

/**
 * Logs detailed information when the rankingcoach text domain is loaded too early
 * 
 * @param bool   $trigger Whether to trigger a user warning
 * @param string $function The function that was called wrong
 * @param string $message  The message explaining what was done incorrectly
 * @return bool Original $trigger value
 */
function rc_debug_textdomain_logger(bool $trigger, string $function, string $message): bool {
    // Only process for textdomain loading issues with our domain
    if ($function === '_load_textdomain_just_in_time' && str_contains($message, 'beyond-seo')) {
        // Get the call stack to identify where the text domain is being loaded
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        
        // Format the trace for logging
        $formatted_trace = [];
        foreach ($trace as $i => $call) {
            if ($i === 0) continue; // Skip the current function
            
            $class = $call['class'] ?? '';
            $type = $call['type'] ?? '';
            $function = $call['function'] ?? '';
            $file = $call['file'] ?? 'unknown';
            $line = $call['line'] ?? 'unknown';
            
            // Include all files in the trace for complete context
            $formatted_trace[] = sprintf(
                '#%d %s%s%s() called at [%s:%s]',
                $i,
                $class,
                $type,
                $function,
                $file,
                $line
            );
        }
        
        // Log the trace information
        if (!empty($formatted_trace)) {
            $log_message = "WordPress reported: Early 'rankingcoach' text domain loading detected. Call stack:\n" . 
                           implode("\n", $formatted_trace);
            
            rc_log_textdomain_issue($log_message);
        }
    }
    
    // Return the original value to maintain normal WordPress behavior
    return $trigger;
}

/**
 * Helper function to log text domain issues to file and error_log
 * 
 * @param string $log_message The message to log
 */
function rc_log_textdomain_issue(string $log_message): void {
    // Log to a specific file for this issue
    $log_file = RANKINGCOACH_LOG_DIR . 'textdomain_debug.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        @wp_mkdir_p($log_dir);
    }
    
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    if (!WP_Filesystem()) {
        return;
    }
    global $wp_filesystem;
    
    if (($wp_filesystem->is_writable($log_dir)) || ($wp_filesystem->is_file($log_file) && $wp_filesystem->is_writable($log_file))) {
        $wp_filesystem->put_contents(
            $log_file, 
            '[' . gmdate('Y-m-d H:i:s') . '] ' . $log_message . "\n\n", 
            FS_CHMOD_FILE
        );
    }
    
    // Also log to PHP error log
    error_log('RANKINGCOACH TEXTDOMAIN DEBUG: ' . str_replace("\n", ' | ', $log_message));
}
