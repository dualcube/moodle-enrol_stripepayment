define(["core/ajax"], function (ajax) {
  const { call: fetchMany } = ajax;

  // Repository functions following ajaxdoc guidelines
  const applyCoupon = (couponid, instance_id) =>
    fetchMany([
      {
        methodname: "moodle_stripepayment_couponsettings",
        args: { couponid, instance_id },
      },
    ])[0];

  const processFreeEnrollment = (user_id, couponid, instance_id) =>
    fetchMany([
      {
        methodname: "moodle_stripepayment_free_enrolsettings",
        args: { user_id, couponid, instance_id },
      },
    ])[0];

  const createPaymentSession = (user_id, couponid, instance_id) =>
    fetchMany([
      {
        methodname: "moodle_stripepayment_stripe_js_settings",
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
            "status",
            '<p style="color:red;"><b>Please enter a coupon code</b></p>'
          );
          DOM.focus("coupon");
          return;
        }

        DOM.setButtonState("apply", true, "Applying...");
        DOM.setHTML(
          "status",
          '<p style="color:blue;">Applying coupon...</p>'
        );

        try {
          // PHP backend handles all validation, calculation, and auto-enrollment
          const data = await applyCoupon(couponCode, instance_id);

          if (data?.status !== undefined) {
            couponid = couponCode; // Update for payment processing
            DOM.setHTML(
              "status",
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
                "status",
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
            "status",
            `<p style="color:red;"><b>${invalid_code_string}</b></p>`
          );
          DOM.focus("coupon");
        } finally {
          DOM.setButtonState("apply", false, "Apply code");
        }
      };

      // Simplified free enrollment
      const freeEnrollHandler = async () => {
        const freeButton = DOM.get("card-button-zero");
        if (!freeButton) return;

        DOM.setButtonState("card-button-zero", true, please_wait_string);

        try {
          await processFreeEnrollment(user_id, couponid, instance_id);
          location.reload();
        } catch (error) {
          console.error("Free enrollment failed:", error);
          showError(
            "paymentResponse",
            `Free enrollment failed: ${error.message}. Please try again or contact admin.`
          );
          DOM.setButtonState("card-button-zero", false, "Enroll Now");
        }
      };

      // Simplified paid enrollment
      const paidEnrollHandler = async () => {
        const payButton = DOM.get("payButton");
        if (!payButton) return;

        DOM.setButtonState("payButton", true, please_wait_string);

        try {
          const data = await createPaymentSession(
            user_id,
            couponid,
            instance_id
          );

          if (data.error?.message) {
            showError("paymentResponse", data.error.message);
            DOM.setButtonState("payButton", false, buy_now_string);
          } else if (data.status) {
            const result = await stripe.redirectToCheckout({
              sessionId: data.status,
            });
            if (result.error) {
              showError("paymentResponse", result.error.message);
              DOM.setButtonState("payButton", false, buy_now_string);
            }
          } else {
            showError("paymentResponse", "Payment session creation failed");
            DOM.setButtonState("payButton", false, buy_now_string);
          }
        } catch (error) {
          console.error("Payment processing failed:", error);
          showError("paymentResponse", error.message);
          DOM.setButtonState("payButton", false, buy_now_string);
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
        } else {
          clearError("paymentResponse");
        }

        if (data.show_sections) {
          DOM.toggle("amountgreaterzero", data.show_sections.paid_enrollment);
          DOM.toggle("amountequalzero", data.show_sections.free_enrollment);
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
          {
            id: "card-button-zero",
            event: "click",
            handler: freeEnrollHandler,
          },
          { id: "payButton", event: "click", handler: paidEnrollHandler },
        ];

        elements.forEach(({ id, event, handler }) => {
          const element = DOM.get(id);
          if (element) element.addEventListener(event, handler);
        });

        // Add Enter key support for coupon input
        const couponInput = DOM.get("coupon");
        if (couponInput) {
          couponInput.addEventListener("keypress", (event) => {
            if (event.key === "Enter") applyCouponHandler(event);
          });
        }
      };

      // Initialize the module
      setupEventListeners();
    },
  };
});
