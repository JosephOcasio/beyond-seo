<?php
namespace RankingCoach\Inc\Core\ChannelFlow\Channels;

use RankingCoach\Inc\Core\ChannelFlow\FlowState;

interface ChannelInterface {
    public function getNextStep(FlowState $state): array;
}