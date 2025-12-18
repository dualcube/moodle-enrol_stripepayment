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
 * Privacy Subsystem implementation for enrol_stripepayment.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @category   privacy
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// phpcs:disable Universal.OOStructures.AlphabeticExtendsImplements.ImplementsWrongOrder
namespace enrol_stripepayment\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for enrol_stripepayment.
 *
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {
    // phpcs:enable
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link(
            'stripe.com',
            [
                'email' => 'privacy:metadata:enrol_stripepayment:stripe_com:email',
            ],
            'privacy:metadata:enrol_stripepayment:stripe_com'
        );

        // The enrol_stripepayment has a DB table that contains user data.
        $collection->add_database_table(
            'enrol_stripepayment',
            [
                'customeremail'      => 'privacy:metadata:enrol_stripepayment:customeremail',
                'customerid'         => 'privacy:metadata:enrol_stripepayment:customerid',
                'itemname'           => 'privacy:metadata:enrol_stripepayment:itemname',
                'courseid'           => 'privacy:metadata:enrol_stripepayment:courseid',
                'userid'             => 'privacy:metadata:enrol_stripepayment:userid',
                'instanceid'         => 'privacy:metadata:enrol_stripepayment:instanceid',
                'couponid'           => 'privacy:metadata:enrol_stripepayment:couponid',
                'memo'               => 'privacy:metadata:enrol_stripepayment:memo',
                'price'              => 'privacy:metadata:enrol_stripepayment:price',
                'paymentstatus'      => 'privacy:metadata:enrol_stripepayment:paymentstatus',
                'pendingreason'      => 'privacy:metadata:enrol_stripepayment:pendingreason',
                'reasoncode'         => 'privacy:metadata:enrol_stripepayment:reasoncode',
                'txnid'              => 'privacy:metadata:enrol_stripepayment:txnid',
                'paymenttype'        => 'privacy:metadata:enrol_stripepayment:paymenttype',
                'timeupdated'        => 'privacy:metadata:enrol_stripepayment:timeupdated',
            ],
            'privacy:metadata:enrol_stripepayment'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {enrol_stripepayment} esp
                  JOIN {enrol} e ON esp.instanceid = e.id
                  JOIN {context} ctx ON e.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
                  JOIN {user} u ON u.id = esp.userid OR LOWER(u.email) = esp.customeremail
                 WHERE u.id = :userid";
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'userid'        => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $sql = "SELECT u.id
                  FROM {enrol_stripepayment} esp
                  JOIN {enrol} e ON esp.instanceid = e.id
                  JOIN {user} u ON esp.userid = u.id OR LOWER(u.email) = esp.customeremail
                 WHERE e.courseid = :courseid";
        $params = ['courseid' => $context->instanceid];

        $userlist->add_from_sql('id', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT esp.*
                  FROM {enrol_stripepayment} esp
                  JOIN {enrol} e ON esp.instanceid = e.id
                  JOIN {context} ctx ON e.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
                  JOIN {user} u ON u.id = esp.userid OR LOWER(u.email) = esp.customeremail
                 WHERE ctx.id {$contextsql} AND u.id = :userid
              ORDER BY e.courseid";

        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'userid'        => $user->id,
        ];
        $params += $contextparams;

        // Reference to the course seen in the last iteration of the loop. By comparing this with the current record, and
        // because we know the results are ordered, we know when we've moved to the Stripe transactions for a new course
        // and therefore when we can export the complete data for the last course.
        $lastcourseid = null;

        $strtransactions = get_string('transactions', 'enrol_stripepayment');
        $transactions = [];
        $records = $DB->get_recordset_sql($sql, $params);
        foreach ($records as $record) {
            if ($lastcourseid != $record->courseid) {
                if (!empty($transactions)) {
                    $coursecontext = \context_course::instance($lastcourseid);
                    writer::with_context($coursecontext)->export_data(
                        [$strtransactions],
                        (object) ['transactions' => $transactions]
                    );
                }
                $transactions = [];
            }

            $transaction = (object) [
                'customeremail'         => $record->customeremail,
                'customerid'            => $record->customerid,
                'itemname'              => $record->itemname,
                'userid'                => $record->userid,
                'couponid'              => $record->couponid,
                'memo'                  => $record->memo,
                'price'                 => $record->price,
                'paymentstatus'         => $record->paymentstatus,
                'pendingreason'         => $record->pendingreason,
                'reasoncode'            => $record->reasoncode,
                'txnid'                 => $record->txnid,
                'paymenttype'           => $record->paymenttype,
                'timeupdated'           => \core_privacy\local\request\transform::datetime($record->timeupdated),
            ];

            $transactions[] = $transaction;
            $lastcourseid = $record->courseid;
        }
        $records->close();

        // The data for the last course won't have been written yet, so make sure to write it now!
        if (!empty($transactions)) {
            $coursecontext = \context_course::instance($lastcourseid);
            writer::with_context($coursecontext)->export_data(
                [$strtransactions],
                (object) ['transactions' => $transactions]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        $DB->delete_records('enrol_stripepayment', ['courseid' => $context->instanceid]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        $contexts = $contextlist->get_contexts();
        $courseids = [];
        foreach ($contexts as $context) {
            if ($context instanceof \context_course) {
                $courseids[] = $context->instanceid;
            }
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $select = "userid = :userid AND courseid $insql";
        $params = $inparams + ['userid' => $user->id];
        $DB->delete_records_select('enrol_stripepayment', $select, $params);

        // We do not want to delete the payment record when the user is just the receiver of payment.
        // In that case, we just delete the receiver's info from the transaction record.

        $select = "customeremail = :customeremail AND courseid $insql";
        $params = $inparams + ['customeremail' => \core_text::strtolower($user->email)];
        $DB->set_field_select('enrol_stripepayment', 'customeremail', '', $select, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist       $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $userids = $userlist->get_userids();

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $params = ['courseid' => $context->instanceid] + $userparams;

        $select = "courseid = :courseid AND userid $usersql";
        $DB->delete_records_select('enrol_stripepayment', $select, $params);

        // We do not want to delete the payment record when the user is just the receiver of payment.
        // In that case, we just delete the receiver's info from the transaction record.

        $select = "courseid = :courseid AND customeremail IN (SELECT LOWER(email) FROM {user} WHERE id $usersql)";
        $DB->set_field_select('enrol_stripepayment', 'customeremail', '', $select, $params);
    }
}
