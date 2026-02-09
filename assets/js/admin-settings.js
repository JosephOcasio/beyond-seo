/**
 * BeyondSEO Admin Settings
 *
 * Handles tabbed interface and API interactions for settings page.
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Tab switching
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();

            const tab = $(this).data('tab');
            const $content = $('#' + tab);

            if ($content.length === 0) {
                return;
            }

            // Hide all tabs
            $('.tab-content').hide();

            // Remove active class from all tabs
            $('.nav-tab').removeClass('nav-tab-active');

            // Show selected tab and mark as active
            $content.show();
            $(this).addClass('nav-tab-active');

            // Store active tab in sessionStorage
            sessionStorage.setItem('beyondSeoActiveTab', tab);
        });

        // Restore last active tab
        const activeTab = sessionStorage.getItem('beyondSeoActiveTab');
        if (activeTab) {
            $('[data-tab="' + activeTab + '"]').trigger('click');
        }

        // Handle form submission
        $('.settings-form').on('submit', function(e) {
            // WordPress handles standard form submission
            // This could be enhanced with AJAX for better UX
        });

        // Add confirmation for destructive actions
        $(document).on('click', '.delete-setting', function(e) {
            if (!confirm('Are you sure you want to delete this setting?')) {
                e.preventDefault();
            }
        });

        // Enable/Disable dependent fields
        handleDependentFields();
        $(document).on('change', 'input[type="checkbox"]', handleDependentFields);
    });

    /**
     * Handle dependent field visibility
     */
    function handleDependentFields() {
        // If caching is disabled, disable cache TTL
        const cacheEnabled = $('input[name="beyond_seo_cache_enabled"]').is(':checked');
        $('input[name="beyond_seo_cache_ttl"]').prop('disabled', !cacheEnabled);

        // If API is disabled, disable rate limit
        const apiEnabled = $('input[name="beyond_seo_api_enabled"]').is(':checked');
        $('input[name="beyond_seo_api_rate_limit"]').prop('disabled', !apiEnabled);
    }

})(jQuery);
