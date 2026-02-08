// noinspection JSUnresolvedReference,JSUnusedLocalSymbols

(function ($) {
    "use strict";

    window.rankingcoach_data_site_url = RankingCoachGeneralData.site_url;
    window.rankingcoach_data_api_base_url = RankingCoachGeneralData.api_base_url;
    window.rankingcoach_data_ajax_url = RankingCoachGeneralData.ajax_url;
    window.rankingcoach_data_nonce_ts = RankingCoachGeneralData.nonce_ts;
    window.rankingcoach_data_nonce = RankingCoachGeneralData.nonce;

    // Initialize toggle text for meta box
    function initToggleText() {
        var $postbox = $("#rankingcoach-seo-analysis");
        if ($postbox.length) {
            var $handle = $postbox.find(".hndle");

            // Only add if not already added
            if ($handle.find("#rankingcoach-toggle-text").length === 0) {
                var $toggleText = $('<div id="rankingcoach-toggle-text" style="cursor: pointer; display: inline-block; margin-left: 8px;"></div>');
                $handle.append($toggleText);
                updateToggleText();
            }
        }
    }

    function updateToggleText() {
        var $postbox = $("#rankingcoach-seo-analysis");
        var $toggleText = $("#rankingcoach-toggle-text");

        if ($postbox.length && $toggleText.length) {
            if ($postbox.hasClass("closed")) {
                $toggleText.text(RankingCoachGeneralData.i18n.showMore);
            } else {
                $toggleText.text(RankingCoachGeneralData.i18n.showLess);
            }
        }
    }

    /**
     * Generate a cryptographically secure session ID
     * @returns {string} UUID v4 session identifier
     */
    window.generateSessionId = function () {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) {
            return crypto.randomUUID();
        }
        // Fallback for older browsers
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Get current origin for parent identification
     * @returns {string} Current window origin
     */
    window.getCurrentOrigin = function() {
        return window.location.origin;
    }

    // Potentially used for the upsell redirect
    window.addEventListener("message", function (event) {

        const data = event.data;
        switch (data.type) {
            case 'registration.ready':
                //
                break;

            case 'registration.complete':
                window.location.href = '/wp-admin/admin.php?page=rankingcoach-main&ref=account_sync';
                break;

            case 'registration.error':
                //
                break;

            case 'registration.cancelled':
                //
                break;

            default:
                //
                //console.warn('Unknown message type:', message.type);
        }

        if (data.action === "redirect_upsell" && data.relativeUrl) {
            window.location.href = window.location.origin + data.relativeUrl;
        }
    });

    /**
     * Called when the iframe has loaded
     */
    window.handleIframeLoaded = function () {
        // Hide loader and show iframe when it's loaded
        const loader = document.getElementById("rc-seo-iframe-loader");
        if (loader) {
            loader.style.display = "none";
        }

        const iframe = document.getElementById("rc-seo-iframe");
        if (iframe) {
            iframe.style.visibility = "visible";
        }

        // Apply the proper iframe sizing
        window.loadIframeWrap();
    };

    /**
     * Load the iframe wrap within the window context.
     */
    window.loadIframeWrap = function () {
        let iframeWrapId = "rc-seo-iframe-wrap";
        let iframeWrapDom = document.querySelector("#" + iframeWrapId);
        if (iframeWrapDom) {
            let wpContentDom = document.querySelector("#wpwrap #wpcontent");
            let wpBodyContentDom = document.querySelector("#wpbody-content");
            wpBodyContentDom.style.padding = "0";
            wpContentDom.style.paddingLeft = "0";
            iframeWrapDom.style.height = window.innerHeight - 32 + "px";
        }
    };
    window.addEventListener("resize", function () {
        let iframeWrapDom = document.querySelector("#rc-seo-iframe-wrap");
        if (iframeWrapDom) {
            iframeWrapDom.style.height = window.innerHeight - 32 + "px";
        }
    });

    /**
     * Load the iframe within the window context.
     */
    window.onload = function () {
        let wpFooterDom = document.querySelector("#wpfooter");
        if (wpFooterDom) wpFooterDom.style.display = "none";

        // Set initial sizing and make sure the iframe wrapper is visible
        let iframeWrapDom = document.querySelector("#rc-seo-iframe-wrap");
        if (iframeWrapDom) {
            let wpContentDom = document.querySelector("#wpwrap #wpcontent");
            let wpBodyContentDom = document.querySelector("#wpbody-content");
            wpBodyContentDom.style.padding = "0";
            wpContentDom.style.paddingLeft = "0";
            iframeWrapDom.style.height = window.innerHeight - 32 + "px";
        }

        // Handle the activation button if it exists in the activation page
        const button = document.getElementById("activationButton");
        const code = document.getElementById("activation_code");
        if (button) {
            const originalText = button.innerHTML;

            function handleClick(event) {
                if (!code || !code.value) {
                    event.preventDefault();
                    const errorDiv = document.getElementById('activation_error');
                    if (errorDiv) {
                        errorDiv.textContent = rcActivation.errorEmptyCode;
                        errorDiv.style.display = 'block';
                    }
                    return;
                } else {
                    const errorDiv = document.getElementById('activation_error');
                    if (errorDiv) {
                        errorDiv.style.display = 'none';
                    }
                }

                const rect = button.getBoundingClientRect();
                button.style.width = `${rect.width}px`;
                button.style.height = `${rect.height}px`;

                button.disabled = true;
                button.style.backgroundColor = "#d0d8e0";
                button.style.color = "#4a5568";

                // Only show the spinner, centered
                button.innerHTML = `
                    <span style="display: inline-flex; align-items: center; gap: 8px;">
                        <img src="/wp-admin/images/spinner-2x.gif"
                             alt="Loading..."
                             style="height: 16px;" />
                        <span>Activating</span>
                    </span>
                `;
                setTimeout(() => {
                    const form = button.closest("form");
                    if (form) form.submit();
                }, 1000);
            }

            button.addEventListener("click", handleClick);

            // Clear error when user starts typing
            if (code) {
                code.addEventListener('input', function () {
                    const errorDiv = document.getElementById('activation_error');
                    if (errorDiv) {
                        errorDiv.style.display = 'none';
                    }
                });
            }

            window.addEventListener("beforeunload", function () {
                button.removeEventListener("click", handleClick);
            });

            // Reset button state on pageshow if from bfcache
            window.addEventListener('pageshow', function (event) {
                if (event.persisted && button) {
                    button.disabled = false;
                    button.innerHTML = originalText;
                    button.style.backgroundColor = '';
                    button.style.color = '';
                    button.style.width = '';
                    button.style.height = '';
                    button.addEventListener("click", handleClick);
                }
            });
        }

        // Initialize toggle text for meta box
        initToggleText();
    };

    // Re-initialize after postbox toggles
    $(document).ready(function () {
        // Initialize on page load
        setTimeout(initToggleText, 100);

        // Listen for postbox toggle events
        $(document).on("click", "#rankingcoach-seo-analysis .hndle, #rankingcoach-seo-analysis .handlediv", function () {
            setTimeout(updateToggleText, 100);
        });

        // Listen for postbox state changes
        $(document).on("postbox-toggled", function () {
            setTimeout(updateToggleText, 100);
        });
    });
})(jQuery);
