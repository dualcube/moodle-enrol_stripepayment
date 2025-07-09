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

          // Call enhanced Moodle web service (now includes UI state calculation)
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

            // Check if auto-enrollment was completed in PHP backend
            if (data.auto_enrolled) {
              // Show success message and reload page since user is now enrolled
              DOM.setHTML(
                "new_coupon",
                '<p style="color:green;"><b>Coupon applied and enrolled successfully! Redirecting...</b></p>'
              );
              // Reload page after a short delay to show the success message
              setTimeout(() => {
                location.reload();
              }, 1500);
              return; // Exit early since enrollment is complete
            }

            if (data.show_sections && data.show_sections.free_enrollment) {
              // Free enrollment available but not auto-completed (fallback)
              freeEnrollHandler();
            }

            // Update UI based on server response (moved logic from client to server)
            updateUIFromServerResponse(data);

            // Show the validcoupon section if discount was applied
            if (data.show_sections && data.show_sections.discount_section) {
              showValidCouponSection(data);
            }
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

          // Call free enrollment web service
          await MoodleAjax.call("moodle_stripepayment_free_enrolsettings", {
            user_id: user_id,
            couponid: couponid,
            instance_id: instance_id,
          });

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
          const data = await MoodleAjax.call(
            "moodle_stripepayment_stripe_js_settings",
            {
              user_id: user_id,
              couponid: couponid,
              instance_id: instance_id,
            }
          );

          // Check if response contains an error (minimum cost validation)
          if (data.error && data.error.message) {
            handlePaymentError(
              data.error.message,
              payButton,
              responseContainer,
              buy_now_string
            );
          } else if (data.status) {
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

      // Helper function to update UI based on server response (moved logic from client to server)
      const updateUIFromServerResponse = (data) => {
        // Handle error state
        if (data.ui_state === "error" && data.error_message) {
          const errorContainer = DOM.get("paymentResponse");
          if (errorContainer) {
            errorContainer.innerHTML = `<p style="color: red; font-weight: bold;">${data.error_message}</p>`;
            errorContainer.style.display = "block";
          }
        } else {
          // Clear any existing error messages
          const errorContainer = DOM.get("paymentResponse");
          if (errorContainer) {
            errorContainer.style.display = "none";
            errorContainer.innerHTML = "";
          }
        }

        // Update section visibility based on server response
        if (data.show_sections) {
          // Show/hide paid enrollment button
          DOM.toggle("amountgreaterzero", data.show_sections.paid_enrollment);

          // Show/hide free enrollment button
          DOM.toggle("amountequalzero", data.show_sections.free_enrollment);

          if (data.show_sections.discount_section) {
            const discountSection = DOM.get("discount-section");
            if (discountSection) {
              discountSection.style.display = "block";
            }
          }
        }

        // Update total amount if provided
        if (data.status && data.currency) {
          const totalAmount = DOM.get("total-amount");
          if (totalAmount) {
            totalAmount.textContent = `${data.currency} ${parseFloat(
              data.status
            ).toFixed(2)}`;
          }
        }
      };

      // Helper function to show valid coupon section (simplified to use server data)
      const showValidCouponSection = (couponData) => {
        // Show discount section
        const discountSection = DOM.get("discount-section");
        if (discountSection) {
          discountSection.style.display = "block";

          // Update discount tag with coupon name (server provides the name)
          const discountTag = DOM.get("discount-tag");
          if (discountTag && couponData.coupon_name) {
            discountTag.textContent = couponData.coupon_name;
          }

          // Update discount amount display (server provides formatted amount)
          const discountAmountDisplay = DOM.get("discount-amount-display");
          if (
            discountAmountDisplay &&
            couponData.discount_amount &&
            couponData.currency
          ) {
            discountAmountDisplay.textContent = `-${
              couponData.currency
            } ${parseFloat(couponData.discount_amount).toFixed(2)}`;
          }

          // Update discount note (server provides the discount value and type)
          const discountNote = DOM.get("discount-note");
          if (discountNote) {
            if (couponData.coupon_type === "percent_off") {
              discountNote.textContent = `${couponData.discount_value}% off`;
            } else if (couponData.coupon_type === "amount_off") {
              discountNote.textContent = `${couponData.currency} ${parseFloat(
                couponData.discount_value
              ).toFixed(2)} off`;
            }
          }
        }
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
      // Initialize the module
      setupEventListeners();
    },
  };
});
