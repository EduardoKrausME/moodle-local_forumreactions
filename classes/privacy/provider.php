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
 * Privacy API provider for forum reactions.
 *
 * @package   local_forumreactions
 * @copyright 2026 Forum reactions contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumreactions\privacy;

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\content_writer;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\userlist;
use local_forumreactions\reaction_manager;

/**
 * Provides metadata, export and deletion support for the Moodle Privacy API.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    plugin_provider,
    core_userlist_provider {

    /**
     * Describe stored personal data.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            reaction_manager::TABLE,
            [
                "forumid" => "privacy:metadata:reaction:forumid",
                "discussionid" => "privacy:metadata:reaction:discussionid",
                "postid" => "privacy:metadata:reaction:postid",
                "userid" => "privacy:metadata:reaction:userid",
                "reaction" => "privacy:metadata:reaction:reaction",
                "timecreated" => "privacy:metadata:reaction:timecreated",
            ],
            "privacy:metadata:reaction"
        );
        return $collection;
    }

    /**
     * Return contexts containing reaction data for a user.
     *
     * @param int $userid User id.
     * @return contextlist Context list.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT DISTINCT ctx.id
                  FROM {" . reaction_manager::TABLE . "} r
                  JOIN {forum} f ON f.id = r.forumid
                  JOIN {modules} m ON m.name = :modname
                  JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = f.id
                  JOIN {context} ctx ON ctx.contextlevel = :contextlevel AND ctx.instanceid = cm.id
                 WHERE r.userid = :userid";
        $contextlist->add_from_sql($sql, [
            "modname" => "forum",
            "contextlevel" => CONTEXT_MODULE,
            "userid" => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export reaction data for approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $forumid = self::get_forumid_from_context($context);
            if (!$forumid) {
                continue;
            }

            $records = $DB->get_records(reaction_manager::TABLE, [
                "forumid" => $forumid,
                "userid" => $userid,
            ], "timecreated ASC");

            if (!$records) {
                continue;
            }

            $export = [];
            foreach ($records as $record) {
                $post = $DB->get_record("forum_posts", ["id" => $record->postid], "id, subject", IGNORE_MISSING);
                $export[] = (object)[
                    "postid" => (int)$record->postid,
                    "postsubject" => $post ? $post->subject : "",
                    "reaction" => $record->reaction,
                    "timecreated" => userdate($record->timecreated),
                ];
            }

            content_writer::with_context($context)->export_data(
                [get_string("privacy:export:path", "local_forumreactions")],
                (object)["reactions" => $export]
            );
        }
    }

    /**
     * Delete all reaction data in a context.
     *
     * @param context $context Context to delete.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;

        $forumid = self::get_forumid_from_context($context);
        if ($forumid) {
            $DB->delete_records(reaction_manager::TABLE, ["forumid" => $forumid]);
        }
    }

    /**
     * Delete one user reaction data in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $forumid = self::get_forumid_from_context($context);
            if ($forumid) {
                $DB->delete_records(reaction_manager::TABLE, [
                    "forumid" => $forumid,
                    "userid" => $userid,
                ]);
            }
        }
    }

    /**
     * Add users with reaction data in the supplied context.
     *
     * @param userlist $userlist User list to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        $forumid = self::get_forumid_from_context($context);
        if (!$forumid) {
            return;
        }

        $sql = "SELECT userid
                  FROM {" . reaction_manager::TABLE . "}
                 WHERE forumid = :forumid";
        $userlist->add_from_sql("userid", $sql, ["forumid" => $forumid]);
    }

    /**
     * Delete reaction data for approved users in a context.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $forumid = self::get_forumid_from_context($userlist->get_context());
        $userids = $userlist->get_userids();
        if (!$forumid || empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, "userid");
        $params["forumid"] = $forumid;
        $DB->delete_records_select(
            reaction_manager::TABLE,
            "forumid = :forumid AND userid {$insql}",
            $params
        );
    }

    /**
     * Resolve a forum instance id from a module context.
     *
     * @param context $context Context to inspect.
     * @return int Forum id or zero when the context is not a forum module.
     */
    private static function get_forumid_from_context(context $context): int {
        global $DB;

        if (!$context instanceof context_module) {
            return 0;
        }

        $sql = "SELECT cm.instance
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid
                   AND m.name = :modname";
        return (int)$DB->get_field_sql($sql, [
            "cmid" => $context->instanceid,
            "modname" => "forum",
        ]);
    }
}
