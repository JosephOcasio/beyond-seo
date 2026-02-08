/**
 * RankingCoach Upsell Page JS
 */
(function ($) {
  "use strict";

  $(document).ready(function () {
    // Function to disable all upsell buttons
    function disableAllUpsellButtons() {
      $(".rankingcoach-plan .upgrade-button").each(function () {
        const $button = $(this);
        $button.addClass("disabled").prop("disabled", true);
        $button.text("Processing...");
      });
    }

    // Show more functionality for plan features
    function initShowMoreFunctionality() {
      let isExpanded = false;

      $(".show-more-btn").on("click", function (e) {
        e.preventDefault();
        
        const $button = $(this);
        const $icon = $button.find(".show-more-icon");
        const $text = $button.find(".show-more-text");
        
        if (!isExpanded) {
          // Expand all cards with hidden features
          $(".feature-hidden").addClass("show");
          $(".show-more-btn").addClass("expanded");
          $(".show-more-text").text("Show less");
          isExpanded = true;
        } else {
          // Collapse all cards
          $(".feature-hidden").removeClass("show");
          $(".show-more-btn").removeClass("expanded");
          $(".show-more-text").text("Show more");
          isExpanded = false;
        }
      });
    }

    // Initialize show more functionality
    initShowMoreFunctionality();

    $(".rankingcoach-plan .upgrade-button").each(function () {
      const $button = $(this);
      const $plan = $button.closest(".rankingcoach-plan");
      const $checkbox = $plan.find('input[type="checkbox"]');
      const $termsDiv = $plan.find(".plan-terms");

      // Function to remove error message
      function removeErrorMessage() {
        $termsDiv.find(".terms-error-message").remove();
      }

      if ($checkbox.length > 0) {
        $button.on("click", function (e) {
          removeErrorMessage();
          if (!$checkbox.is(":checked")) {
            e.preventDefault();
            // Create and display the error message
            const $errorMessage = $("<div>")
              .addClass("terms-error-message")
              .text(
                "Please accept the Terms & Conditions and Privacy Policy to proceed."
              );
            $termsDiv.append($errorMessage);
            $checkbox.focus();
          } else {
            // Terms are accepted, disable all buttons and proceed
            disableAllUpsellButtons();
            // Add a small delay to show the "Processing..." state before redirect
            setTimeout(function () {
              window.location.href = $button.attr("href");
            }, 500);
            e.preventDefault();
          }
        });

        // Remove error message when checkbox is checked
        $checkbox.on("change", function () {
          if ($(this).is(":checked")) {
            removeErrorMessage();
          }
        });
      } else {
        // No checkbox required, just disable on click
        $button.on("click", function (e) {
          disableAllUpsellButtons();
          // Add a small delay to show the "Processing..." state before redirect
          setTimeout(function () {
            window.location.href = $button.attr("href");
          }, 500);
          e.preventDefault();
        });
      }
    });
  });
})(jQuery);
