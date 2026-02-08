document.addEventListener("DOMContentLoaded", () => {
  // Ensure body class is applied from JS (not inline PHP)
  document.body.classList.add("rankingcoach-activation-page");

  const form = document.getElementById("rc-activation-form");
  const activationCodeInput = document.getElementById("activation_code");
  const errorDiv = document.getElementById("activation_error");

  if (form) {
    form.addEventListener("submit", (event) => {
      if (activationCodeInput.value.trim() === "") {
        event.preventDefault();
        if (errorDiv) {
          errorDiv.textContent = rcActivation.errorEmptyCode;
          errorDiv.style.display = "block";
        }
      } else {
        if (errorDiv) {
          errorDiv.style.display = "none";
        }
      }
    });
  }

  if (activationCodeInput) {
    activationCodeInput.addEventListener("input", () => {
      if (errorDiv && errorDiv.style.display === "block") {
        errorDiv.style.display = "none";
      }
    });
  }
});
