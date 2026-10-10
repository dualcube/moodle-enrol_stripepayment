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
 * Confirms a Stripe Checkout session and completes enrolment.
 *
 * Loaded on whatever page Stripe's success_url sends the browser back to (see
 * enrol_stripepayment_before_footer() in lib.php) - not a page this plugin owns, so
 * there's no container of our own to report errors into; core/notification's standard
 * exception display is used instead. Confirmation itself goes through
 * moodle_stripepayment_process_enrolment over core/ajax, authenticated by the browser's
 * own session - never a site-wide token - the same pattern stripe_payment.js uses for
 * apply_coupon/process_payment.
 *
 * @module enrol_stripepayment/process_enrolment
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2026 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ajax from 'core/ajax';
import Notification from 'core/notification';

const { call: fetchMany } = ajax;

const processEnrolment = (sessionid) =>
    fetchMany([{ methodname: "moodle_stripepayment_process_enrolment", args: { sessionid } }])[0];

// Drop stripe_session_id from the visible URL immediately, so a page refresh (or the
// browser restoring this tab later) can't re-trigger confirmation with a session Stripe
// has already settled.
const stripSessionIdFromUrl = () => {
    const url = new URL(window.location.href);
    url.searchParams.delete('stripe_session_id');
    window.history.replaceState({}, document.title, url.toString());
};

const init = (sessionid) => {
    stripSessionIdFromUrl();
    processEnrolment(sessionid).then((result) => {
        window.location.href = result.redirecturl;
        return null;
    }).catch(Notification.exception);
};

export default {
    init,
};
