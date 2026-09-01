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

    if ($oldversion < 2025082100) {
        xmldb_enrol_stripepayment_upgrade_to_2025082100($dbman);
    }

    if ($oldversion < 2025082108) {
        xmldb_enrol_stripepayment_upgrade_to_2025082108($dbman);
    }

    return true;
}

/**
 * Upgrade step 2025082100: drop legacy fields unused by Stripe payment processing,
 * rename the rest to the plugin's current naming, and migrate legacy API keys.
 *
 * @param database_manager $dbman
 */
function xmldb_enrol_stripepayment_upgrade_to_2025082100($dbman) {
    $table = new xmldb_table('enrol_stripepayment');

    xmldb_enrol_stripepayment_upgrade_drop_field($dbman, $table, 'business');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'tax', 'price');
    xmldb_enrol_stripepayment_upgrade_drop_field($dbman, $table, 'option_name1');
    xmldb_enrol_stripepayment_upgrade_drop_field($dbman, $table, 'option_selection1_x');
    xmldb_enrol_stripepayment_upgrade_drop_field($dbman, $table, 'option_name2');
    xmldb_enrol_stripepayment_upgrade_drop_field($dbman, $table, 'option_selection2_x');
    xmldb_enrol_stripepayment_upgrade_drop_field($dbman, $table, 'parent_txn_id');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'receiver_email', 'receiveremail');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'receiver_id', 'receiverid');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'item_name', 'itemname');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'coupon_id', 'couponid');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'payment_status', 'paymentstatus');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'pending_reason', 'pendingreason');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'reason_code', 'reasoncode', XMLDB_TYPE_CHAR, '30');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'txn_id', 'txnid');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'payment_type', 'paymenttype', XMLDB_TYPE_CHAR, '30');

    xmldb_enrol_stripepayment_migrate_legacy_keys();

    // Stripe savepoint reached.
    upgrade_plugin_savepoint(true, 2025082100, 'enrol', 'stripepayment');
}

/**
 * Upgrade step 2025082108: rename the receiver_* fields to customer_*.
 *
 * @param database_manager $dbman
 */
function xmldb_enrol_stripepayment_upgrade_to_2025082108($dbman) {
    $table = new xmldb_table('enrol_stripepayment');

    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'receiverid', 'customerid');
    xmldb_enrol_stripepayment_upgrade_rename_field($dbman, $table, 'receiveremail', 'customeremail');

    upgrade_plugin_savepoint(true, 2025082108, 'enrol', 'stripepayment');
}

/**
 * Drop a field from a table if it currently exists.
 *
 * @param database_manager $dbman
 * @param xmldb_table $table
 * @param string $fieldname
 */
function xmldb_enrol_stripepayment_upgrade_drop_field($dbman, $table, $fieldname) {
    $field = new xmldb_field($fieldname);
    if ($dbman->field_exists($table, $field)) {
        $dbman->drop_field($table, $field);
    }
}

/**
 * Rename a field on a table if it currently exists.
 *
 * @param database_manager $dbman
 * @param xmldb_table $table
 * @param string $oldname
 * @param string $newname
 * @param string $type
 * @param string $length
 */
function xmldb_enrol_stripepayment_upgrade_rename_field(
    $dbman,
    $table,
    $oldname,
    $newname,
    $type = XMLDB_TYPE_CHAR,
    $length = '255'
) {
    $field = new xmldb_field($oldname, $type, $length, null, false, false);
    if ($dbman->field_exists($table, $field)) {
        $dbman->rename_field($table, $field, $newname);
    }
}

/**
 * Auto-migrate legacy single-mode API keys (publishablekey/secretkey) into the
 * test/live key pair, inferring the mode from the secret key's prefix.
 */
function xmldb_enrol_stripepayment_migrate_legacy_keys() {
    $legacypublishable = get_config('enrol_stripepayment', 'publishablekey');
    $legacysecret = get_config('enrol_stripepayment', 'secretkey');

    if (empty($legacypublishable) || empty($legacysecret)) {
        return;
    }

    if (strpos($legacysecret, 'sk_test_') === 0 && strpos($legacypublishable, 'pk_test_') === 0) {
        set_config('testpublishablekey', $legacypublishable, 'enrol_stripepayment');
        set_config('testsecretkey', $legacysecret, 'enrol_stripepayment');
        set_config('stripemode', 'test', 'enrol_stripepayment');
    } else if (strpos($legacysecret, 'sk_live_') === 0 && strpos($legacypublishable, 'pk_live_') === 0) {
        set_config('livepublishablekey', $legacypublishable, 'enrol_stripepayment');
        set_config('livesecretkey', $legacysecret, 'enrol_stripepayment');
        set_config('stripemode', 'live', 'enrol_stripepayment');
    } else {
        // Neither prefix matched (unexpected key format) - leave the legacy keys alone.
        return;
    }

    // Clear legacy keys now that they have been migrated into the mode-specific pair.
    set_config('publishablekey', '', 'enrol_stripepayment');
    set_config('secretkey', '', 'enrol_stripepayment');
}
