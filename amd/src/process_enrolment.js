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
 * Confirms a Stripe Checkout session and completes enrolment from the return-URL
 * landing page (process_enrolment.php), the same way stripe_payment.js drives
 * apply_coupon/process_payment: a webservice call over core/ajax, authenticated by
 * the browser's own session - never a site-wide token.
 *
 * @module enrol_stripepayment/process_enrolment
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2026 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ajax from 'core/ajax';

const { call: fetchMany } = ajax;

const processEnrolment = (sessionid) =>
    fetchMany([{ methodname: "moodle_stripepayment_process_enrolment", args: { sessionid } }])[0];

const showError = (message) => {
    const container = document.getElementById('stripepayment-processing-error');
    if (container) {
        container.textContent = message;
        container.classList.remove('d-none');
    }
};

const init = (sessionid) => {
    processEnrolment(sessionid).then((result) => {
        window.location.href = result.redirecturl;
        return null;
    }).catch((error) => {
        showError(error.message);
    });
};

export default {
    init,
};
