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
 * Stripe enrolment plugin upgrade script.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute the plugin upgrade steps from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_enrol_stripepayment_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025071807) {
        // Remove legacy fields that are not used by Stripe payment processing.
        $table = new xmldb_table('enrol_stripepayment');

        // Remove business field.
        $field = new xmldb_field('business');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Rename tax field to price.
        $field = new xmldb_field('tax', XMLDB_TYPE_CHAR, '255', null, false, false);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'price');
        }

        // Remove option_name1 field.
        $field = new xmldb_field('option_name1');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Remove option_selection1_x field.
        $field = new xmldb_field('option_selection1_x');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Remove option_name2 field.
        $field = new xmldb_field('option_name2');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Remove option_selection2_x field.
        $field = new xmldb_field('option_selection2_x');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Remove parent_txn_id field.
        $field = new xmldb_field('parent_txn_id');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        // Stripe savepoint reached.
        upgrade_plugin_savepoint(true, 2025071807, 'enrol', 'stripepayment');
    }

    return true;
}
