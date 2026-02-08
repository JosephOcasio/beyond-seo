(function ($) {
  "use strict";

  var settings = window.RankingCoachDeactivateFeedback || null;
  if (!settings || !settings.pluginFile || !Array.isArray(settings.reasons)) {
    return;
  }

  var pluginFile = settings.pluginFile;
  var pluginSlug = settings.pluginSlug || "";
  var ajaxUrl = settings.ajaxUrl;
  var nonce = settings.nonce;
  var strings = settings.strings || {};
  var pluginName = settings.pluginName || "rankingCoach";

  function __(key, fallback) {
    var value = strings[key];
    return typeof value === "string" && value.length ? value : fallback;
  }

  function closeModal() {
    $(document).off("keyup.rcDeactivateSurvey");
    $("#rankingcoach-deactivate-modal").hide().remove();
  }

  function buildOption(option, index) {
    var id = "rankingcoach-deactivate-option-" + index;
    var detailInput = option.details
      ? '<input class="rankingcoach-deactivate-modal__option-details" type="text" placeholder="' +
        option.details +
        '" />'
      : "";
    return (
      '<div class="rankingcoach-deactivate-modal__option">' +
      '<label for="' +
      id +
      '">' +
      '<input id="' +
      id +
      '" type="radio" name="rankingcoach-deactivate-reason" value="' +
      option.code +
      '" />' +
      '<span class="rankingcoach-deactivate-modal__option-reason">' +
      option.label +
      "</span>" +
      "</label>" +
      detailInput +
      "</div>"
    );
  }

  function renderModal(href) {
    closeModal();

    var modal =
      '<div id="rankingcoach-deactivate-modal" role="dialog" aria-modal="true">' +
      '<div class="rankingcoach-deactivate-modal__wrap">' +
      '<form class="rankingcoach-deactivate-modal__form">' +
      '<span class="rankingcoach-deactivate-modal__title"><span class="dashicons dashicons-testimonial"></span> ' +
      __("modalTitle", "Quick Feedback") +
      "</span>" +
      '<span class="rankingcoach-deactivate-modal__subtitle">' +
      __("modalSubtitle", "If you have a moment, please share why you are deactivating %s:").replace("%s", pluginName) +
      "</span>" +
      '<div class="rankingcoach-deactivate-modal__options">' +
      settings.reasons.map(buildOption).join("") +
      "</div>" +
      '<div class="rankingcoach-deactivate-modal__error"></div>' +
      '<div class="rankingcoach-deactivate-modal__delete-data">' +
      "<label>" +
      '<input type="checkbox" name="delete_data" />' +
      "<span>" +
      __("deleteData", "Delete all project data and settings upon deactivation") +
      "</span>" +
      "</label>" +
      "</div>" +
      '<div class="rankingcoach-deactivate-modal__footer">' +
      "</div>" +
      "</form>" +
      "</div>" +
      "</div>";

    var $modal = $(modal);
    var $form = $modal.find("form");
    var $footer = $modal.find(".rankingcoach-deactivate-modal__footer");
    var $buttonsWrapper = $('<div class="buttons-wrapper"></div>');
    var $submit = $('<button type="submit" class="button button-primary button-large"></button>').text(
      __("submit", "Submit and Deactivate"),
    );
    var $skip = $('<a data-url="' + href + '" href="#" class="rankingcoach-deactivate-modal__skip"></a>').text(
      __("skip", "Skip and Deactivate"),
    );

    $buttonsWrapper.append($submit, $skip);
    $footer.append($buttonsWrapper);

    $modal.on("click", ".rankingcoach-deactivate-modal__wrap", function (event) {
      if ($(event.target).closest(".rankingcoach-deactivate-modal__form").length === 0) {
        closeModal();
      }
    });

    $form.on("change", 'input[type="radio"]', function () {
      var $selectedOption = $(this).closest(".rankingcoach-deactivate-modal__option");
      $form.find(".rankingcoach-deactivate-modal__option").removeClass("is-selected");
      $selectedOption.addClass("is-selected");
      $form.find(".rankingcoach-deactivate-modal__option-details").hide();

      // Show details input for "other" and "errors" options
      var selectedValue = $(this).val();
      if (selectedValue === "other" || selectedValue === "errors") {
        var $details = $selectedOption.find(".rankingcoach-deactivate-modal__option-details");
        if ($details.length) {
          $details.show().trigger("focus");
        }
      }

      $form.find(".rankingcoach-deactivate-modal__error").empty();
    });

    $form.on("submit", function (event) {
      event.preventDefault();
      var $checked = $form.find('input[type="radio"]:checked');
      if (!$checked.length) {
        $form.find(".rankingcoach-deactivate-modal__error").html(__("errorNoOption", "Please select a reason"));
        return;
      }

      var $selected = $checked.closest(".rankingcoach-deactivate-modal__option");
      var detailsField = $selected.find(".rankingcoach-deactivate-modal__option-details");
      var payload = {
        action: "rankingcoach_submit_deactivate_feedback",
        nonce: nonce,
        plugin: pluginFile,
        reasonCode: $checked.val(),
        feedbackText: detailsField.length ? detailsField.val() : "",
        deleteProject: $form.find('input[name="delete_data"]').is(":checked"),
      };

      $submit.prop("disabled", true).text(__("sending", "Sending…"));
      $skip.addClass("disabled");

      $.post(ajaxUrl, payload).always(function () {
        window.location.href = href;
      });
    });

    $skip.on("click", function (event) {
      event.preventDefault();
      var payload = {
        action: "rankingcoach_submit_deactivate_feedback",
        nonce: nonce,
        plugin: pluginFile,
        reasonCode: "skip",
        feedbackText: "",
        deleteProject: false,
      };
      $.post(ajaxUrl, payload).always(function () {
        window.location.href = href;
      });
    });

    $(document).on("keyup.rcDeactivateSurvey", function (event) {
      if (event.key === "Escape") {
        closeModal();
      }
    });

    $("body").append($modal);
    requestAnimationFrame(function () {
      $modal.addClass("is-visible");
    });
    setTimeout(function () {
      $form.find('input[type="radio"]').first().trigger("focus");
    }, 50);
  }

  $(document).on(
    "click",
    '#the-list [data-slug="' +
      pluginSlug +
      '"] .deactivate a, a[href*="action=deactivate"][href*="plugin=' +
      pluginFile +
      '"]',
    function (event) {
      if (event.which === 2 || event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) {
        return;
      }

      event.preventDefault();
      var href = $(this).attr("href") || "";
      if (!href.length) {
        return;
      }

      renderModal(href);
    },
  );
})(jQuery);
