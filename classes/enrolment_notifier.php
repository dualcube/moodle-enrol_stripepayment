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
 * Enrolment notification and admin error messaging for the Stripe payment plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment;

use context_system;
use core_user;
use moodle_url;
use stdClass;

/**
 * Sends the Message API notifications the Stripe payment plugin needs: enrolment
 * notifications to students/teachers/managers/admins, and admin alerts on transaction failure.
 *
 * Holds the Moodle Message API calls that {@see util} used to carry directly - split
 * out on its own so each class stays focused and small.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_notifier {
    /**
     * send error message to admin using Message API
     * @param string  $subject
     * @param array $data
     */
    public static function message_stripepayment_error_to_admin($subject, $data) {
        global $PAGE;
        $PAGE->set_context(context_system::instance());

        $admin = get_admin();
        $site = get_site();
        $messagebody = $site->fullname . ": " . get_string('transactionfailed', 'enrol_stripepayment') . "\n\n";
        foreach ($data as $key => $value) {
            $messagebody .= s($key) . " => " . s($value) . "\n";
        }
        $messagesubject = get_string('stripeapierror', 'enrol_stripepayment', $subject);
        $fullmessage = $messagebody;
        $fullmessagehtml = '<p>' . nl2br(s($messagebody)) . '</p>';
        self::send_message_custom(
            $site,
            core_user::get_noreply_user(),
            $admin,
            $messagesubject,
            'Site administration',
            'enrol_stripepayment',
            $fullmessage,
            $fullmessagehtml
        );
    }

    /**
     * Send enrollment notifications to students, teachers, managers, and admins
     * @param stdClass $course The course object
     * @param stdClass $context The course context
     * @param stdClass $user The enrolled user
     * @param object $plugin The enrollment plugin instance
     */
    public static function send_enrollment_notifications($course, $context, $user, $plugin) {
        $teachers = self::get_users_with_archetype($context, 'editingteacher');
        $managers = self::get_users_with_archetype($context, 'manager');
        $teacher = $teachers ? reset($teachers) : false;

        // Notification settings.
        $mailstudents = $plugin->get_config('mailstudents');
        $mailteachers = $plugin->get_config('mailteachers');
        $mailmanagers = $plugin->get_config('mailmanagers');
        $mailadmins   = $plugin->get_config('mailadmins');

        // Common data.
        $shortname = format_string($course->shortname, true, ['context' => $context]);
        $sitename = new moodle_url('/');

        $adminsubject = get_string(
            "enrolmentnew",
            'enrol_stripepayment',
            ['username' => fullname($user), 'course' => $course->fullname],
        );
        $adminmessage = get_string(
            'adminmessage',
            'enrol_stripepayment',
            ['username' => fullname($user), 'course' => $course->fullname, 'sitename' => $sitename],
        );

        // Map notification rules.
        $notifications = [
            'students' => [
                'enabled' => !empty($mailstudents),
                'recipient' => $user,
                'from' => empty($teacher) ? core_user::get_noreply_user() : $teacher,
                'subject' => get_string("enrolmentuser", 'enrol_stripepayment', $shortname),
                'message' => get_string(
                    'welcometocoursetext',
                    'enrol_stripepayment',
                    ['course' => $course->fullname, 'sitename' => $sitename],
                ),
            ],
            'teachers' => [
                'enabled' => !empty($mailteachers) && !empty($teachers),
                'recipient' => array_values($teachers),
                'from' => $user,
                'subject' => $adminsubject,
                'message' => $adminmessage,
            ],
            'managers' => [
                'enabled' => !empty($mailmanagers) && !empty($managers),
                'recipient' => array_values($managers),
                'from' => $user,
                'subject' => $adminsubject,
                'message' => $adminmessage,
            ],
            'admins' => [
                'enabled' => !empty($mailadmins),
                'recipient' => get_admins(),
                'from' => $user,
                'subject' => $adminsubject,
                'message' => $adminmessage,
            ],
        ];

        self::dispatch_notifications($course, $shortname, $notifications);
    }

    /**
     * All users holding a role of the given archetype in this context, including roles
     * assigned in parent contexts (e.g. a category- or site-level manager, found from a
     * course context). Keyed by user id, so callers get each user only once even if they
     * hold more than one role matching the archetype.
     *
     * @param stdClass $context The course context
     * @param string $archetype Role archetype, e.g. 'editingteacher' or 'manager'
     * @return stdClass[] users keyed by id
     */
    private static function get_users_with_archetype($context, $archetype) {
        $users = [];
        foreach (get_archetype_roles($archetype) as $role) {
            $users += get_role_users($role->id, $context, true, 'u.*', 'u.id ASC');
        }
        return $users;
    }

    /**
     * Send every enabled notification from a send_enrollment_notifications() rule map.
     *
     * @param stdClass $course The course object
     * @param string $shortname Course shortname
     * @param array $notifications Rule map built by send_enrollment_notifications()
     */
    private static function dispatch_notifications($course, $shortname, array $notifications) {
        foreach ($notifications as $notify) {
            if (!$notify['enabled']) {
                continue;
            }

            $fullmessage = $notify['message'];
            $fullmessagehtml = '<p>' . $fullmessage . '</p>';

            self::send_message_custom(
                $course,
                $notify['from'],
                $notify['recipient'],
                $notify['subject'],
                $course->fullname,
                $shortname,
                $fullmessage,
                $fullmessagehtml
            );
        }
    }

    /**
     * Send a message to one or more recipients.
     *
     * @param stdClass $course Course object
     * @param stdClass $userfrom User sending the message
     * @param mixed $userto User(s) receiving the message
     * @param string $subject Message subject
     * @param string $contexturlname Order details
     * @param string $shortname Course shortname
     * @param string $fullmessage Full message text
     * @param string $fullmessagehtml Full message HTML
     * @return void
     */
    private static function send_message_custom(
        $course,
        $userfrom,
        $userto,
        $subject,
        $contexturlname,
        $shortname,
        $fullmessage,
        $fullmessagehtml
    ) {
        $recipients = is_array($userto) ? $userto : [$userto];
        foreach ($recipients as $recipient) {
            $message = new \core\message\message();
            $message->courseid = $course->id;
            $message->component = $shortname;
            $message->name = $shortname;
            $message->userfrom = $userfrom;
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = $fullmessage;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $fullmessagehtml;
            $message->smallmessage = get_string('newenrolment', 'enrol_stripepayment', $shortname);
            $message->notification = 1;
            $message->contexturl = new \core\url('/course/view.php', ['id' => $course->id]);
            $message->contexturlname = $contexturlname;

            if (!message_send($message)) {
                debugging("Failed to send stripepayment enrolment notification to user: {$recipient->id}", DEBUG_DEVELOPER);
            }
        }
    }
}
