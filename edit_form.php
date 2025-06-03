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
 * Course wise edit form.
 *
 * Adds new instance of enrol_stripepayment to specified course
 * or edits current instance.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir.'/formslib.php');
require_once('lib.php');
/**
 * Sets up moodle edit form class methods.
 * @copyright  2019 Dualcube Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_stripepayment_edit_form extends moodleform {
    /**
     * Sets up moodle form.
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        list($instance, $plugin, $context) = $this->_customdata;
        $mform->addElement('header', 'header', get_string('pluginname', 'enrol_stripepayment'));
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);
        $options = [ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no')];
        $mform->addElement('select', 'status', get_string('status', 'enrol_stripepayment'), $options);
        $mform->setDefault('status', $plugin->get_config('status'));
        $costarray = [];
        $costarray[] =& $mform->createElement('text', 'cost', get_string('cost', 'enrol_stripepayment'), ['size' => 4]);
        $mform->setDefault('cost', format_float($plugin->get_config('cost'), 2, true));
        $mform->setType('cost', PARAM_FLOAT);
        $mform->addGroup($costarray, 'costar', get_string('cost', 'enrol_stripepayment'), [' '], false);
        // Currency select.
        $currency = enrol_get_plugin('stripepayment')->get_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_stripepayment'), $currency);
        $mform->setDefault('currency', $plugin->get_config('currency'));
        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $plugin->get_config('roleid'));
        }
        // Assign role.
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_stripepayment'), $roles);
        $mform->setDefault('roleid', $plugin->get_config('roleid'));
        $mform->addElement('text', 'customint3', get_string('maxenrolled', 'enrol_stripepayment'));
        $mform->setDefault('maxenrolled', 'customint3');
        $mform->addHelpButton('customint3', 'maxenrolled', 'enrol_stripepayment');
        $mform->setType('customint3', PARAM_INT);
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_stripepayment'),
        ['optional' => true, 'defaultunit' => 86400]);
        $mform->setDefault('enrolperiod', $plugin->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_stripepayment');
        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_stripepayment'),
        ['optional' => true]);
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_stripepayment');
        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_stripepayment'),
        ['optional' => true]);
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_stripepayment');
 
        // Course welcome message.
        if (has_any_capability(['enrol/stripepayment:config', 'moodle/course:editcoursewelcomemessage'], $context)) {
            $mform->addElement(
                'select',
                'customint1',
                get_string(
                    identifier: 'sendcoursewelcomemessage',
                    component: 'core_enrol',
                ),
                enrol_send_welcome_email_options(),
            );
            $mform->addHelpButton(
                elementname: 'customint1',
                identifier: 'sendcoursewelcomemessage',
                component: 'core_enrol',
            );

            $options = [
                'cols' => '60',
                'rows' => '8',
            ];
            $mform->addElement(
                'textarea',
                'customtext1',
                get_string(
                    identifier: 'customwelcomemessage',
                    component: 'core_enrol',
                ),
                $options,
            );
            $mform->setDefault('customtext1', get_string('customwelcomemessageplaceholder', 'core_enrol'));
            $mform->hideIf(
                elementname: 'customtext1',
                dependenton: 'customint1',
                condition: 'eq',
                value: ENROL_DO_NOT_SEND_EMAIL,
            );

            // Static form elements cannot be hidden by hideIf() so we need to add a dummy group.
            // See: https://tracker.moodle.org/browse/MDL-66251.
            $group[] = $mform->createElement(
                'static',
                'customwelcomemessage_extra_help',
                null,
                get_string(
                    identifier: 'customwelcomemessage_help',
                    component: 'core_enrol',
                ),
            );
            $mform->addGroup($group, 'group_customwelcomemessage_extra_help', '', ' ', false);
            $mform->hideIf(
                elementname: 'group_customwelcomemessage_extra_help',
                dependenton: 'customint1',
                condition: 'eq',
                value: ENROL_DO_NOT_SEND_EMAIL,
            );
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        if (enrol_accessing_via_instance($instance)) {
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'),
            get_string('instanceeditselfwarningtext', 'core_enrol'));
        }
        $this->add_action_buttons(true, ($instance->id ? null : get_string('addinstance', 'enrol')));
        $this->set_data($instance);
    }
    /**
     * Sets up moodle form validation.
     * @param stdClass $data
     * @param stdClass $files
     * @return $error error list
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['enrolenddate']) && $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_stripepayment');
        }
        $cost = str_replace(get_string('decsep', 'langconfig'), '.', $data['cost']);
        if (!is_numeric($cost)) {
            $errors['cost'] = get_string('costerror', 'enrol_stripepayment');
        }
        return $errors;
    }
}
