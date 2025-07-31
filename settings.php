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
 * Stripe enrolment plugin - ENHANCED WITH LIVE/TEST TOGGLE.
 *
 * This plugin allows you to set up paid courses with Live/Test mode toggle.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/enrol/stripepayment/lib.php');
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'enrol_stripepayment_settings',
        '',
        get_string('pluginnamedesc', 'enrol_stripepayment')
    ));
    
    $settings->add(new admin_setting_heading(
        'enrol_stripepayment_mode_settings',
        get_string('stripemodesettings', 'enrol_stripepayment'),
        get_string('stripemodesettingsdesc', 'enrol_stripepayment')
    ));

    // Current Mode Toggle
    $modeoptions = [
        'test' => get_string('testmode', 'enrol_stripepayment', 'Test Mode'),
        'live' => get_string('livemode', 'enrol_stripepayment', 'Live Mode'),
    ];
    
    $currentmode = get_config('enrol_stripepayment', 'stripemode') ?: 'test';
    $modedescription = get_string('stripemodedesc', 'enrol_stripepayment');

    $settings->add(new admin_setting_configselect(
        'enrol_stripepayment/stripemode',
        get_string('stripemode', 'enrol_stripepayment'),
        $modedescription,
        'test',
        $modeoptions
    ));

    // Note: Sections are now conditionally rendered server-side based on current mode
    // This provides a cleaner interface and maintains Moodle's default structure

    // Dynamic Mode Status Display
    $plugin = enrol_get_plugin('stripepayment');
    $modestatustext = $plugin->get_mode_status_display();

    $settings->add(new admin_setting_description(
        'enrol_stripepayment/mode_status',
        get_string('currentmodestatus', 'enrol_stripepayment'),
        $modestatustext
    ));
    
    // Get current mode to show only relevant section
    $current_mode = get_config('enrol_stripepayment', 'stripemode') ?: 'test';

    // Add mode switching instructions
    $mode_switch_text = $current_mode === 'test' ?
        'To switch to Live Mode, change the mode above and save settings.' :
        'To switch to Test Mode, change the mode above and save settings.';

    $settings->add(new admin_setting_description(
        'enrol_stripepayment/mode_switch_info',
        '',
        '<div style="background-color: #f0f8ff; padding: 10px; border-left: 4px solid #2196f3; margin: 10px 0;">' .
        '<strong>ℹ️ Info:</strong> ' . $mode_switch_text .
        '</div>'
    ));

    // Add warning about mode changes affecting instances
    $settings->add(new admin_setting_description(
        'enrol_stripepayment/mode_change_warning',
        '',
        '<div style="background-color: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0;">' .
        '<strong>⚠️ Warning:</strong> Changing the mode will hide enrolment instances created in the other mode. ' .
        'Products and prices are linked to specific API keys. If you switch modes and users try to access ' .
        'enrolment instances from the previous mode, they will see a "Payment method not found, contact admin" error. ' .
        'This is normal behavior and instances will reappear when you switch back to the original mode.' .
        '</div>'
    ));

    if ($current_mode === 'test') {
        $settings->add(new admin_setting_heading(
            'enrol_stripepayment_test_keys',
            '🟢 ' . get_string('testapikeys', 'enrol_stripepayment'),
            '<div style="background-color: #e8f5e8; padding: 10px; border-left: 4px solid #4caf50; margin: 10px 0;">' .
            get_string('testapikeysdesc', 'enrol_stripepayment') .
            '</div>'
        ));

        $settings->add(new admin_setting_configtext(
            'enrol_stripepayment/testpublishablekey',
            get_string('testpublishablekey', 'enrol_stripepayment'),
            get_string('testpublishablekeydesc', 'enrol_stripepayment'),
            '',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configtext(
            'enrol_stripepayment/testsecretkey',
            get_string('testsecretkey', 'enrol_stripepayment'),
            get_string('testsecretkeydesc', 'enrol_stripepayment'),
            '',
            PARAM_TEXT
        ));
    } else {
        $settings->add(new admin_setting_heading(
            'enrol_stripepayment_live_keys',
            '🔴 ' . get_string('liveapikeys', 'enrol_stripepayment'),
            '<div style="background-color: #ffebee; padding: 10px; border-left: 4px solid #f44336; margin: 10px 0;">' .
            '<strong>⚠️ WARNING:</strong> ' . get_string('liveapikeysdesc', 'enrol_stripepayment') .
            '</div>'
        ));

        $settings->add(new admin_setting_configtext(
            'enrol_stripepayment/livepublishablekey',
            get_string('livepublishablekey', 'enrol_stripepayment'),
            get_string('livepublishablekeydesc', 'enrol_stripepayment'),
            '',
            PARAM_TEXT
        ));

        $settings->add(new admin_setting_configtext(
            'enrol_stripepayment/livesecretkey',
            get_string('livesecretkey', 'enrol_stripepayment'),
            get_string('livesecretkeydesc', 'enrol_stripepayment'),
            '',
            PARAM_TEXT
        ));
    }
    
    // Legacy fields are now auto-migrated and hidden from the interface
    // They are kept in the database for backward compatibility but not shown to users
    
    // Enhanced auto-migration logic for legacy keys
    $legacy_publishable = get_config('enrol_stripepayment', 'publishablekey');
    $legacy_secret = get_config('enrol_stripepayment', 'secretkey');
    $test_publishable = get_config('enrol_stripepayment', 'testpublishablekey');
    $test_secret = get_config('enrol_stripepayment', 'testsecretkey');
    $live_publishable = get_config('enrol_stripepayment', 'livepublishablekey');
    $live_secret = get_config('enrol_stripepayment', 'livesecretkey');
    $current_mode = get_config('enrol_stripepayment', 'stripemode');

    // Auto-migrate legacy keys if they exist and new keys are empty
    if (!empty($legacy_publishable) && !empty($legacy_secret)) {
        $migration_needed = false;

        // Check if we need to migrate
        if (empty($test_publishable) && empty($live_publishable) &&
            empty($test_secret) && empty($live_secret)) {
            $migration_needed = true;
        }

        // Auto-detect mode from legacy keys and migrate
        if ($migration_needed) {
            if (strpos($legacy_secret, 'sk_test_') === 0 && strpos($legacy_publishable, 'pk_test_') === 0) {
                set_config('testpublishablekey', $legacy_publishable, 'enrol_stripepayment');
                set_config('testsecretkey', $legacy_secret, 'enrol_stripepayment');
                set_config('stripemode', 'test', 'enrol_stripepayment');

                // Clear legacy keys after migration
                set_config('publishablekey', '', 'enrol_stripepayment');
                set_config('secretkey', '', 'enrol_stripepayment');

            } else if (strpos($legacy_secret, 'sk_live_') === 0 && strpos($legacy_publishable, 'pk_live_') === 0) {
                set_config('livepublishablekey', $legacy_publishable, 'enrol_stripepayment');
                set_config('livesecretkey', $legacy_secret, 'enrol_stripepayment');
                set_config('stripemode', 'live', 'enrol_stripepayment');

                // Clear legacy keys after migration
                set_config('publishablekey', '', 'enrol_stripepayment');
                set_config('secretkey', '', 'enrol_stripepayment');
            }
        }
    }
    
    $settings->add(new admin_setting_configcheckbox(
        'enrol_stripepayment/mailstudents',
        get_string('mailstudents', 'enrol_stripepayment'),
        '',
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'enrol_stripepayment/mailteachers',
        get_string('mailteachers', 'enrol_stripepayment'),
        '',
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'enrol_stripepayment/mailadmins',
        get_string('mailadmins', 'enrol_stripepayment'),
        '',
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'enrol_stripepayment/enablecouponsection',
        get_string('enablecouponsection', 'enrol_stripepayment'),
        '',
        0,
    ));

    // Variable $enroll button color.
    $settings->add( new admin_setting_configcolourpicker(
        'enrol_stripepayment/enrolbtncolor',
        get_string('enrolbtncolor', 'enrol_stripepayment'),
        get_string('enrolbtncolordes', 'enrol_stripepayment'),
        '#1177d1'
    ));
    // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
    // it describes what should happen when users are not supposed to be enrolled any more.
    $options = [
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    ];
    $settings->add(new admin_setting_configselect(
        'enrol_stripepayment/expiredaction',
        get_string('expiredaction', 'enrol_stripepayment'),
        get_string('expiredactionhelp', 'enrol_stripepayment'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES,
        $options
    ));

    // Webservice token.
    $webservicesoverview = $CFG->wwwroot . '/admin/search.php?query=enablewebservices';
    $restweblink = $CFG->wwwroot . '/admin/settings.php?section=webserviceprotocols';
    $createtoken = $CFG->wwwroot . '/admin/webservice/tokens.php';
    $settings->add(new admin_enrol_stripepayment_configtext(
        'enrol_stripepayment/webservice_token',
        get_string('webservicetokenstring', 'enrol_stripepayment'),
        get_string('enablewebservicesfirst', 'enrol_stripepayment') . '<a href="' . $webservicesoverview . '" target="_blank"> '
        . get_string('fromhere', 'enrol_stripepayment') . '</a> . '
        . get_string('createusertoken', 'enrol_stripepayment') . '<a href="' . $restweblink . '" target="_blank"> '
        . get_string('fromhere', 'enrol_stripepayment') . '</a> . '
        . get_string('enabledrestprotocol', 'enrol_stripepayment') . '<a href="' . $createtoken . '" target="_blank"> '
        . get_string('fromhere', 'enrol_stripepayment') . '</a>
        ',
        ''
    ));
    // Enrol instance defaults.
    $settings->add(new admin_setting_heading(
        'enrol_stripepayment_defaults',
        get_string('enrolinstancedefaults', 'admin'),
        get_string('enrolinstancedefaults_desc', 'admin')
    ));
    $options = [
        ENROL_INSTANCE_ENABLED  => get_string('yes'),
        ENROL_INSTANCE_DISABLED => get_string('no'),
    ];
    $settings->add(new admin_setting_configselect(
        'enrol_stripepayment/status',
        get_string('status', 'enrol_stripepayment'),
        get_string('status_desc', 'enrol_stripepayment'),
        ENROL_INSTANCE_DISABLED,
        $options
    ));
    $settings->add(new admin_setting_configtext(
        'enrol_stripepayment/cost',
        get_string('cost', 'enrol_stripepayment'),
        '',
        0,
        PARAM_FLOAT,
        4
    ));
    $stripecurrencies = enrol_get_plugin('stripepayment')->get_currencies();
    $settings->add(new admin_setting_configselect(
        'enrol_stripepayment/currency',
        get_string('currency', 'enrol_stripepayment'),
        '',
        'USD',
        $stripecurrencies
    ));
    $settings->add(new admin_setting_configtext(
        'enrol_stripepayment/maxenrolled',
        get_string('maxenrolled', 'enrol_stripepayment'),
        get_string('maxenrolledhelp', 'enrol_stripepayment'),
        0,
        PARAM_INT
    ));
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_stripepayment/roleid',
            get_string('defaultrole', 'enrol_stripepayment'),
            get_string('defaultroledesc', 'enrol_stripepayment'),
            $student->id,
            $options
        ));
    }
    $settings->add(new admin_setting_configduration(
        'enrol_stripepayment/enrolperiod',
        get_string('enrolperiod', 'enrol_stripepayment'),
        get_string('enrolperioddesc', 'enrol_stripepayment'),
        0
    ));
}