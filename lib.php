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
 * Stripe enrolment plugin.
 *
 * This plugin allows you to set up paid courses.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use enrol_stripepayment\enrol_lifecycle_trait;
use enrol_stripepayment\enrol_navigation_trait;
use enrol_stripepayment\instance_form_trait;
use enrol_stripepayment\payment_page_trait;

/**
 * Stripe enrolment plugin implementation.
 *
 * enrol_plugin's contract mandates most of this class's behaviour be public
 * overrides, each tied to a different core type (forms, navigation,
 * backup/restore, cron, course_enrolment_manager, ...). The method bodies
 * live in per-concern traits under classes/ (navigation/icons, the course-page
 * checkout, the edit-instance form, and backup/cron) purely to keep this file
 * from being one huge one; every trait is used only by this class.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_stripepayment_plugin extends enrol_plugin {
    use enrol_navigation_trait;
    use payment_page_trait;
    use instance_form_trait;
    use enrol_lifecycle_trait;
}
