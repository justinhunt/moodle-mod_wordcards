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
 * Privacy Subsystem implementation for mod_wordcards.
 *
 * @package    mod_minilesson
 * @copyright  2018 Justin Hunt https://poodll.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_wordcards\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
// use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_wordcards\constants;

defined('MOODLE_INTERNAL') || die();

// 3.3 user_provider not backported so we use this switch to avoid errors when using same codebase for 3.3 and higher
if (interface_exists('\core_privacy\local\request\core_userlist_provider')) {
    interface the_user_provider extends \core_privacy\local\request\core_userlist_provider {
    }
} else {
    interface the_user_provider {
    };
}

/**
 * Privacy Subsystem for mod_wordcards
 *
 * @copyright  2024 Justin Hunt https://poodll.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin does store personal user data.
    \core_privacy\local\metadata\provider,
    // This plugin is a core_user_data_provider.
    \core_privacy\local\request\plugin\provider,
    // user provider 3.4 and above
    the_user_provider {

    use \core_privacy\local\legacy_polyfill;

    /**
     * Return meta data about this plugin.
     *
     * @param  collection $collection A list of information to add to.
     * @return collection Return the collection after adding to it.
     */
    public static function _get_metadata(collection $collection) {

        $details = [
            'id' => 'privacy:metadata:attemptid',
            'modid' => 'privacy:metadata:modid',
            'userid' => 'privacy:metadata:userid',
            'grade1' => 'privacy:metadata:grade1',
            'grade2' => 'privacy:metadata:grade2',
            'grade3' => 'privacy:metadata:grade3',
            'grade4' => 'privacy:metadata:grade4',
            'grade5' => 'privacy:metadata:grade5',
            'totalgrade' => 'privacy:metadata:totalgrade',
            'timecreated' => 'privacy:metadata:timecreated',
        ];
        $collection->add_database_table(constants::M_ATTEMPTSTABLE, $details, 'privacy:metadata:attempttable');

        $details = [
            'userid' => 'privacy:metadata:userid',
            'termid' => 'privacy:metadata:termid',
            'timecreated' => 'privacy:metadata:timecreated',
        ];
        $collection->add_database_table(constants::M_SEENTABLE , $details, 'privacy:metadata:seentable');

        $details = [
            'userid' => 'privacy:metadata:userid',
            'termid' => 'privacy:metadata:termid',
            'lastfail' => 'privacy:metadata:lastfail',
            'lastsuccess' => 'privacy:metadata:lastsuccess',
            'failcount' => 'privacy:metadata:failcount',
            'successcount' => 'privacy:metadata:successcount',
            'timecreated' => 'privacy:metadata:timecreated',
        ];
        $collection->add_database_table(constants::M_ASSOCTABLE , $details, 'privacy:metadata:associationstable');

        $details = [
            'userid' => 'privacy:metadata:userid',
            'termid' => 'privacy:metadata:termid',
            'timemoodified' => 'privacy:metadata:timemodified',
        ];
        $collection->add_database_table(constants::M_MYWORDSTABLE , $details, 'privacy:metadata:mywordstable');

        return $collection;
    }


    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist the list of contexts containing user info for the user.
     */
    public static function _get_contexts_for_userid($userid) {

        $sql = "SELECT c.id
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {" . constants::M_TABLE . "} actt ON actt.id = cm.instance
            INNER JOIN {" . constants::M_ATTEMPTSTABLE . "} usert ON usert.modid = actt.id
                 WHERE usert.userid = :theuserid";
        $params = [
                'contextlevel' => CONTEXT_MODULE,
                'modname' => constants::M_MODNAME,
                'theuserid' => $userid,
            ];

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     *
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        // Find users with wordcards attempts
        $sql = "SELECT usert.userid
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN  {" . constants::M_TABLE . "} actt ON actt.id = cm.instance
                  JOIN {" . constants::M_ATTEMPTSTABLE . "} usert ON usert.modid = actt.id
                 WHERE c.id = :contextid";

        $params = [
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_MODULE,
            'modname' => constants::M_MODNAME,
        ];

        $userlist->add_from_sql('userid', $sql, $params);

    }

    /**
     * Export personal data for the given approved_contextlist.
     *
     * User and context information is contained within the contextlist.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function _export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();
        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        // Get term data.
        $termdatabycontextid = self::fetch_export_term_data($user, $contextsql, $contextparams);

        // Get attempt data
        $attempdatabycontextid = self::fetch_export_attempt_data($user, $contextsql, $contextparams);

        foreach ($attempdatabycontextid as $thecmid => $theattempts) {

                $context = \context_module::instance($thecmid);
            if (array_key_exists($thecmid, $termdatabycontextid)) {
                $termdata = $termdatabycontextid[$thecmid];
            } else {
                $termdata = [];
            }
            self::export_attempt_data_for_user($theattempts, $termdata,  $context, $user);
        }

    }

    protected static function fetch_export_attempt_data($user, $contextsql, $contextparams) {
        global $DB;

        // Get attempt data.
        $sql = "SELECT usert.id as attemptid,
            cm.id AS cmid,
            usert.userid AS userid,
            usert.grade1,
            usert.grade2,
            usert.grade3,
            usert.grade4,
            usert.grade5,
            usert.totalgrade,
            usert.timecreated
        FROM {" . constants::M_ATTEMPTSTABLE . "} usert
        JOIN {" . constants::M_TABLE . "} actt ON usert.modid = actt.id
        JOIN {course_modules} cm ON actt.id = cm.instance
        JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
        JOIN {context} c ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
        WHERE c.id {$contextsql}
        AND usert.userid = :userid
        ORDER BY usert.id, cm.id";
        $params = [
            'userid' => $user->id,
        'modulename' => constants::M_MODNAME,
        'contextlevel' => CONTEXT_MODULE,
        ] + $contextparams;

        $attempts = $DB->get_recordset_sql($sql, $params);
        $allcontextdata = [];

        foreach ($attempts as $attempt) {
            $attempt->timecreated = \core_privacy\local\request\transform::datetime($attempt->timecreated);

            if (!array_key_exists($attempt->cmid, $allcontextdata)) {
                $allcontextdata[$attempt->cmid] = ['attempts' => []];
            }
            $allcontextdata[$attempt->cmid]['attempts'][] = get_object_vars($attempt);

        }
        $attempts->close();

        return $allcontextdata;
    }

    protected static function fetch_export_term_data($user, $contextsql, $contextparams) {
        global $DB;

        // Export term data.
        $sql = "SELECT seent.id as seenid,
        cm.id AS cmid,
        seent.userid AS userid,
        termt.term,
        assoct.lastfail,
        assoct.lastsuccess,
        CASE WHEN assoct.failcount IS NULL THEN 0 ELSE assoct.failcount END AS failcount,
        CASE WHEN assoct.successcount IS NULL THEN 0 ELSE assoct.successcount END AS successcount,
        CASE WHEN mywordt.id IS NULL THEN 'no' ELSE 'yes' END AS mywords
        FROM {" . constants::M_SEENTABLE . "} seent
        INNER JOIN {" . constants::M_TERMSTABLE . "} termt ON  termt.id = seent.termid
        LEFT JOIN {" . constants::M_ASSOCTABLE . "} assoct ON assoct.termid = termt.id
        LEFT JOIN {" . constants::M_MYWORDSTABLE . "} mywordt ON mywordt.termid = termt.id
        JOIN {course_modules} cm ON termt.modid = cm.instance
        JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
        JOIN {context} c ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
        WHERE c.id {$contextsql}
            AND seent.userid = :userid
        ORDER BY seent.id, cm.id";

        $params = [
                'userid' => $user->id,
                'modulename' => constants::M_MODNAME,
                'contextlevel' => CONTEXT_MODULE,
            ] + $contextparams;

        $allterms = $DB->get_recordset_sql($sql, $params);
        $allcontextdata = [];

        foreach ($allterms as $oneterm) {
            if ($oneterm->lastfail) {
                $oneterm->lastfail = \core_privacy\local\request\transform::datetime($oneterm->lastfail);
            } else {
                $oneterm->lastfail = get_string('never', constants::M_COMPONENT);
            }

            if ($oneterm->lastsuccess) {
                $oneterm->lastsuccess = \core_privacy\local\request\transform::datetime($oneterm->lastsuccess);
            } else {
                $oneterm->lastsuccess = get_string('never', constants::M_COMPONENT);
            }
            if (!array_key_exists($oneterm->cmid, $allcontextdata)) {
                $allcontextdata[$oneterm->cmid] = ['terms' => []];
            }
            $allcontextdata[$oneterm->cmid]['terms'][] = get_object_vars($oneterm);
        }
        $allterms->close();

        return $allcontextdata;
    }

    /**
     * Export the supplied personal data for a single wordcards attempt along with any generic data or area files.
     *
     * @param array $attemptdata the personal data to export
     * @param \context_module $context the context of the minilesson.
     * @param \stdClass $user the user record
     */
    protected static function export_attempt_data_for_user(array $attemptdata, array $termdata, \context_module $context, \stdClass $user) {
        // Fetch the generic module data for the wordcards activity.
        $contextdata = helper::get_context_data($context, $user);

        // Merge with attempt data and write it.
        $contextdata = (object)array_merge((array)$contextdata, $attemptdata, $termdata);
        writer::with_context($context)->export_data([], $contextdata);

        // Write file data (probably none)
        helper::export_context_files($context, $user);
    }

     /**
      * Export the supplied personal data for a single wordcards attempt along with any generic data or area files.
      *
      * @param array $attemptdata the personal data to export
      * @param \context_module $context the context of the minilesson.
      * @param \stdClass $user the user record
      */
    protected static function export_term_data_for_user(array $termdata, \context_module $context, \stdClass $user) {
        // Fetch the generic module data for the wordcards activity.
        $contextdata = helper::get_context_data($context, $user);

        // Merge with term data and write it.
        $contextdata = (object)array_merge((array)$contextdata, $termdata);
        writer::with_context($context)->export_data([], $contextdata);
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        if (!$cm = get_coursemodule_from_id(constants::M_MODNAME, $context->instanceid)) {
            return;
        }

        $instanceid = $cm->instance;

        // Delete attempt data.
        $attempts = $DB->get_records(constants::M_ATTEMPTSTABLE, ['modid' => $instanceid], '', 'id');
        $DB->delete_records(constants::M_ATTEMPTSTABLE, ['modid' => $instanceid]);

        // Delete all term data.
        $termids = $DB->get_fieldset(constants::M_TERMSTABLE, 'id', ['modid' => $instanceid]);
        if ($termids) {
            foreach ($termids as $termid) {
                $DB->delete_records(constants::M_MYWORDSTABLE, ['termid' => $termid]);
                $DB->delete_records(constants::M_ASSOCTABLE, ['termid' => $termid]);
                $DB->delete_records(constants::M_SEENTABLE, ['termid' => $termid]);
            }
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_MODULE) {

                $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);

                $entries = $DB->get_records(constants::M_ATTEMPTSTABLE, ['modid' => $instanceid, 'userid' => $userid],
                    '', 'id');

                if (!$entries) {
                    continue;
                }

                list($insql, $inparams) = $DB->get_in_or_equal(array_keys($entries), SQL_PARAMS_NAMED);

                // Now delete all user related entries.
                $DB->delete_records(constants::M_ATTEMPTSTABLE, ['modid' => $instanceid, 'userid' => $userid]);

                // Delete all term data.
                $termids = $DB->get_fieldset(constants::M_TERMSTABLE, 'id', ['modid' => $instanceid]);
                if ($termids) {
                    foreach ($termids as $termid) {
                        $DB->delete_records(constants::M_MYWORDSTABLE, ['termid' => $termid, 'userid' => $userid]);
                        $DB->delete_records(constants::M_ASSOCTABLE, ['termid' => $termid, 'userid' => $userid]);
                        $DB->delete_records(constants::M_SEENTABLE, ['termid' => $termid, 'userid' => $userid]);
                    }
                }
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param   approved_userlist    $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        $instanceid = $DB->get_field('course_modules', 'instance', ['id' => $context->instanceid], MUST_EXIST);
        list($userinsql, $userinparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $userinstanceparams = $userinparams + ['instanceid' => $instanceid];

        // Now delete all attempts.
        $attemptdeletewhere = "modid = :instanceid AND userid {$userinsql}";
        $DB->delete_records_select(constants::M_ATTEMPTSTABLE, $attemptdeletewhere, $userinstanceparams);

        // Delete all term data.
        $termids = $DB->get_fieldset(constants::M_TERMSTABLE, 'id', ['modid' => $instanceid]);
        $termdeletewhere = "termid = :termid AND userid {$userinsql}";

        if ($termids) {
            foreach ($termids as $termid) {
                $usertermparams = $userinparams + ['termid' => $termid];
                $DB->delete_records_select(constants::M_MYWORDSTABLE, $termdeletewhere, $usertermparams);
                $DB->delete_records_select(constants::M_ASSOCTABLE, $termdeletewhere, $usertermparams);
                $DB->delete_records_select(constants::M_SEENTABLE, $termdeletewhere, $usertermparams);
            }
        }
    }
}
