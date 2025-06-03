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
 * Course wise edit settings.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once('edit_form.php');

$courseid   = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT); // Instanceid.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);
require_login($course);
require_capability('enrol/stripepayment:config', $context);
$PAGE->set_url('/enrol/stripepayment/edit.php', ['courseid' => $course->id, 'id' => $instanceid]);
$PAGE->set_pagelayout('admin');
$return = new moodle_url('/enrol/instances.php', ['id' => $course->id]);
if (!enrol_is_enabled('stripepayment')) {
    redirect($return);
}
$plugin = enrol_get_plugin('stripepayment');

if ($instanceid) {
    $instance = $DB->get_record(
        'enrol',
        ['courseid' => $course->id, 'enrol' => 'stripepayment', 'id' => $instanceid],
        '*',
        MUST_EXIST
    );
    $instance->cost = format_float($instance->cost, 2, true);
} else {
    require_capability('moodle/course:enrolconfig', $context);
    // No instance yet, we have to add new instance.
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', ['id' => $course->id]));
    $instance = new stdClass();
    $instance->id       = null;
    $instance->courseid = $course->id;
}
$mform = new enrol_stripepayment_edit_form(null, [$instance, $plugin, $context]);
if ($mform->is_cancelled()) {
    redirect($return);
} else if ($data = $mform->get_data()) {

    // when we edit the stripe of a code then update all feild.
    if ($instance->id) {
        $reset = ($instance->status != $data->status);
        $instance->status         = $data->status;
        $instance->name           = $data->name;
        $instance->cost           = unformat_float($data->cost);
        $instance->roleid         = $data->roleid;
        $instance->customint1     = $data->customint1;
        $instance->customint3     = $data->customint3; // Max enrolled user number
        $instance->customtext1    = $data->customtext1;
        $instance->enrolperiod    = $data->enrolperiod;
        $instance->enrolstartdate = $data->enrolstartdate;
        $instance->enrolenddate   = $data->enrolenddate;
        $instance->timemodified   = time();
        $instance->currency   = $data->currency;
        $DB->update_record('enrol', $instance);
        if ($reset) {
            $context->mark_dirty();
        }
    } else {
        // for first time create the instance of the plugin for the course.
        $fields = [
            'status' => $data->status,
            'name' => $data->name,
            'cost' => unformat_float($data->cost),
            'roleid' => $data->roleid,
            'enrolperiod' => $data->enrolperiod,
            'customint3' => $data->customint3,
            'enrolstartdate' => $data->enrolstartdate,
            'enrolenddate' => $data->enrolenddate,
            'currency'   => $data->currency,
        ];
        $plugin->add_instance($course, $fields);
    }
    redirect($return);
}
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('pluginname', 'enrol_stripepayment'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'enrol_stripepayment'));
$mform->display();
echo $OUTPUT->footer();
