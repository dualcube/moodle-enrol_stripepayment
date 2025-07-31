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

/**
 * External library for stripepayment
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ajax from 'core/ajax';

const { call: fetchMany } = ajax;

// Repository functions
const applyCoupon = (couponid, instanceid) =>
    fetchMany([{ methodname: "moodle_stripepayment_applycoupon", args: { couponid, instanceid } }])[0];

const stripeEnrol = (userid, couponid, instanceid) =>
    fetchMany([{ methodname: "moodle_stripepayment_enrol", args: { userid, couponid, instanceid } }])[0];

const createDOM = (instanceid) => {
    const cache = new Map();
    return {
        getElement(id) {
            const fullid = `${id}-${instanceid}`;
            if (!cache.has(fullid)) {
                cache.set(fullid, document.getElementById(fullid));
            }
            return cache.get(fullid);
        },
        setElement(id, html) {
            const element = this.getElement(id);
            if (element) {
                element.innerHTML = html;
            }
        },
        toggleElement(id, show) {
            const element = this.getElement(id);
            if (element) {
                element.style.display = show ? "block" : "none";
            }
        },
        focusElement(id) {
            const element = this.getElement(id);
            if (element) {
                element.focus();
            }
        },
        setButton(id, disabled, text, opacity = disabled ? "0.7" : "1") {
            const button = this.getElement(id);
            if (button) {
                button.disabled = disabled;
                button.textContent = text;
                button.style.opacity = opacity;
                button.style.cursor = disabled ? "not-allowed" : "pointer";
            }
        },
    };
};

function stripePayment(userid, couponid, instanceid, pleasewaitstring, entercoupon, couponappling,paymenterror) {
    const DOM = createDOM(instanceid);
    if (typeof window.Stripe === "undefined") {
        return;
    }

    const displayMessage = (containerid, message, type) => {
        let color;
        switch (type) {
            case "error": color = "red"; break;
            case "success": color = "green"; break;
            default: color = "blue"; break;
        }
        DOM.setElement(containerid, `<p style="color: ${color}; font-weight: bold;">${message}</p>`);
        DOM.toggleElement(containerid, true);
    };

    const clearError = (containerid) => {
        DOM.setElement(containerid, "");
        DOM.toggleElement(containerid, false);
    };

    const updateUIFromServerResponse = (data) => {
        if (data.message) {
            displayMessage("showmessage", data.message, data.uistate === "error" ? "error" : "success");
        } else {
            clearError("showmessage");
        }

        DOM.toggleElement("enrolbutton", data.uistate === "paid");
        DOM.toggleElement("total", data.uistate === "paid");

        if (data.uistate !== "error") {
            DOM.toggleElement("discountsection", data.showsections.discountsection);
            if (data.showsections.discountsection) {
                if (data.couponname) {
                    DOM.setElement("discounttag", data.couponname);
                }
                if (data.discountamount && data.currency) {
                    DOM.setElement("discountamountdisplay", `-${data.currency} ${data.discountamount}`);
                }
                if (data.discountamount && data.discountvalue) {
                    const note = data.coupontype === "percentoff"
                        ? `${data.discountvalue}% off`
                        : `${data.currency} ${data.discountvalue} off`;
                    DOM.setElement("discountnote", note);
                }
            }
            if (data.status && data.currency) {
                const totalamount = DOM.getElement("totalamount");
                if (totalamount) {
                    totalamount.textContent = `${data.currency} ${parseFloat(data.status).toFixed(2)}`;
                }
            }
        }
    };

    const applyCouponHandler = async (event) => {
        event.preventDefault();
        const couponinput = DOM.getElement("coupon");
        const couponcode = couponinput?.value.trim();
        if (!couponcode) {
            displayMessage("showmessage", entercoupon, "error");
            DOM.focusElement("coupon");
            return;
        }
        DOM.setButton("apply", true, couponappling);
        try {
            const data = await applyCoupon(couponcode, instanceid);
            if (data?.status !== undefined) {
                couponid = couponcode;
                DOM.toggleElement("coupon", false);
                DOM.toggleElement("apply", false);
                updateUIFromServerResponse(data);
            } else {
                throw new Error("Invalid server response");
            }
        } catch (error) {
            displayMessage("showmessage", error.message || "Coupon validation failed", "error");
            DOM.focusElement("coupon");
        }
    };

    const EnrollHandler = async () => {
        const enrollbutton = DOM.getElement("enrolbutton");
        if (!enrollbutton) return;
        clearError("paymentresponse");
        DOM.setButton("enrolbutton", true, pleasewaitstring);
        try {
            const paymentdata = await stripeEnrol(userid, couponid, instanceid);
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
            DOM.toggleElement("enrolbutton", false);
        }
    };

    const setupEventListeners = () => {
        const elements = [
            { id: "apply", event: "click", handler: applyCouponHandler },
            { id: "enrolbutton", event: "click", handler: EnrollHandler },
        ];
        elements.forEach(({ id, event, handler }) => {
            const element = DOM.getElement(id);
            if (element) {
                element.addEventListener(event, handler);
            }
        });
    };

    setupEventListeners();
}

export default {
    stripePayment,
};
