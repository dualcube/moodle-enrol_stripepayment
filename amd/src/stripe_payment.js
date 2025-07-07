define(["core/ajax"], function (ajax) {
  /**
   * Modern Stripe Payment Module
   * Modernized to use async/await, fetch API, and vanilla JavaScript
   */

  // Utility functions for modern AJAX calls
  const MoodleAjax = {
    async call(methodname, args) {
      try {
        const promises = ajax.call([
          {
            methodname: methodname,
            args: args,
          },
        ]);
        return await promises[0];
      } catch (error) {
        throw new Error(`AJAX call failed: ${error.message}`);
      }
    },
  };

  // Instance-aware DOM utility functions
  const createDOM = (instanceId) => ({
    get(id) {
      return document.getElementById(`${id}-${instanceId}`);
    },

    setValue(id, value) {
      const element = this.get(id);
      if (element) {
        element.value = value;
      }
    },

    setAttribute(id, attr, value) {
      const element = this.get(id);
      if (element) {
        element.setAttribute(attr, value);
      }
    },

    setHTML(id, html) {
      const element = this.get(id);
      if (element) {
        element.innerHTML = html;
      }
    },

    toggle(id, show) {
      const element = this.get(id);
      if (element) {
        element.style.display = show ? "block" : "none";
      }
    },

    focus(id) {
      const element = this.get(id);
      if (element) {
        element.focus();
      }
    },
  });
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

      // Coupon application functionality
      const applyCouponHandler = async (event) => {
        event.preventDefault();

        const couponInput = DOM.get("coupon");
        const applyButton = DOM.get("apply");
        const couponCode = couponInput ? couponInput.value.trim() : "";

        if (!couponCode) {
          DOM.setHTML(
            "new_coupon",
            '<p style="color:red;"><b>Please enter a coupon code</b></p>'
          );
          DOM.focus("coupon");
          return;
        }

        // Disable button during processing with visual feedback
        if (applyButton) {
          applyButton.disabled = true;
          applyButton.textContent = "Applying...";
          applyButton.style.opacity = "0.7";
          applyButton.style.cursor = "not-allowed";
        }

        try {
          // Show loading state
          DOM.setHTML(
            "new_coupon",
            '<p style="color:blue;">Applying coupon...</p>'
          );

          // Call Moodle web service
          const data = await MoodleAjax.call(
            "moodle_stripepayment_couponsettings",
            {
              couponid: couponCode,
              instance_id: instance_id,
            }
          );

          // Validate response
          if (data && typeof data.status !== "undefined") {
            // Update the couponid variable for payment processing
            couponid = couponCode;
            console.log(
              "Coupon applied successfully. Updated couponid to:",
              couponid
            );

            // Show success message
            DOM.setHTML(
              "new_coupon",
              '<p style="color:green;"><b>Coupon applied successfully!</b></p>'
            );

            // Hide the coupon input section after successful application (instance-specific)
            const paymentContainer =
              DOM.get("coupon").closest(".payment-container");
            const couponInputGroup = paymentContainer
              ? paymentContainer.querySelector(".coupon-input-group")
              : null;
            if (couponInputGroup) {
              couponInputGroup.style.display = "none";
            }

            // Get original cost and currency from DOM first
            const subtotalElement = DOM.get("subtotal-amount");
            const originalCostText = subtotalElement
              ? subtotalElement.textContent
              : "";
            const matches = originalCostText.match(/([A-Z$€£¥]+)\s*([\d.]+)/);
            const currency = matches ? matches[1] : "USD";
            const originalCost = matches ? parseFloat(matches[2]) : 0;

            // Update cost display and payment buttons
            const finalCost = parseFloat(data.status);
            checkmincost(finalCost, currency);
            // Show the validcoupon section by triggering template update
            showValidCouponSection(finalCost, originalCost, currency, data);
          } else {
            throw new Error("Invalid response from server");
          }
        } catch (error) {
          console.error("Coupon application failed:", error);
          DOM.setHTML(
            "new_coupon",
            `<p style="color:red;"><b>${invalid_code_string}</b></p>`
          );
          DOM.focus("coupon");
        } finally {
          // Re-enable button with visual feedback
          if (applyButton) {
            applyButton.disabled = false;
            applyButton.textContent = "Apply code";
            applyButton.style.opacity = "1";
            applyButton.style.cursor = "pointer";
          }
        }
      };

      // Free enrollment functionality
      const freeEnrollHandler = async () => {
        const freeButton = DOM.get("card-button-zero");
        if (!freeButton) return;

        try {
          // Disable button and show loading
          freeButton.disabled = true;
          freeButton.textContent = please_wait_string;

          console.log("Starting free enrollment with couponid:", couponid);

          // Call free enrollment web service
          const result = await MoodleAjax.call(
            "moodle_stripepayment_free_enrolsettings",
            {
              user_id: user_id,
              couponid: couponid,
              instance_id: instance_id,
            }
          );

          console.log("Free enrollment result:", result);

          // Reload page on success
          location.reload();
        } catch (error) {
          console.error("Free enrollment failed:", error);

          // Show error message instead of just reloading
          const errorContainer = DOM.get("paymentResponse");
          if (errorContainer) {
            errorContainer.innerHTML = `<p style="color: red; font-weight: bold;">Free enrollment failed: ${error.message}. Please try again or contact admin.</p>`;
            errorContainer.style.display = "block";
          }

          // Re-enable button
          freeButton.disabled = false;
          freeButton.textContent = "Enroll Now";
        }
      };

      // Paid enrollment functionality
      const paidEnrollHandler = async () => {
        const payButton = DOM.get("payButton");
        const responseContainer = DOM.get("paymentResponse");

        if (!payButton) return;

        try {
          // Disable button and show loading with visual feedback
          payButton.disabled = true;
          payButton.textContent = please_wait_string;
          payButton.style.opacity = "0.7";
          payButton.style.cursor = "not-allowed";

          // Call Stripe checkout web service
          console.log("Creating Stripe checkout with coupon:", couponid);
          const data = await MoodleAjax.call(
            "moodle_stripepayment_stripe_js_settings",
            {
              user_id: user_id,
              couponid: couponid,
              instance_id: instance_id,
            }
          );

          if (data.status) {
            // Redirect to Stripe checkout
            const result = await stripe.redirectToCheckout({
              sessionId: data.status,
            });

            if (result.error) {
              handlePaymentError(
                result.error.message,
                payButton,
                responseContainer,
                buy_now_string
              );
            }
          } else {
            handlePaymentError(
              "Payment session creation failed",
              payButton,
              responseContainer,
              buy_now_string
            );
          }
        } catch (error) {
          console.error("Payment processing failed:", error);
          handlePaymentError(
            error.message,
            payButton,
            responseContainer,
            buy_now_string
          );
        }
      };

      // Helper function to handle payment errors
      const handlePaymentError = (
        errorMessage,
        button,
        container,
        buttonText
      ) => {
        if (container) {
          container.innerHTML = `<p>${errorMessage}</p>`;
          container.style.display = "block";
        }
        if (button) {
          button.disabled = false;
          button.textContent = buttonText;
          button.style.opacity = "1";
          button.style.cursor = "pointer";
        }
      };

      // Helper function to show valid coupon section
      const showValidCouponSection = (
        finalCost,
        originalCost,
        currency,
        couponData
      ) => {
        // Calculate discount amount
        const discountAmount = originalCost - finalCost;

        // Show discount section
        const discountSection = DOM.get("discount-section");
        if (discountSection) {
          discountSection.style.display = "block";

          // Update discount tag with coupon name
          const discountTag = DOM.get("discount-tag");
          if (discountTag) {
            if (couponData && couponData.coupon_name) {
              discountTag.textContent = couponData.coupon_name;
            } else {
              // Fallback to percentage if no coupon name
              const discountPercent = Math.round(
                (discountAmount / originalCost) * 100
              );
              discountTag.textContent = `${discountPercent}% off`;
            }
          }

          // Update discount amount display
          const discountAmountDisplay = DOM.get("discount-amount-display");
          if (discountAmountDisplay) {
            discountAmountDisplay.textContent = `-${currency} ${discountAmount.toFixed(
              2
            )}`;
          }

          // Update discount note based on coupon type
          const discountNote = DOM.get("discount-note");
          if (discountNote) {
            if (couponData && couponData.coupon_type === "percent_off") {
              discountNote.textContent = `${couponData.discount_value}% off`;
            } else if (couponData && couponData.coupon_type === "amount_off") {
              discountNote.textContent = `${currency} ${couponData.discount_value.toFixed(
                2
              )} off`;
            } else {
              // Fallback to calculated percentage
              const discountPercent = Math.round(
                (discountAmount / originalCost) * 100
              );
              discountNote.textContent = `${discountPercent}% off`;
            }
          }
        }

        // Update total amount
        const totalAmount = DOM.get("total-amount");
        if (totalAmount) {
          totalAmount.textContent = `${currency} ${finalCost.toFixed(2)}`;
        }
      };

      // Minimum amount for payment validation
      const checkmincost = (finalcost, currency) => {
        const minamount = {
          USD: 0.5,
          AED: 2.0,
          AUD: 0.5,
          BGN: 1.0,
          BRL: 0.5,
          CAD: 0.5,
          CHF: 0.5,
          CZK: 15.0,
          DKK: 2.5,
          EUR: 0.5,
          GBP: 0.3,
          HKD: 4.0,
          HUF: 175.0,
          INR: 0.5,
          JPY: 50,
          MXN: 10,
          MYR: 2,
          NOK: 3.0,
          NZD: 0.5,
          PLN: 2.0,
          RON: 2.0,
          SEK: 3.0,
          SGD: 0.5,
          THB: 10,
        };

        const minAmount = minamount[currency] || 0.5; // Default to USD minimum

        // Clear any existing error messages
        const errorContainer = DOM.get("paymentResponse");
        if (errorContainer) {
          errorContainer.style.display = "none";
          errorContainer.innerHTML = "";
        }

        // If cost is 0 or negative, treat as free enrollment
        if (finalcost <= 0) {
          DOM.toggle("amountgreaterzero", false);
          DOM.toggle("amountequalzero", true);
          return;
        }

        // If cost is between 0 and minimum, show error
        if (finalcost > 0 && finalcost < minAmount) {
          DOM.toggle("amountgreaterzero", false);
          DOM.toggle("amountequalzero", false);

          // Show error message
          if (errorContainer) {
            errorContainer.innerHTML = `<p style="color: red; font-weight: bold;">Amount is less than supported minimum (${currency} ${minAmount.toFixed(
              2
            )}). Please contact admin.</p>`;
            errorContainer.style.display = "block";
          }
          return;
        }

        // Cost is above minimum, show paid enrollment
        DOM.toggle("amountgreaterzero", true);
        DOM.toggle("amountequalzero", false);
      };

      // Event listeners setup
      const setupEventListeners = () => {
        // Coupon apply button
        const applyButton = DOM.get("apply");
        if (applyButton) {
          applyButton.addEventListener("click", applyCouponHandler);
        }

        // Coupon input field - support Enter key
        const couponInput = DOM.get("coupon");
        if (couponInput) {
          couponInput.addEventListener("keypress", (event) => {
            if (event.key === "Enter") {
              applyCouponHandler(event);
            }
          });
        }

        // Free enrollment button
        const freeButton = DOM.get("card-button-zero");
        if (freeButton) {
          freeButton.addEventListener("click", freeEnrollHandler);
        }

        // Paid enrollment button
        const payButton = DOM.get("payButton");
        if (payButton) {
          payButton.addEventListener("click", paidEnrollHandler);
        }
      };

      // Initial cost validation on page load
      const performInitialCostValidation = () => {
        const subtotalElement = DOM.get("subtotal-amount");
        if (subtotalElement) {
          const originalCostText = subtotalElement.textContent || "";
          const matches = originalCostText.match(/([A-Z$€£¥]+)\s*([\d.]+)/);
          const currency = matches ? matches[1] : "USD";
          const originalCost = matches ? parseFloat(matches[2]) : 0;

          // Validate initial cost
          checkmincost(originalCost, currency);
        }
      };

      // Initialize the module
      setupEventListeners();
      performInitialCostValidation();
    },
  };
});
