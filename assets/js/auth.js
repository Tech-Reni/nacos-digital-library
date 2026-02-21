document.addEventListener("DOMContentLoaded", function () {

  // ================================
  // PASSWORD VISIBILITY TOGGLE
  // ================================
  document.querySelectorAll(".toggle-eye").forEach(icon => {
    icon.addEventListener("click", function () {
      const target = document.getElementById(this.dataset.target);
      if (!target) return;
      
      const isPassword = target.type === "password";
      target.type = isPassword ? "text" : "password";
      this.className = isPassword 
        ? "ri-eye-off-line toggle-eye" 
        : "ri-eye-line toggle-eye";
    });
  });

  // ================================
  // FORM VALIDATION RULES
  // ================================
  const validationRules = {
    email: {
      required: true,
      pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
      message: "Please enter a valid email address"
    },
    username: {
      required: true,
      minLength: 3,
      maxLength: 20,
      pattern: /^[a-zA-Z0-9_-]+$/,
      message: "Username must be 3-20 characters (letters, numbers, _, -)"
    },
    password: {
      required: true,
      minLength: 6,
      message: "Password must be at least 6 characters"
    },
    confirm_password: {
      required: true,
      match: "password",
      message: "Passwords do not match"
    },
    first_name: {
      required: true,
      minLength: 2,
      pattern: /^[a-zA-Z\s'-]+$/,
      message: "First name must contain only letters"
    },
    last_name: {
      required: true,
      minLength: 2,
      pattern: /^[a-zA-Z\s'-]+$/,
      message: "Last name must contain only letters"
    },
    phone: {
      required: false,
      pattern: /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{4,6}$/,
      message: "Please enter a valid phone number"
    }
  };

  // ================================
  // REAL-TIME VALIDATION
  // ================================
  const form = document.querySelector("form");
  if (form) {
    const inputs = form.querySelectorAll("input, select, textarea");
    
    inputs.forEach(input => {
      // Validate on blur
      input.addEventListener("blur", function () {
        validateField(this);
      });

      // Real-time validation on input
      input.addEventListener("input", function () {
        if (this.classList.contains("has-error")) {
          validateField(this);
        }
      });

      // Check password match on confirm password input
      if (input.name === "confirm_password") {
        input.addEventListener("input", function () {
          validateField(this);
        });
      }
    });

    // Form submission
    form.addEventListener("submit", function (e) {
      let isValid = true;
      const inputs = this.querySelectorAll("input, select, textarea");

      inputs.forEach(input => {
        if (!validateField(input)) {
          isValid = false;
        }
      });

      if (!isValid) {
        e.preventDefault();
        showAlert("Please fix the errors above", "error");
      }
    });
  }

  // ================================
  // VALIDATION FUNCTION
  // ================================
  function validateField(field) {
    const fieldName = field.name;
    const rules = validationRules[fieldName];
    const formGroup = field.closest(".form-group");

    if (!formGroup) return true;

    // Clear previous error
    const errorElement = formGroup.querySelector(".field-error");
    const successElement = formGroup.querySelector(".field-success");
    
    if (errorElement) errorElement.classList.remove("show");
    if (successElement) successElement.classList.remove("show");
    
    formGroup.classList.remove("has-error", "is-valid");

    // Skip validation if no rules and field is empty
    if (!rules && !field.value.trim()) {
      return true;
    }

    // If no rules, field is valid
    if (!rules) {
      formGroup.classList.add("is-valid");
      if (successElement) successElement.classList.add("show");
      return true;
    }

    // Check required
    if (rules.required && !field.value.trim()) {
      showFieldError(formGroup, "This field is required");
      return false;
    }

    // If not required and empty, skip other validations
    if (!rules.required && !field.value.trim()) {
      return true;
    }

    // Check minLength
    if (rules.minLength && field.value.length < rules.minLength) {
      showFieldError(formGroup, `Minimum ${rules.minLength} characters required`);
      return false;
    }

    // Check maxLength
    if (rules.maxLength && field.value.length > rules.maxLength) {
      showFieldError(formGroup, `Maximum ${rules.maxLength} characters allowed`);
      return false;
    }

    // Check pattern
    if (rules.pattern && !rules.pattern.test(field.value)) {
      showFieldError(formGroup, rules.message || "Invalid format");
      return false;
    }

    // Check password match
    if (rules.match) {
      const matchField = form.querySelector(`[name="${rules.match}"]`);
      if (matchField && field.value !== matchField.value) {
        showFieldError(formGroup, rules.message || "Fields do not match");
        return false;
      }
    }

    // Field is valid
    formGroup.classList.add("is-valid");
    if (successElement) {
      successElement.textContent = "✓ Valid";
      successElement.classList.add("show");
    }
    return true;
  }

  // ================================
  // ERROR/SUCCESS DISPLAY
  // ================================
  function showFieldError(formGroup, message) {
    formGroup.classList.add("has-error");
    formGroup.classList.remove("is-valid");
    
    let errorElement = formGroup.querySelector(".field-error");
    if (!errorElement) {
      errorElement = document.createElement("div");
      errorElement.className = "field-error";
      formGroup.appendChild(errorElement);
    }
    
    errorElement.textContent = "✗ " + message;
    errorElement.classList.add("show");
  }

  // ================================
  // ALERT SYSTEM
  // ================================
  function showAlert(message, type = "info") {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll(".alert");
    existingAlerts.forEach(alert => alert.remove());

    // Create new alert
    const alert = document.createElement("div");
    alert.className = `alert ${type}`;
    alert.textContent = message;

    const formWrapper = document.querySelector(".auth-box") || document.querySelector("form");
    if (formWrapper) {
      formWrapper.insertBefore(alert, formWrapper.firstChild);

      // Auto-remove success alerts after 5 seconds
      if (type === "success") {
        setTimeout(() => alert.remove(), 5000);
      }
    }
  }

  // ================================
  // INPUT FORMATTING
  // ================================

  // Phone number formatting
  const phoneInputs = document.querySelectorAll("input[name='phone']");
  phoneInputs.forEach(input => {
    input.addEventListener("input", function () {
      let value = this.value.replace(/\D/g, "");
      if (value.length > 0) {
        if (value.length <= 3) {
          value = value;
        } else if (value.length <= 6) {
          value = `(${value.slice(0, 3)}) ${value.slice(3)}`;
        } else {
          value = `(${value.slice(0, 3)}) ${value.slice(3, 6)}-${value.slice(6, 10)}`;
        }
      }
      this.value = value;
    });
  });

  // Email lowercase conversion
  const emailInputs = document.querySelectorAll("input[type='email']");
  emailInputs.forEach(input => {
    input.addEventListener("blur", function () {
      this.value = this.value.toLowerCase().trim();
    });
  });

  // ================================
  // PASSWORD STRENGTH INDICATOR
  // ================================
  const passwordInputs = document.querySelectorAll("input[name='password']");
  passwordInputs.forEach(input => {
    input.addEventListener("input", function () {
      showPasswordStrength(this);
    });
  });

  function showPasswordStrength(input) {
    const value = input.value;
    const formGroup = input.closest(".form-group");
    
    let strength = 0;
    let strengthText = "";
    let strengthColor = "";

    // Remove old strength indicator
    const oldIndicator = formGroup.querySelector(".password-strength");
    if (oldIndicator) oldIndicator.remove();

    if (value.length === 0) return;

    // Check strength criteria
    if (value.length >= 6) strength++;
    if (value.length >= 8) strength++;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) strength++;
    if (/\d/.test(value)) strength++;
    if (/[!@#$%^&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(value)) strength++;

    if (strength <= 1) {
      strengthText = "Weak";
      strengthColor = "#e63946";
    } else if (strength <= 2) {
      strengthText = "Fair";
      strengthColor = "#ff9800";
    } else if (strength <= 3) {
      strengthText = "Good";
      strengthColor = "#ffcc00";
    } else if (strength <= 4) {
      strengthText = "Strong";
      strengthColor = "#2a9d8f";
    } else {
      strengthText = "Very Strong";
      strengthColor = "#0b8f3a";
    }

    // Create strength indicator
    const indicator = document.createElement("div");
    indicator.className = "password-strength";
    indicator.style.cssText = `
      margin-top: 6px;
      font-size: 12px;
      font-weight: 600;
      color: ${strengthColor};
    `;
    indicator.textContent = `Strength: ${strengthText}`;
    
    // Create strength bar
    const bar = document.createElement("div");
    bar.style.cssText = `
      height: 3px;
      background: #e0e0e0;
      border-radius: 3px;
      margin-top: 4px;
      overflow: hidden;
    `;
    
    const fill = document.createElement("div");
    fill.style.cssText = `
      height: 100%;
      width: ${(strength / 5) * 100}%;
      background: ${strengthColor};
      border-radius: 3px;
      transition: width 0.3s ease;
    `;
    
    bar.appendChild(fill);
    indicator.appendChild(bar);
    formGroup.appendChild(indicator);
  }

  // ================================
  // FORM RESET
  // ================================
  const resetButtons = document.querySelectorAll("button[type='reset']");
  resetButtons.forEach(btn => {
    btn.addEventListener("click", function () {
      const form = this.closest("form");
      if (form) {
        setTimeout(() => {
          form.querySelectorAll(".form-group").forEach(group => {
            group.classList.remove("has-error", "is-valid");
            const error = group.querySelector(".field-error");
            const success = group.querySelector(".field-success");
            const strength = group.querySelector(".password-strength");
            if (error) error.classList.remove("show");
            if (success) success.classList.remove("show");
            if (strength) strength.remove();
          });
        }, 0);
      }
    });
  });

});