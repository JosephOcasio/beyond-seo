<?php
use RankingCoach\Inc\Core\Admin\Pages\ActivationPage;
use RankingCoach\Inc\Core\Admin\AdminManager;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;

/** @var ActivationPage $this */
$error_message = $errorMessage ?? '';
$activation_saved = $this->getQueryParamInt('rc_activation_saved');
$currentLanguage = WordpressHelpers::current_language_code_helper();
$supportUrl = ($currentLanguage === 'de') ? 'https://mein.ionos.de/support/contact' : 'https://my.ionos.com/support/contact';
$activationCode = $activationCode ?? '';
?>

<div class="wrap rc-activation-wrap">
    <?php if ($activation_saved > 0): ?>
        <h1><?php esc_html_e('Activation done', 'beyond-seo'); ?></h1>
        <p>
            <?php esc_html_e('Activation code processed successfully.', 'beyond-seo'); ?><br>
            <?php
            echo sprintf(
                // translators: %d is the number of seconds before redirect.
                esc_html__( 'You will be automatically redirected in %d seconds...', 'beyond-seo' ),
                    10
                );
            ?>
        </p>
        <script>
            setTimeout(function () {
                window.location.href = "<?php echo esc_url(AdminManager::getPageUrl(AdminManager::PAGE_MAIN)); ?>";
            }, 10000);
        </script>
        <a href="<?php echo esc_url(AdminManager::getPageUrl(AdminManager::PAGE_MAIN)); ?>">
            <button><?php esc_html_e('Continue onboarding', 'beyond-seo'); ?></button>
        </a>
        <?php return; ?>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div style="text-align:center;margin-bottom:24px;">
            <img src="<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/icons/beyondSEO-logo.svg'); ?>"
                 alt="BeyondSEO Logo" style="width:240px;height:auto;">
        </div>
        <div class="rc-error-icon-container"><div class="rc-error-icon">!</div></div>
        <h1><?php esc_html_e("We couldn't complete the activation", 'beyond-seo'); ?></h1>
        <p class="rc-error" style="color:#555;font-size:16px;"><?php echo esc_html($error_message); ?></p>
        <div style="display:flex;justify-content:center;gap:10px;">
            <a href="<?php echo esc_url($supportUrl); ?>" target="_blank" class="rc-contact-support-button">
                <?php esc_html_e('Contact support', 'beyond-seo'); ?>
            </a>
            <a href="<?php echo esc_url(AdminManager::getPageUrl(AdminManager::PAGE_ACTIVATION)); ?>" class="rc-try-again-button">
                <?php esc_html_e('Try another code', 'beyond-seo'); ?>
            </a>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <div style="text-align:center;margin-bottom:24px;">
        <img src="<?php echo esc_url(plugin_dir_url(RANKINGCOACH_FILE) . 'inc/Core/Admin/assets/icons/beyondSEO-logo.svg'); ?>"
             alt="BeyondSEO Logo" style="width:240px;height:auto;">
    </div>
    <h1><?php esc_html_e('Enter your activation code', 'beyond-seo'); ?></h1>
    <p><?php esc_html_e("To use the plugin, you'll need to activate it. Paste your code below to get started.", 'beyond-seo'); ?></p>
    <ul class="rc-feature-list">
        <li><?php esc_html_e('Optimize WordPress SEO', 'beyond-seo'); ?></li>
        <li><?php esc_html_e('Manage local listings', 'beyond-seo'); ?></li>
        <li><?php esc_html_e('Track keyword rankings', 'beyond-seo'); ?></li>
        <li><?php esc_html_e('Start your personal online marketing agent', 'beyond-seo'); ?></li>
    </ul>

    <form id="rc-activation-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('save_rankingcoach_activation'); ?>
        <input type="hidden" name="action" value="<?php echo esc_attr('save_rankingcoach_activation'); ?>">
        <input type="text" id="activation_code" name="activation_code"
               value="<?php echo esc_attr($activationCode); ?>"
               placeholder="<?php esc_attr_e('ex: 0822646889', 'beyond-seo'); ?>" />
        <div id="activation_error" class="rc-error" style="display:none;"></div>
        <button type="submit" id="activationButton"><?php esc_html_e('Activate', 'beyond-seo'); ?></button>
    </form>
    <p style="font-size:14px;color:#777;">
        <?php esc_html_e('Use the code we emailed you to unlock the plugin.', 'beyond-seo'); ?><br>
        <?php esc_html_e("Can’t find it? Search your inbox for 'activation code.'", 'beyond-seo'); ?>
    </p>
</div>
