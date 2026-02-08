<?php
// Third-Party Cookie detection UI and controller script.
// Expects the following variables from the controller (IframePage):
// - string $cookieTitle
// - string $cookieDescription
// - array  $browserInstructions (associative: browser => string[] steps)
// - string $iframeUrl

if (!isset($cookieTitle, $cookieDescription, $browserInstructions, $iframeUrl)) {
    // Load centralized defaults when controller didn't provide values
    $cookieText = require __DIR__ . '/cookie-text.php';
    $cookieTitle = $cookieTitle ?? ($cookieText['cookieTitle'] ?? '');
    $cookieDescription = $cookieDescription ?? ($cookieText['cookieDescription'] ?? '');
    $browserInstructions = $browserInstructions ?? ($cookieText['browserInstructions'] ?? []);
    $iframeUrl = $iframeUrl ?? '';
}
?>

<!-- Third-Party Cookie Check iframe -->
<iframe id="rc-cookie-check-iframe" src="https://mindmup.github.io/3rdpartycookiecheck/start.html" style="display:none"></iframe>

<!-- Third-Party Cookie Warning -->
<div id="rc-cookie-warning" style="display: none; position: fixed; top: 28px; left: 142px; width: calc(100% - 142px); height: calc(100% - 28px); background: #fff; z-index: 99999; padding: 40px; box-sizing: border-box;">
    <div style="max-width: 600px; margin: 0 auto; text-align: center; padding-top: 100px;">
        <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="font-size: 48px; margin-bottom: 20px;">🍪</div>
            <h2 style="color: #495057; margin-bottom: 15px; font-size: 24px;"><?php echo esc_html($cookieTitle); ?></h2>
            <p style="color: #6c757d; margin-bottom: 25px; font-size: 16px; line-height: 1.5;">
                <?php echo esc_html($cookieDescription); ?>
            </p>

            <div style="background: #e9ecef; border-radius: 6px; padding: 20px; margin-bottom: 25px; text-align: left;">
                <h4 style="color: #495057; margin-bottom: 10px; font-size: 16px;"><?php echo esc_html(__('How to enable:', 'beyond-seo')); ?></h4>
                <div id="browser-instructions">
                    <?php
                    // Render instruction lists with IDs: {browser}-instructions
                    foreach ($browserInstructions as $browser => $instructions): ?>
                        <ol id="<?php echo esc_attr($browser); ?>-instructions"
                            style="color:#6c757d;margin:0;padding-left:20px;line-height:1.6;display:none;">
                            <?php foreach ((array)$instructions as $instruction): ?>
                                <li><?php echo esc_html($instruction); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center;">
                <button onclick="location.reload()" style="background: #007cba; color: white; border: none; padding: 12px 24px; border-radius: 4px; font-size: 16px; cursor: pointer; transition: background-color 0.2s;">
                    <?php echo esc_html(__('Refresh Page', 'beyond-seo')); ?>
                </button>
                <a href="<?php echo esc_url($iframeUrl); ?>" target="_blank" style="background: #28a745; color: white; border: none; padding: 12px 24px; border-radius: 4px; font-size: 16px; cursor: pointer; transition: background-color 0.2s; text-decoration: none; display: inline-block;">
                    <?php echo esc_html(__('Open Dashboard in New Tab', 'beyond-seo')); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var cookieCheckCompleted = false;
    var fallbackTimeout = null;
    var FALLBACK_DELAY = 5000; // 5 seconds timeout for cookie check

    function detectBrowser() {
        var userAgent = navigator.userAgent.toLowerCase();
        if (userAgent.indexOf('firefox') !== -1) return 'firefox';
        if (userAgent.indexOf('edg') !== -1) return 'edge';
        if (userAgent.indexOf('chrome') !== -1 || userAgent.indexOf('chromium') !== -1) return 'chrome';
        if (userAgent.indexOf('safari') !== -1) return 'safari';
        return 'default';
    }

    function showBrowserInstructions(browser) {
        var ids = ['safari-instructions','chrome-instructions','firefox-instructions','edge-instructions','default-instructions'];
        ids.forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
        var target = document.getElementById(browser + '-instructions');
        if (target) target.style.display = 'block';
    }

    function initializeIframe() {
        if (cookieCheckCompleted) return;
        cookieCheckCompleted = true;
        if (fallbackTimeout) {
            clearTimeout(fallbackTimeout);
            fallbackTimeout = null;
        }
        var warn = document.getElementById('rc-cookie-warning');
        if (warn) warn.style.display = 'none';
        // rest handled by handleIframeLoaded
    }

    function fallbackToNormalIframe() {
        if (cookieCheckCompleted) return;
        initializeIframe();
    }

    var receiveMessage = function (evt) {
        if (evt.origin !== 'https://mindmup.github.io') return;

        if (evt.data === 'MM:3PCunsupported') {
            if (cookieCheckCompleted) return;
            cookieCheckCompleted = true;
            if (fallbackTimeout) {
                clearTimeout(fallbackTimeout);
                fallbackTimeout = null;
            }
            var browser = detectBrowser();
            showBrowserInstructions(browser);
            var warn = document.getElementById('rc-cookie-warning');
            if (warn) warn.style.display = 'block';
            var loader = document.getElementById('rc-seo-iframe-loader');
            if (loader) loader.style.display = 'none';
            var iframe = document.getElementById('rc-seo-iframe');
            if (iframe) iframe.style.display = 'none';
        } else if (evt.data === 'MM:3PCsupported') {
            if (cookieCheckCompleted) return;
            initializeIframe();
        }
    };
    window.addEventListener('message', receiveMessage, false);

    // Provide a generic loader completion hook for the iframe
    window.handleIframeLoaded = function() {
        if (document.getElementById('rc-cookie-warning').style.display === 'none') {
            var loader = document.getElementById('rc-seo-iframe-loader');
            if (loader) loader.style.display = 'none';
            var iframe = document.getElementById('rc-seo-iframe');
            if (iframe) iframe.style.visibility = 'visible';
        }
    };

    function handleCookieCheckError() {
        fallbackToNormalIframe();
    }

    function initializeCookieCheck() {
        var cookieCheckIframe = document.getElementById('rc-cookie-check-iframe');
        if (cookieCheckIframe) {
            cookieCheckIframe.onerror = handleCookieCheckError;
            cookieCheckIframe.onabort = handleCookieCheckError;

            fallbackTimeout = setTimeout(function() {
                fallbackToNormalIframe();
            }, FALLBACK_DELAY);

            cookieCheckIframe.onload = function() {
                setTimeout(function() {
                    if (!cookieCheckCompleted) {
                        fallbackToNormalIframe();
                    }
                }, 2000);
            };
        } else {
            fallbackToNormalIframe();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCookieCheck);
    } else {
        initializeCookieCheck();
    }
})();
</script>
