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

  const stripeenrol = (user_id, couponid, instance_id) =>
    fetchMany([
      {
        methodname: "moodle_stripepayment_enrol",
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
          displayMessage("show-message", "Please enter a coupon code", "error");
          DOM.focus("coupon");
          return;
        }

        DOM.setButtonState("apply", true, "Applying...");

        try {
          const data = await applyCoupon(couponCode, instance_id);

          if (data?.status !== undefined) {
            couponid = couponCode;

            // Hide input group after success
            DOM.get("coupon")?.closest(".coupon-input-group")?.style.setProperty("display", "none");

            // Handle free auto-enrollment
            if (data.auto_enrolled) {
              displayMessage("show-message", data.message || "Enrolled successfully!", "success");
              setTimeout(() => location.reload(), 1500);
              return;
            }

            updateUIFromServerResponse(data); // Handles rest of the update
          } else {
            throw new Error("Invalid server response");
          }
        } catch (error) {
          console.error("Coupon application failed:", error);
          displayMessage("show-message", error.message || "Coupon validation failed", "error");
          DOM.focus("coupon");
        } finally {
          DOM.setButtonState("apply", false, "Apply code");
        }
      };

      const EnrollHandler = async () => {
        const enrollButton = DOM.get("enrolButton");
        if (!enrollButton) return;

        clearError("paymentResponse");
        DOM.setButtonState("enrolButton", true, please_wait_string);

        try {
          const paymentData = await stripeenrol(user_id, couponid, instance_id);

          if (paymentData.error?.message) {
            displayMessage("paymentResponse", paymentData.error.message, "error");
          } else if (paymentData.status === "success" && paymentData.redirect_url) {
            window.location.href = paymentData.redirect_url; // Redirect browser to Stripe Checkout
          } else {
            displayMessage("paymentResponse", "Payment session creation failed.", "error");
          }
        } catch (err) {
          console.error("Enrollment failed:", err);
          displayMessage("paymentResponse", `Enrollment failed: ${err.message}`, "error");
        } finally {
          DOM.toggle("enrolButton", false);
        }
      };

      const displayMessage = (containerId, message, type = "info") => {
        const color = type === "error" ? "red" : type === "success" ? "green" : "blue";
        DOM.setHTML(containerId, `<p style="color: ${color}; font-weight: bold;">${message}</p>`);
        DOM.toggle(containerId, true);
      };

      const clearError = (containerId) => {
        DOM.setHTML(containerId, "");
        DOM.toggle(containerId, false);
      };

      const updateUIFromServerResponse = (data) => {
        if (data.message) {
            displayMessage("show-message", data.message, data.ui_state === "error" ? "error" : "success");
          } else {
            clearError("show-message");
          }

          // Enrol button
          DOM.toggle("enrolButton", data.ui_state === "paid");
          DOM.toggle("total", data.ui_state === "paid")
          if(data.ui_state!= "error"){
          // Discount section
          DOM.toggle("discount-section", !!data.show_sections?.discount_section);

          // Fill discount data
          if (data.show_sections?.discount_section) {
            if (data.coupon_name) DOM.setHTML("discount-tag", data.coupon_name);
            if (data.discount_amount && data.currency) {
              DOM.setHTML(
                "discount-amount-display",
                `-${data.currency} ${parseFloat(data.discount_amount).toFixed(2)}`
              );
            }

            if (data.coupon_type && data.discount_value) {
              const note =
                data.coupon_type === "percent_off"
                  ? `${data.discount_value}% off`
                  : `${data.currency} ${parseFloat(data.discount_value).toFixed(2)} off`;
              DOM.setHTML("discount-note", note);
            }
          }

          // Update total amount
          if (data.status && data.currency) {
            const totalAmount = DOM.get("total-amount");
            if (totalAmount) {
              totalAmount.textContent = `${data.currency} ${parseFloat(data.status).toFixed(2)}`;
            }
          }
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
