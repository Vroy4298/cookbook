// Main event listener that runs when the DOM is fully loaded
document.addEventListener("DOMContentLoaded", () => {
  // Get references to tab elements and forms
  const loginTab = document.getElementById("login-tab");
  const signupTab = document.getElementById("signup-tab");
  const loginForm = document.getElementById("login-form");
  const signupForm = document.getElementById("signup-form");

  // Handle login tab click
  loginTab?.addEventListener("click", () => {
    loginTab.classList.add("tab-active");
    loginTab.classList.remove("tab-inactive");
    signupTab.classList.add("tab-inactive");
    signupTab.classList.remove("tab-active");
    loginForm?.classList.remove("hidden");
    signupForm?.classList.add("hidden");
  });

  // Handle signup tab click
  signupTab?.addEventListener("click", () => {
    signupTab.classList.add("tab-active");
    signupTab.classList.remove("tab-inactive");
    loginTab.classList.add("tab-inactive");
    loginTab.classList.remove("tab-active");
    signupForm?.classList.remove("hidden");
    loginForm?.classList.add("hidden");
  });

  // Password visibility toggle functionality
  const togglePasswordButtons = document.querySelectorAll(".toggle-password");
  togglePasswordButtons.forEach((button) => {
    button.addEventListener("click", function () {
      const passwordInput = this.parentElement.querySelector("input");
      const icon = this.querySelector("i");
      if (!passwordInput || !icon) return;

      if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.replace("fa-eye", "fa-eye-slash");
      } else {
        passwordInput.type = "password";
        icon.classList.replace("fa-eye-slash", "fa-eye");
      }
    });
  });

  // Signup form validation
  const signupFormElement = document.getElementById("signup-form-element");
  const signupPassword = document.getElementById("signup-password");
  const signupConfirmPassword = document.getElementById("signup-confirm-password");
  const passwordError = document.getElementById("signup-password-error");

  if (signupFormElement) {
    signupFormElement.addEventListener("submit", function (event) {
      event.preventDefault();

      const termsCheckbox = document.getElementById("terms");
      const termsError = document.getElementById("terms-error");
      const nameInput = document.getElementById("signup-name");
      const emailInput = document.getElementById("signup-email");
      const submitButton = this.querySelector('button[type="submit"]');

      const originalButtonHTML = "Create Account";
      let hasError = false;
      let errorMessages = [];

      // Reset form state
      submitButton.innerHTML = originalButtonHTML;
      submitButton.disabled = false;
      submitButton.classList.remove("loading");

      nameInput?.classList.remove("error-highlight");
      emailInput?.classList.remove("error-highlight");
      signupPassword?.classList.remove("error-highlight");
      signupConfirmPassword?.classList.remove("error-highlight");
      termsError?.classList.add("hidden");
      passwordError?.classList.add("hidden");

      if (!nameInput?.value.trim()) {
        errorMessages.push("Please enter your name");
        nameInput?.classList.add("error-highlight");
        hasError = true;
      }

      if (!emailInput?.value.trim()) {
        errorMessages.push("Please enter your email");
        emailInput?.classList.add("error-highlight");
        hasError = true;
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value)) {
        errorMessages.push("Please enter a valid email address");
        emailInput?.classList.add("error-highlight");
        hasError = true;
      }

      if (!termsCheckbox?.checked) {
        errorMessages.push("You must agree to the Terms of Service and Privacy Policy");
        termsError.textContent = "You must agree to the Terms of Service and Privacy Policy";
        termsError.classList.remove("hidden");
        hasError = true;
      }

      if (signupPassword && signupConfirmPassword) {
        const pw = signupPassword.value;
        const cpw = signupConfirmPassword.value;

        if (pw.length < 8) {
          errorMessages.push("Password must be at least 8 characters long");
          signupPassword.classList.add("error-highlight");
          hasError = true;
        } else if (!/[A-Z]/.test(pw)) {
          errorMessages.push("Password must contain at least one uppercase letter");
          signupPassword.classList.add("error-highlight");
          hasError = true;
        } else if (!/[0-9]/.test(pw)) {
          errorMessages.push("Password must contain at least one number");
          signupPassword.classList.add("error-highlight");
          hasError = true;
        } else if (!/[!@#$%^&*(),.?":{}|<>]/.test(pw)) {
          errorMessages.push("Password must contain at least one special character");
          signupPassword.classList.add("error-highlight");
          hasError = true;
        } else if (pw !== cpw) {
          errorMessages.push("Passwords do not match");
          passwordError.textContent = "Passwords do not match";
          passwordError.classList.remove("hidden");
          signupConfirmPassword.classList.add("error-highlight", "shake-error");
          hasError = true;
        }
      }

      if (hasError) {
        showAlert(errorMessages.join("<br>"), "error");
        return;
      }

      submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
      submitButton.disabled = true;
      submitButton.classList.add("loading");

      setTimeout(() => {
        this.submit();
      }, 100);
    });
  }

  // Login form loading state
  const loginFormElement = document.getElementById("login-form-element");
  loginFormElement?.addEventListener("submit", function () {
    const submitButton = this.querySelector('button[type="submit"]');
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Logging in...';
    submitButton.disabled = true;
  });

  // Button ripple effect
  const allButtons = document.querySelectorAll("button");
  allButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      const ripple = document.createElement("span");
      this.appendChild(ripple);

      const x = e.clientX - this.getBoundingClientRect().left;
      const y = e.clientY - this.getBoundingClientRect().top;

      ripple.className = "ripple";
      ripple.style.left = `${x}px`;
      ripple.style.top = `${y}px`;

      setTimeout(() => ripple.remove(), 600);

      this.classList.add("button-clicked");
      setTimeout(() => this.classList.remove("button-clicked"), 200);
    });
  });

  // Add loading effect on submit buttons
  const submitButtons = document.querySelectorAll('button[type="submit"]');
  submitButtons.forEach((button) => {
    button.addEventListener("click", function () {
      if (!this.classList.contains("loading")) {
        const form = this.closest("form");
        if (form && form.checkValidity()) {
          this.classList.add("loading");
          this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
        }
      }
    });
  });

  // Login error alert animation
  const loginErrorAlert = document.getElementById("login-error-alert");
  if (loginErrorAlert) {
    setTimeout(() => {
      const passwordInput = document.getElementById("login-password");
      passwordInput?.classList.add("shake-error", "error-highlight");

      passwordInput?.addEventListener("animationend", () => {
        passwordInput.classList.remove("shake-error");
      });
    }, 100);

    setTimeout(() => {
      loginErrorAlert.style.opacity = "0";
      loginErrorAlert.style.transition = "opacity 0.5s ease";
      setTimeout(() => (loginErrorAlert.style.display = "none"), 500);
    }, 5000);
  }

  // Alert function
  window.showAlert = (message, type = "success") => {
    const alertDiv = document.createElement("div");
    alertDiv.className =
      type === "success"
        ? "fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50"
        : "fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50";

    alertDiv.innerHTML = `
      <strong class="font-bold">${type === "success" ? "Success!" : "Error!"}</strong>
      <span class="block sm:inline"> ${message}</span>
      <button class="absolute top-0 right-0 px-4 py-3"><i class="fas fa-times"></i></button>
    `;

    document.body.appendChild(alertDiv);

    const closeButton = alertDiv.querySelector("button");
    closeButton.addEventListener("click", () => alertDiv.remove());

    setTimeout(() => {
      alertDiv.style.opacity = "0";
      alertDiv.style.transition = "opacity 0.5s ease";
      setTimeout(() => alertDiv.remove(), 500);
    }, 5000);
  };

  // Show alerts from URL
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has("success")) {
    showAlert("Your account has been created successfully! Please log in.", "success");
  }
  if (urlParams.has("error")) {
    showAlert(urlParams.get("error") || "An error occurred. Please try again.", "error");
  }
  if (urlParams.has("wrong_password")) {
    const passwordInput = document.getElementById("login-password");
    passwordInput?.classList.add("error-highlight", "shake-error");

    showAlert("Incorrect password. Please try again.", "error");

    passwordInput?.addEventListener("animationend", () => {
      passwordInput.classList.remove("shake-error");
    });
  }
});
