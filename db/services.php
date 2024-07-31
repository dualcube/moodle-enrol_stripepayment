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
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Web services for stripe enrolment plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$services = [
    'moodle_enrol_stripepayment' => [                  
        'functions' => [
            'moodle_stripepayment_couponsettings',
            'moodle_stripepayment_free_enrolsettings',
            'moodle_stripepayment_stripe_js_settings', 
            'moodle_stripepayment_success_stripe_url',
        ],
        'requiredcapability' => '',
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'enrolstripepayment',
    ]
];
$functions = [
    'moodle_stripepayment_couponsettings' => [
        'classname' => 'moodle_enrol_stripepayment_external',
        'methodname' => 'stripepayment_couponsettings',
        'classpath' => 'enrol/stripepayment/externallib.php',
        'description' => 'Load coupon settings data',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'moodle_stripepayment_free_enrolsettings' => [
        'classname' => 'moodle_enrol_stripepayment_external',
        'methodname' => 'stripepayment_free_enrolsettings',
        'classpath' => 'enrol/stripepayment/externallib.php',
        'description' => 'Update information after Successful Free Enrol',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'moodle_stripepayment_stripe_js_settings' => [
        'classname' => 'moodle_enrol_stripepayment_external',
        'methodname' => 'stripe_js_method',
        'classpath' => 'enrol/stripepayment/externallib.php',
        'description' => 'Update information after Stripe Successful Connect',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'moodle_stripepayment_success_stripe_url' => [
        'classname' => 'moodle_enrol_stripepayment_external',
        'methodname' => 'success_stripe_url',
        'classpath' => 'enrol/stripepayment/externallib.php',
        'description' => 'Update information after Stripe Successful Payment',
        'type' => 'write',
        'ajax' => true,
    ]
];
