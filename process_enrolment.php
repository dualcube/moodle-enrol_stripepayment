<?php
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
 * Landing page for the browser's return from a Stripe Checkout session.
 *
 * Stripe redirects the paying user's own browser here after checkout. This page itself
 * does no processing - it just authenticates the user via their normal Moodle session
 * (no webservice token is ever placed in this URL) and hands the session id to
 * amd/src/process_enrolment.js, which confirms the payment and completes enrolment the
 * same way apply_coupon/process_payment work: a moodle_stripepayment_process_enrolment
 * call over core/ajax, authenticated by that same session.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2026 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$sessionid = required_param('session_id', PARAM_TEXT);

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/enrol/stripepayment/process_enrolment.php', ['session_id' => $sessionid]);
$PAGE->set_title(get_string('pluginname', 'enrol_stripepayment'));
$PAGE->requires->js_call_amd('enrol_stripepayment/process_enrolment', 'init', [$sessionid]);

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('processingpayment', 'enrol_stripepayment'));
echo html_writer::div('', 'alert alert-danger d-none', ['id' => 'stripepayment-processing-error']);
echo $OUTPUT->footer();
