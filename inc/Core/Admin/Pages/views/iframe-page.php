<?php
declare(strict_types=1);

use RankingCoach\Inc\Core\Admin\AdminManager;

if ($openInNewTab ?? false): ?>
<div style="padding: 40px; text-align: center; background: #fff;">
    <p><?php esc_html_e('Opening RankingCoach dashboard in a new tab...', 'beyond-seo'); ?></p>
</div>
<script>
    // Open dashboard in new tab
    const iframeUrl = <?php echo wp_json_encode($iframeUrl ?? ''); ?>;
    const mainUrl = '<?php echo esc_url(AdminManager::getPageUrl(AdminManager::PAGE_MAIN)); ?>';
    let upsellUrl = '<?php echo esc_url(AdminManager::getPageUrl(AdminManager::PAGE_UPSELL)); ?>';
    const newWindow = window.open(iframeUrl, '_blank');

    <?php if($highestPlan ?? false): ?>
        upsellUrl = '<?php echo esc_url(admin_url()); ?>'
    <?php endif; ?>


    // Check if popup was blocked
    if (!newWindow || newWindow.closed || typeof newWindow.closed === 'undefined') {
        alert('Popup blocked! Please allow popups for this site and try again.');
    }
    else {
        // Redirect to admin after a short delay to allow popup to load
        setTimeout(function() {
            window.location.href = upsellUrl;
        }, 1000);
    }
</script>
<?php else: ?>
<style>
    .notice, .notice-warning, .update-nag, .updated, .error, .is-dismissible, .inline {
        display: none !important;
    }
    #wpbody-content {
        background: #fff;
    }
    .rc-skeleton-container {
        position: fixed;
        top: 28px;
        left: 142px;
        width: 100%;
        height: 100%;
        background: #fff;
        display: flex;
        padding: 40px;
        box-sizing: border-box;
        /* z-index: 99999; */
    }
    .rc-skeleton-sidebar {
        width: 220px;
        min-width: 220px;
        background: #f1f1f1;
        margin-right: 20px;
        border-radius: 4px;
    }
    .rc-skeleton-content {
        flex: 1;
        padding: 10px;
    }
    .rc-skeleton-pulse {
        background: linear-gradient(90deg, #eee 25%, #f5f5f5 50%, #eee 75%);
        background-size: 200% 100%;
        animation: rc-pulse 1.5s infinite;
        border-radius: 4px;
    }
    .rc-skeleton-menu-item {
        height: 24px;
        margin: 16px;
        border-radius: 4px;
    }
    .rc-skeleton-header {
        height: 32px;
        margin-bottom: 24px;
        width: 50%;
    }
    .rc-skeleton-card {
        height: 120px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    .rc-skeleton-row {
        height: 20px;
        margin-bottom: 12px;
        border-radius: 4px;
    }
    .rc-skeleton-button {
        height: 40px;
        width: 120px;
        margin-top: 20px;
        border-radius: 4px;
    }
    .rc-w-90 { width: 90%; }
    .rc-w-80 { width: 80%; }
    .rc-w-70 { width: 70%; }
    .rc-w-60 { width: 60%; }
    .rc-w-50 { width: 50%; }

    @keyframes rc-pulse {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>

<div id="rc-seo-iframe-wrap" style="position: relative; border: none; outline: 0;">
    <div id="rc-seo-iframe-loader" class="rc-skeleton-container">
        <!-- Sidebar Menu -->
        <div class="rc-skeleton-sidebar">
            <div class="rc-skeleton-menu-item rc-skeleton-pulse"></div>
            <div class="rc-skeleton-menu-item rc-skeleton-pulse"></div>
            <div class="rc-skeleton-menu-item rc-skeleton-pulse"></div>
            <div class="rc-skeleton-menu-item rc-skeleton-pulse"></div>
            <div class="rc-skeleton-menu-item rc-skeleton-pulse"></div>
            <div class="rc-skeleton-menu-item rc-skeleton-pulse"></div>
            <div class="rc-skeleton-menu-item rc-skeleton-pulse"></div>
        </div>

        <!-- Main Content -->
        <div class="rc-skeleton-content">
            <div class="rc-skeleton-header rc-skeleton-pulse"></div>
            <div class="rc-skeleton-card rc-skeleton-pulse"></div>

            <div class="rc-skeleton-row rc-skeleton-pulse rc-w-90"></div>
            <div class="rc-skeleton-row rc-skeleton-pulse rc-w-80"></div>
            <div class="rc-skeleton-row rc-skeleton-pulse rc-w-70"></div>

            <div class="rc-skeleton-card rc-skeleton-pulse"></div>

            <div class="rc-skeleton-row rc-skeleton-pulse rc-w-60"></div>
            <div class="rc-skeleton-row rc-skeleton-pulse rc-w-50"></div>

            <div class="rc-skeleton-button rc-skeleton-pulse"></div>
        </div>
    </div>

    <iframe id="rc-seo-iframe"
            name="rc-wp-seo"
            src="<?php echo esc_url($iframeUrl ?? ''); ?>"
            width="100%"
            height="100%"
            style="visibility: hidden;"
            allow="fullscreen; picture-in-picture"
            allowfullscreen
            onload="window.handleIframeLoaded && window.handleIframeLoaded()"></iframe>
</div>
<?php endif; ?>