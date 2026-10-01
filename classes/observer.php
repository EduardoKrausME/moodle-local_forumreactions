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
 * Event observers for forum reaction cleanup.
 *
 * @package   local_forumreactions
 * @copyright 2026 Forum reactions contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumreactions;

use core\event\course_module_deleted;
use mod_forum\event\post_deleted;

/**
 * Deletes orphaned reaction records when forum content is removed.
 */
class observer {
    /**
     * Delete reactions associated with a deleted forum post.
     *
     * @param post_deleted $event Forum post deleted event.
     */
    public static function post_deleted(post_deleted $event): void {
        global $DB;

        if ($event->objectid) {
            $DB->delete_records(reaction_manager::TABLE, ["postid" => (int)$event->objectid]);
        }
    }

    /**
     * Delete reactions when an entire forum activity is removed.
     *
     * @param course_module_deleted $event Course module deleted event.
     */
    public static function course_module_deleted(course_module_deleted $event): void {
        global $DB;

        if (($event->other["modulename"] ?? "") !== "forum") {
            return;
        }

        $forumid = (int)($event->other["instanceid"] ?? 0);
        if ($forumid > 0) {
            $DB->delete_records(reaction_manager::TABLE, ["forumid" => $forumid]);
        }
    }
}
