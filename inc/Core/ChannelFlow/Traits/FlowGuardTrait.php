<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\ChannelFlow\Traits;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\ChannelFlow\ChannelResolver;
use RankingCoach\Inc\Core\ChannelFlow\FlowManager;
use RankingCoach\Inc\Core\ChannelFlow\OptionStore;

/**
 * FlowGuardTrait
 *
 * Shared Flow evaluation helper for admin pages to avoid duplication.
 * New canonical location: RankingCoach\Inc\Core\ChannelFlow\Traits\FlowGuardTrait
 */
trait FlowGuardTrait
{
    /**
     * Evaluate the current channel flow and return a normalized result.
     *
     * @return array{channel?:string,next_step?:string,description?:string,meta?:mixed}
     */
    private function evaluateFlow(): array
    {
        $store    = new OptionStore();
        $resolver = new ChannelResolver($store);
        $flow     = new FlowManager($store, $resolver);

        return $flow->evaluate();
    }
}