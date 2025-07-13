define(["core/ajax"], function (ajax) {
  const { call: fetchMany } = ajax;

  // Repository functions following ajaxdoc guidelines
  const applyCoupon = (couponid, instance_id) =>
    fetchMany([
      {
        methodname: "moodle_stripepayment_applycoupon",
        args: { couponid, instance_id },
      },
    ])[0];

  const createPaymentSession = (user_id, couponid, instance_id) =>
    fetchMany([
      {
        methodname: "moodle_stripepayment_paid_enrol",
        args: { user_id, couponid, instance_id },
      },
    ])[0];

  // Optimized DOM utility with caching
  const createDOM = (instanceId) => {
    const cache = new Map();

    return {
      get(id) {
        const fullId = `${id}-${instanceId}`;
        if (!cache.has(fullId)) {
          cache.set(fullId, document.getElementById(fullId));
        }
        return cache.get(fullId);
      },

      setHTML(id, html) {
        const element = this.get(id);
        if (element) element.innerHTML = html;
      },

      toggle(id, show) {
        const element = this.get(id);
        if (element) element.style.display = show ? "block" : "none";
      },

      focus(id) {
        const element = this.get(id);
        if (element) element.focus();
      },

      setButtonState(id, disabled, text, opacity = disabled ? "0.7" : "1") {
        const button = this.get(id);
        if (button) {
          button.disabled = disabled;
          button.textContent = text;
          button.style.opacity = opacity;
          button.style.cursor = disabled ? "not-allowed" : "pointer";
        }
      },
    };
  };
  return {
    stripe_payment: function (
      user_id,
      publishablekey,
      couponid,
      instance_id,
      please_wait_string,
      buy_now_string,
      invalid_code_string
    ) {
      // Create instance-specific DOM utility
      const DOM = createDOM(instance_id);

      // Initialize Stripe (global object loaded from external script)
      if (typeof window.Stripe === "undefined") {
        console.error(
          "Stripe.js not loaded. Make sure the Stripe script is included."
        );
        return;
      }
      const stripe = window.Stripe(publishablekey);

      // Simplified coupon application - PHP backend handles all logic
      const applyCouponHandler = async (event) => {
        event.preventDefault();

        const couponInput = DOM.get("coupon");
        const couponCode = couponInput?.value.trim();

        if (!couponCode) {
          DOM.setHTML(
            "show_message",
            '<p style="color:red;"><b>Please enter a coupon code</b></p>'
          );
          DOM.focus("coupon");
          return;
        }

        DOM.setButtonState("apply", true, "Applying...");
        DOM.setHTML(
          "show_message",
          '<p style="color:blue;">Applying coupon...</p>'
        );

        try {
          // PHP backend handles all validation, calculation, and auto-enrollment
          const data = await applyCoupon(couponCode, instance_id);

          if (data?.status !== undefined) {
            couponid = couponCode; // Update for payment processing
            DOM.setHTML(
              "show_message",
              '<p style="color:green;"><b>Coupon applied successfully!</b></p>'
            );

            // Hide coupon input after successful application
            const couponInputGroup = DOM.get("coupon")?.closest(
              ".coupon-input-group"
            );
            if (couponInputGroup) couponInputGroup.style.display = "none";

            // Handle auto-enrollment (PHP backend completed enrollment)
            if (data.auto_enrolled) {
              DOM.setHTML(
                "show_message",
                '<p style="color:green;"><b>Coupon applied and enrolled successfully! Redirecting...</b></p>'
              );
              setTimeout(() => location.reload(), 1500);
              return;
            }

            // Update UI based on PHP calculations
            updateUIFromServerResponse(data);
            if (data.show_sections?.discount_section) {
              showValidCouponSection(data);
            }
          } else {
            throw new Error("Invalid server response");
          }
        } catch (error) {
          console.error("Coupon application failed:", error);
          DOM.setHTML(
            "show_message",
            `<p style="color:red;"><b>${invalid_code_string}</b></p>`
          );
          DOM.focus("coupon");
        } finally {
          DOM.setButtonState("apply", false, "Apply code");
        }
      };

      const EnrollHandler = async () => {
        const enrollButton = DOM.get("enrolButton");
        if (!enrollButton) return;

        DOM.setButtonState("enrolButton", true, please_wait_string);

        try {
          const paymentData = await createPaymentSession(
            user_id,
            couponid,
            instance_id
          );

          if (paymentData.error?.message) {
            showError("paymentResponse", paymentData.error.message);
            DOM.setButtonState("enrolButton", false, "Enrol Now");
          } else if (paymentData.status) {
            // paymentData.status contains the session ID for Stripe checkout
            const result = await stripe.redirectToCheckout({
              sessionId: paymentData.status,
            });
            if (result.error) {
              showError("paymentResponse", result.error.message);
              DOM.setButtonState("enrolButton", false, "Enrol Now");
            }
          } else {
            showError("paymentResponse", "Payment session creation failed");
            DOM.setButtonState("enrolButton", false, "Enrol Now");
          }
        } catch (error) {
          console.error("Enrollment failed:", error);
          showError(
            "paymentResponse",
            `Enrollment failed: ${error.message}. Please try again or contact admin.`
          );
          DOM.setButtonState("enrolButton", false, "Enrol Now");
        }
      };

      // Optimized helper functions
      const showError = (containerId, message) => {
        DOM.setHTML(
          containerId,
          `<p style="color: red; font-weight: bold;">${message}</p>`
        );
        DOM.toggle(containerId, true);
      };

      const clearError = (containerId) => {
        DOM.setHTML(containerId, "");
        DOM.toggle(containerId, false);
      };

      // Simplified UI update based on PHP backend response
      const updateUIFromServerResponse = (data) => {
        if (data.ui_state === "error" && data.error_message) {
          showError("paymentResponse", data.error_message);
          // Hide the pay button when there's a minimum cost error
          DOM.toggle("enrolButton", false);
        } else {
          clearError("paymentResponse");
          // Show the pay button for valid states
          if (data.ui_state === "paid") {
            DOM.toggle("enrolButton", true);
          }
        }

        // Show/hide discount section if needed
        if (
          data.show_sections &&
          data.show_sections.discount_section !== undefined
        ) {
          DOM.toggle("discount-section", data.show_sections.discount_section);
        }

        // Update total amount display
        if (data.status && data.currency) {
          const totalAmount = DOM.get("total-amount");
          if (totalAmount) {
            totalAmount.textContent = `${data.currency} ${parseFloat(
              data.status
            ).toFixed(2)}`;
          }
        }
      };

      // Simplified coupon section display using PHP data
      const showValidCouponSection = (couponData) => {
        DOM.toggle("discount-section", true);

        // Update discount information from PHP backend
        if (couponData.coupon_name) {
          DOM.setHTML("discount-tag", couponData.coupon_name);
        }

        if (couponData.discount_amount && couponData.currency) {
          DOM.setHTML(
            "discount-amount-display",
            `-${couponData.currency} ${parseFloat(
              couponData.discount_amount
            ).toFixed(2)}`
          );
        }

        if (couponData.coupon_type && couponData.discount_value) {
          const noteText =
            couponData.coupon_type === "percent_off"
              ? `${couponData.discount_value}% off`
              : `${couponData.currency} ${parseFloat(
                  couponData.discount_value
                ).toFixed(2)} off`;
          DOM.setHTML("discount-note", noteText);
        }
      };

      // Optimized event listeners setup
      const setupEventListeners = () => {
        const elements = [
          { id: "apply", event: "click", handler: applyCouponHandler },
          { id: "enrolButton", event: "click", handler: EnrollHandler },
        ];

        elements.forEach(({ id, event, handler }) => {
          const element = DOM.get(id);
          if (element) element.addEventListener(event, handler);
        });
      };

      // Initialize the module
      setupEventListeners();
    },
  };
});
