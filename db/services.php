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
 * Web services for stripe enrolment plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$services = ['moodle_enrol_stripepayment' => [
    'functions' => [
            'moodle_stripepayment_apply_coupon',
            'moodle_stripepayment_process_enrolment',
            'moodle_stripepayment_process_payment',
        ],
        'requiredcapability' => '',
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'enrolstripepayment', ],
    ];
$functions = [
    'moodle_stripepayment_apply_coupon' => [
        'classname' => 'enrol_stripepayment\external\apply_coupon',
        'description' => 'Load coupon settings data',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'moodle_stripepayment_process_payment' => [
        'classname' => 'enrol_stripepayment\external\process_payment',
        'description' => 'Update information after Stripe Successful Connect',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'moodle_stripepayment_process_enrolment' => [
        'classname' => 'enrol_stripepayment\external\process_enrolment',
        'description' => 'Update information after Stripe Successful Payment',
        'type' => 'write',
        'loginrequired' => true,
    ],

];
