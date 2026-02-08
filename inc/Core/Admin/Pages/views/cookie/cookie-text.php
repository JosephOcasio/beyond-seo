<?php
// Centralized cookie UI text and per-browser instructions.
// Returns an associative array with keys: cookieTitle, cookieDescription, browserInstructions.
// Kept in a separate file to decouple from controller and keep translations close to UI.

if (!defined('ABSPATH')) {
    exit;
}

return [
    'cookieTitle' => __('Third-Party Cookies Required', 'beyond-seo'),
    'cookieDescription' =>
        __('To access the dashboard, please enable third-party cookies in your browser. If you prefer not to enable them, you can still view the dashboard by clicking the', 'beyond-seo')
        . ' "' . __('Open Dashboard in New Tab', 'beyond-seo') . '" '
        . __('button below', 'beyond-seo'),

    'browserInstructions' => [
        'safari' => [
            __('Open Safari Preferences (Safari → Settings)', 'beyond-seo'),
            __('Go to the "Privacy" tab', 'beyond-seo'),
            __('Uncheck "Prevent cross-site tracking"', 'beyond-seo'),
            __('Refresh this page', 'beyond-seo'),
        ],
        'chrome' => [
            __('Click the three dots menu (⋮) in the top right corner', 'beyond-seo'),
            __('In the left sidebar, click Privacy and security.', 'beyond-seo'),
            __('On the right click Third-party cookies', 'beyond-seo'),
            __('Pick Allow third-party cookies', 'beyond-seo'),
        ],
        'firefox' => [
            __('Click the menu button (☰) and select Settings', 'beyond-seo'),
            __('Go to Privacy & Security panel', 'beyond-seo'),
            __('Under "Enhanced Tracking Protection", select "Standard" or "Custom"', 'beyond-seo'),
            __('If Custom, uncheck "Cookies" or select "Cross-site tracking cookies"', 'beyond-seo'),
            __('Refresh this page', 'beyond-seo'),
        ],
        'edge' => [
            __('Click the three dots menu (...) in the top right corner', 'beyond-seo'),
            __('Go to Settings → Cookies and site permissions → Cookies and site data', 'beyond-seo'),
            __('Turn off "Block third-party cookies"', 'beyond-seo'),
            __('Refresh this page', 'beyond-seo'),
        ],
        'default' => [
            __('Open your browser\'s Settings or Preferences', 'beyond-seo'),
            __('Look for Privacy, Security, or Cookies settings', 'beyond-seo'),
            __('Enable third-party cookies or disable tracking protection', 'beyond-seo'),
            __('Refresh this page', 'beyond-seo'),
        ],
    ],
];
