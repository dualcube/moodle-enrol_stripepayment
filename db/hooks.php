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
 * Hook callbacks for enrol_stripepayment
 *
 * @package    enrol_stripepayment
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => core_enrol\hook\after_user_enrolled::class,
        'callback' => 'enrol_stripepayment\user_enrolment_callbacks::send_course_welcome_message',
    ],
    [
        'hook' => core_enrol\hook\after_user_enrolled::class,
        'callback' => 'enrol_stripepayment\user_enrolment_callbacks::send_teacher_admin_message',
    ],
];
