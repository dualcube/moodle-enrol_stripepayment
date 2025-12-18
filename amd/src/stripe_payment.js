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
 * @module enrol_stripepayment/stripe_payment
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ajax from 'core/ajax';
import Str from 'core/str';

const { call: fetchMany } = ajax;
let localized = {};

const init = () => {
    Str.get_strings([
        {key: 'pleasewait', component: 'enrol_stripepayment'},
        {key: 'entercoupon', component: 'enrol_stripepayment'},
        {key: 'couponappling', component: 'enrol_stripepayment'},
        {key: 'couponapply', component: 'enrol_stripepayment'},
        {key: 'couponappliedsuccessfully', component: 'enrol_stripepayment'},
        {key: 'enrolnow', component: 'enrol_stripepayment'},
        {key: 'invalidserverresponse', component: 'enrol_stripepayment'},
        {key: 'unknownpaymenterror', component: 'enrol_stripepayment'},
    ]).then(strings => {
        localized = {
            pleasewait: strings[0],
            entercoupon: strings[1],
            couponappling: strings[2],
            couponapply: strings[3],
            couponappliedsuccessfully: strings[4],
            enrolnow: strings[5],
            invalidserverresponse: strings[7],
            unknownpaymenterror: strings[8],
        };
    }).catch(error => {
        console.error('Failed to load localized strings:', error);
    });
};

// Repository functions
const applyCoupon = (couponid, instance) =>
    fetchMany([{ methodname: "moodle_stripepayment_apply_coupon", args: { couponid, instance } }])[0];

const processPayment = (couponid, instance) =>
    fetchMany([{ methodname: "moodle_stripepayment_process_payment", args: { couponid, instance } }])[0];

const stripePayment = (couponid, instance) => {
    const cache = new Map();
    const getElement = (id) => {
        const fullid = `${id}-${instance['id']}`;
        if (!cache.has(fullid)) {
            cache.set(fullid, document.getElementById(fullid));
        }
        return cache.get(fullid);
    };
    const setElement = (id, html) => {
        const element = getElement(id);
        if (element) {
            element.innerHTML = html;
        }
    };
    const toggleElement = (id, show) => {
        const element = getElement(id);
        if (element) {
            element.style.display = show ? "block" : "none";
        }
    };
    const focusElement = (id) => {
        const element = getElement(id);
        if (element) {
            element.focus();
        }
    };
    const setButton = (id, disabled, text, opacity = disabled ? "0.7" : "1") => {
        const button = getElement(id);
        if (button) {
            button.disabled = disabled;
            button.textContent = text;
            button.style.opacity = opacity;
            button.style.cursor = disabled ? "not-allowed" : "pointer";
        }
    };
    const displayMessage = (containerid, message, type) => {
        let color;
        switch (type) {
            case "error": color = "red"; break;
            case "success": color = "green"; break;
            default: color = "blue"; break;
        }
        setElement(containerid, `<p style="color: ${color}; font-weight: bold;">${message}</p>`);
        toggleElement(containerid, true);
    };
    const clearError = (containerid) => {
        setElement(containerid, "");
        toggleElement(containerid, false);
    };
    const applyCouponHandler = async (event) => {
        event.preventDefault();
        const couponinput = getElement("coupon");
        const couponcode = couponinput?.value.trim();
        if (!couponcode) {
            displayMessage("showmessage", localized.entercoupon, "error");
            focusElement("coupon");
            return;
        }
        setButton("apply", true, localized.couponappling);
        try {
            const data = await applyCoupon(couponcode, instance);
            if (data?.discountedprice !== undefined) {
                couponid = couponcode;
                toggleElement("coupon", false);
                toggleElement("apply", false);
                updateUIFromServerResponse(data);
            } else {
                throw new Error(localized.invalidserverresponse);
            }
        } catch (error) {
            displayMessage("showmessage", error.message, "error");
            focusElement("coupon");
        } finally {
            setButton("apply", false, localized.couponapply);
        }
    };
    const updateUIFromServerResponse = (data) => {
        toggleElement("discountsection", data.displaydiscountsection);
        if (data.displaydiscountsection) {
            setElement("discounttag", data.couponname);
            setElement("discountamountdisplay", data.discountamount);
            setElement("discountnote", data.discountmessage);
            setElement("totalamount", data.discountedprice);
            displayMessage("showmessage", localized.couponappliedsuccessfully, "success");
        }
        setButton("enrolbutton", false, localized.enrolnow);
    };
    const EnrollHandler = async () => {
        clearError("paymentresponse");
        setButton("enrolbutton", true, localized.pleasewait);
        try {
            const paymentdata = await processPayment(couponid, instance);
            if (paymentdata.error?.message) {
                displayMessage("paymentresponse", paymentdata.error.message, "error");
            } else if (paymentdata.status === "success" && paymentdata.redirecturl) {
                window.location.href = paymentdata.redirecturl;
            } else {
                displayMessage("paymentresponse", unknownpaymenterror, "error");
            }
        } catch (err) {
            displayMessage("paymentresponse", err.message, "error");
        }
    };
    const setupEventListeners = () => {
        const elements = [
            { id: "apply", event: "click", handler: applyCouponHandler },
            { id: "enrolbutton", event: "click", handler: EnrollHandler },
        ];
        elements.forEach(({ id, event, handler }) => {
            const element = getElement(id);
            if (element) {
                element.addEventListener(event, handler);
            }
        });
    };

    setupEventListeners();
    init();
};

export default {
    stripePayment,
};
