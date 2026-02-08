<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\ChannelFlow;

use RankingCoach\Inc\Core\Admin\AdminManager;

/**
 * ChannelEnforcer - Enforces business rules that certain channels cannot access certain pages.
 *
 * This class acts as a gatekeeper to ensure that specific distribution channels
 * are restricted from accessing pages that are not relevant to their flow.
 *
 * Business Rules:
 * - IONOS/Extendify clients must NEVER see RegistrationPage (they come pre-registered)
 * - DIRECT clients must NEVER see ActivationPage (they need to register first)
 *
 * @package RankingCoach\Inc\Core\ChannelFlow
 */
final class ChannelEnforcer
{
    /**
     * Mapping of page names to channels that are NOT allowed to access them.
     * If a channel appears in the array for a page, access is forbidden.
     */
    private const VIOLATIONS = [
        'registration' => ['ionos', 'extendify'],  // IONOS/Extendify cannot see registration
        'activation'   => ['direct'],               // DIRECT cannot see activation
    ];

    /**
     * Mapping of blocked page/channel combinations to their alternative redirect targets.
     * Structure: [page_name => [channel => alternative_page]]
     */
    private const ALTERNATIVE_PAGES = [
        'registration' => [
            'ionos' => AdminManager::PAGE_ACTIVATION,
            'extendify' => AdminManager::PAGE_ACTIVATION,
        ],
        'activation' => [
            'direct' => AdminManager::PAGE_REGISTRATION,
        ],
    ];

    /**
     * Check if a channel is allowed to access a specific page.
     * If not allowed, terminates execution with 403.
     *
     * @param string $pageName The page name (e.g., 'registration', 'activation')
     * @param string $channel The detected channel (e.g., 'ionos', 'direct')
     * @return void
     */
    public static function enforcePageAccess(string $pageName, string $channel): void
    {
        // Check if violations exist for this page
        if (!isset(self::VIOLATIONS[$pageName])) {
            return;
        }

        // Check if the channel is in the violations list for this page
        if (in_array($channel, self::VIOLATIONS[$pageName], true)) {
            // Log the violation
            error_log(sprintf(
                'ChannelEnforcer: Blocked %s from accessing %s',
                $channel,
                $pageName
            ));

            $alternativePage = self::getAlternativePageForChannel($pageName, $channel);
            if($alternativePage !== 'main') {
                $nextStepUrl = AdminManager::getPageUrl($alternativePage);
                wp_safe_redirect($nextStepUrl);
            }
        }
    }

    /**
     * Get the appropriate redirect target for a blocked channel.
     *
     * @param string $pageName The page that was blocked
     * @param string $channel The channel that was blocked
     * @return string The page slug to redirect to
     */
    public static function getAlternativePageForChannel(string $pageName, string $channel): string
    {
        // Look up the alternative page
        if (isset(self::ALTERNATIVE_PAGES[$pageName][$channel])) {
            return self::ALTERNATIVE_PAGES[$pageName][$channel];
        }

        // Fallback to dashboard if no alternative is configured
        return 'main';
    }
}