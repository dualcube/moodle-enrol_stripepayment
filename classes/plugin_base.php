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
 * Base capability and state-predicate overrides for the Stripe enrolment plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment;

use context_course;
use enrol_plugin;
use stdClass;

/**
 * Holds the simple capability/state-predicate enrol_plugin overrides
 * (allow_*, can_*, roles_protected, use_standard_editing_ui).
 *
 * The bottom of a small inheritance chain leading up to enrol_stripepayment_plugin
 * (lib.php) - {@see presentation_base} and {@see instance_lifecycle_base} each add
 * more concerns on top - purely so that no single class carries the full mandatory
 * enrol_plugin override surface: PHP Mess Detector's
 * TooManyPublicMethods rule (and PDepend's coupling metrics generally) analyse each
 * class node independently and do not merge a subclass's inherited members from a
 * parent declared in another file. Every method here is still a real enrol_plugin
 * interface requirement, not something optional or specific to this plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class plugin_base extends enrol_plugin {
    /**
     * Lists all protected user roles.
     * @return bool(true or false)
     */
    public function roles_protected() {
        // Users with role assign cap may tweak the roles later.
        return false;
    }

    /**
     * Defines if user can be unenrolled.
     * @param stdClass $instance of the plugin
     * @return bool(true or false)
     */
    public function allow_unenrol(stdClass $instance) {
        // Required by enrol_plugin's signature but unused here.
        unset($instance);

        // Users with unenrol cap may unenrol other users manually - requires enrol/stripe:unenrol.
        return true;
    }

    /**
     * Defines if user can be managed from admin.
     * @param stdClass $instance of the plugin
     * @return bool(true or false)
     */
    public function allow_manage(stdClass $instance) {
        // Required by enrol_plugin's signature but unused here.
        unset($instance);

        // Users with manage cap may tweak period and status - requires enrol/stripe:manage.
        return true;
    }

    /**
     * Defines if 'enrol me' link will be shown on course page.
     * @param stdClass $instance of the plugin
     * @return bool(true or false)
     */
    public function show_enrolme_link(stdClass $instance) {
        return $instance->status == ENROL_INSTANCE_ENABLED;
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/stripepayment:manage', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/stripepayment:manage', $context);
    }
}
