// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

define(["core/ajax"], function (ajax) {
  const { call: fetchMany } = ajax;

  // Repository functions following ajaxdoc guidelines
  const applyCoupon = (couponid, instanceid) =>
    fetchMany([{ methodname: "moodle_stripepayment_applycoupon", args: { couponid, instanceid } }])[0];

  const stripeenrol = (userid, couponid, instanceid) =>
    fetchMany([{ methodname: "moodle_stripepayment_enrol", args: { userid, couponid, instanceid } }])[0];

  // Optimized DOM utility with caching
  const createDOM = (instanceid) => {
    const cache = new Map();
    return {
      getelement(id) {
        const fullid = `${id}-${instanceid}`;
        if (!cache.has(fullid)) {
          cache.set(fullid, document.getElementById(fullid));
        }
        return cache.get(fullid);
      },
      setelement(id, html) {
        const element = this.getelement(id);
        if (element) {
          element.innerHTML = html;
        }
      },
      toggleelement(id, show) {
        const element = this.getelement(id);
        if (element) {
          element.style.display = show ? "block" : "none";
        }
      },
      focuselement(id) {
        const element = this.getelement(id);
        if (element) {
          element.focus();
        }
      },
      setbutton(id, disabled, text, opacity = disabled ? "0.7" : "1") {
        const button = this.getelement(id);
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
    stripe_payment: function (userid, publishablekey, couponid, instanceid, pleasewaitstring, entercoupon, couponappling) {
      const DOM = createDOM(instanceid);
      if (typeof window.Stripe === "undefined") {
        return;
      }
      const applyCouponHandler = async (event) => {
        event.preventDefault();
        const couponinput = DOM.getelement("coupon");
        const couponcode = couponinput?.value.trim();
        if (!couponcode) {
          displayMessage("showmessage", entercoupon, "error");
          DOM.focuselement("coupon");
          return;
        }
        DOM.setbutton("apply", true, couponappling);
        try {
          const data = await applyCoupon(couponcode, instanceid);
          if (data?.status !== undefined) {
            couponid = couponcode;
            DOM.toggleelement("coupon", false);
            DOM.toggleelement("apply", false);
            updateUIFromServerResponse(data);
          } else {
            throw new Error("Invalid server response");
          }
        } catch (error) {
          displayMessage("showmessage", error.message || "Coupon validation failed", "error");
          DOM.focuselement("coupon");
        }
      };

      const EnrollHandler = async () => {
        const enrollbutton = DOM.getelement("enrolbutton");
        if (!enrollbutton) {
          return;
        }
        clearError("paymentresponse");
        DOM.setbutton("enrolbutton", true, pleasewaitstring);
        try {
          const paymentdata = await stripeenrol(userid, couponid, instanceid);
          if (paymentdata.error?.message) {
            displayMessage("paymentresponse", paymentdata.error.message, "error");
          } else if (paymentdata.status === "success" && paymentdata.redirecturl) {
            window.location.href = paymentdata.redirecturl;
          } else {
            displayMessage("paymentresponse", "Unknown error occurred during payment.", "error");
          }
        } catch (err) {
          displayMessage("paymentresponse", err.message, "error");
        } finally {
          DOM.toggleelement("enrolbutton", false);
        }
      };

      const displayMessage = (containerid, message, type) => {
        let color;
        switch (type) {
          case "error":
            color = "red";
            break;
          case "success":
            color = "green";
            break;
          default:
            color = "blue";
            break;
        }
        DOM.setelement(containerid, `<p style="color: ${color}; font-weight: bold;">${message}</p>`);
        DOM.toggleelement(containerid, true);
      };

      const clearError = (containerid) => {
        DOM.setelement(containerid, "");
        DOM.toggleelement(containerid, false);
      };

      const updateUIFromServerResponse = (data) => {
        if (data.message) {
          displayMessage("showmessage", data.message, data.uistate === "error" ? "error" : "success");
        } else {
          clearError("showmessage");
        }
        DOM.toggleelement("enrolbutton", data.uistate === "paid");
        DOM.toggleelement("total", data.uistate === "paid");
        if (data.uistate !== "error") {
          DOM.toggleelement("discountsection", data.showsections.discountsection);
          if (data.showsections.discountsection) {
            if (data.couponname) {
              DOM.setelement("discounttag", data.couponname);
            }
            if (data.discountamount && data.currency) {
              DOM.setelement("discountamountdisplay", `-${data.currency} ${data.discountamount}`);
            }
            if (data.discountamount && data.discountvalue) {
              const note = data.coupontype === "percentoff"
                ? `${data.discountvalue}% off`
                : `${data.currency} ${data.discountvalue} off`;
              DOM.setelement("discountnote", note);
            }
          }
          if (data.status && data.currency) {
            const totalamount = DOM.getelement("totalamount");
            if (totalamount) {
              totalamount.textContent = `${data.currency} ${parseFloat(data.status).toFixed(2)}`;
            }
          }
        }
      };

      const setupEventListeners = () => {
        const elements = [
          { id: "apply", event: "click", handler: applyCouponHandler },
          { id: "enrolbutton", event: "click", handler: EnrollHandler },
        ];
        elements.forEach(({ id, event, handler }) => {
          const element = DOM.getelement(id);
          if (element) {
            element.addEventListener(event, handler);
          }
        });
      };
      setupEventListeners();
    },
  };
});