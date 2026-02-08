<?php
use RankingCoach\Inc\Core\Admin\Pages\UpsellPage;

/** @var UpsellPage $this */
?>
<style>
    #upsell-rankingcoach-page {
        position: fixed;
        top: 32px;
        left: 160px;
        right: 0;
        bottom: 0;
        background: #fff;
        display: grid;
        justify-items: center;
        align-content: center;
        overflow-y: auto;
        transition: left 0.1s;
        z-index: 1;
    }

    body.folded #upsell-rankingcoach-page {
        left: 36px;
    }

    @media screen and (max-width: 782px) {
        #upsell-rankingcoach-page {
            top: 46px;
            left: 0;
        }
    }
</style>
<div id="upsell-rankingcoach-page">
</div>
<script>
    console.log('[PHP] upsell-dc-page-react.php template loaded');
    console.log('[PHP] Container element:', document.getElementById('upsell-rankingcoach-page'));
</script>
