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

namespace enrol_stripepayment;

/**
 * Hook callbacks to get the enrolment information.
 *
 * @package    enrol_stripepayment
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_enrolment_callbacks {

    /**
     * Callback for the user_enrolment hook.
     *
     * @param \core_enrol\hook\after_user_enrolled $hook
     */
    public static function send_course_welcome_message(\core_enrol\hook\after_user_enrolled $hook): void {
        $instance = $hook->get_enrolinstance();
        // Send welcome message.
        if ($instance->enrol == 'stripepayment' && $instance->customint1 && $instance->customint1 !== ENROL_DO_NOT_SEND_EMAIL) {
            $plugin = enrol_get_plugin($instance->enrol);
            $plugin->send_course_welcome_message_to_user(
                instance: $instance,
                userid: $hook->get_userid(),
                sendoption: $instance->customint1,
                message: $instance->customtext1,
                roleid: $hook->roleid,
            );
        }
    }

    public static function send_teacher_admin_message(\core_enrol\hook\after_user_enrolled $hook): void {
        $instance = $hook->get_enrolinstance();
        if ($instance->enrol != 'stripepayment') {
            return;
        }
        $userid = $hook->get_userid();
        $plugin = enrol_get_plugin($instance->enrol);
        $context = \context_course::instance($instance->courseid);
        $course = get_course($instance->courseid);
        $user = \core_user::get_user($userid);
        $mailteachers = $plugin->get_config('mailteachers');
        $mailadmins = $plugin->get_config('mailadmins');
        $shortname = format_string($course->shortname, true, ['context' => $context]);
        $subject = get_string("enrolmentnew", 'enrol', $shortname);
        $orderdetails = new \stdClass();
        $orderdetails->course = format_string($course->fullname, true, ['context' => $context]);
        $orderdetails->user = fullname($user);
        $orderdetails->email = $user->email;

        if (!empty($mailteachers)) {
            if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                                     '', '', '', '', false, true)) {
                $users = sort_by_roleassignment_authority($users, $context);
                $teacher = array_shift($users);
            } else {
                $teacher = false;
            }

            if (!empty($teacher)) {
                $fullmessage = get_string('enrolmentnewuser', 'enrol', $orderdetails);
                $fullmessagehtml = html_to_text('<p>'.get_string('enrolmentnewuser', 'enrol', $orderdetails).'</p>');
                // Send test email.
                ob_start();
                email_to_user($teacher, $user, $subject, $fullmessage, $fullmessagehtml);
                ob_get_contents();
                ob_end_clean();
            }
        }

        if (!empty($mailadmins)) {
            $admins = get_admins();
            foreach ($admins as $admin) {
                $fullmessage = get_string('enrolmentnewuser', 'enrol', $orderdetails);
                $fullmessagehtml = html_to_text('<p>'.get_string('enrolmentnewuser', 'enrol', $orderdetails).'</p>');
                // Send test email.
                ob_start();
                email_to_user($admin, $user, $subject, $fullmessage, $fullmessagehtml);
                ob_get_contents();
                ob_end_clean();
            }
        }
    }

}
