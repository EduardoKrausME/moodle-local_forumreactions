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
 * External function to add or remove a forum reaction.
 *
 * @package   local_forumreactions
 * @copyright 2026 Forum reactions contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumreactions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_forumreactions\reaction_manager;
use moodle_exception;

/**
 * External API for toggling one forum reaction.
 */
class toggle_reaction extends external_api {
    /**
     * Describe external function parameters.
     *
     * @return external_function_parameters Parameters definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            "cmid" => new external_value(PARAM_INT, "Forum course module id"),
            "postid" => new external_value(PARAM_INT, "Forum post id"),
            "reaction" => new external_value(PARAM_ALPHANUMEXT, "Reaction key"),
        ]);
    }

    /**
     * Add or remove the current user reaction.
     *
     * @param int $cmid Forum course module id.
     * @param int $postid Forum post id.
     * @param string $reaction Reaction key.
     * @return array Updated reaction summary for the post.
     */
    public static function execute(int $cmid, int $postid, string $reaction): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            "cmid" => $cmid,
            "postid" => $postid,
            "reaction" => $reaction,
        ]);

        [$cm, $course, $forum, $context] = reaction_manager::get_forum_data($params["cmid"]);
        self::validate_context($context);
        require_login($course, true, $cm);
        require_capability("mod/forum:viewdiscussion", $context);
        require_capability("local/forumreactions:react", $context);

        if (isguestuser()) {
            throw new moodle_exception("noguestreactions", "local_forumreactions");
        }

        $posts = reaction_manager::get_visible_posts($forum, $cm, [$params["postid"]]);
        if (empty($posts[$params["postid"]])) {
            throw new moodle_exception("postnotavailable", "local_forumreactions");
        }

        reaction_manager::toggle($forum, $posts[$params["postid"]], (int) $USER->id, $params["reaction"]);

        $summary = reaction_manager::get_summary(
            [$params["postid"] => $posts[$params["postid"]]],
            (int) $USER->id,
            true
        );

        return reset($summary);
    }

    /**
     * Describe external function return data.
     *
     * @return external_single_structure Updated reaction summary structure.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            "postid" => new external_value(PARAM_INT, "Forum post id"),
            "canreact" => new external_value(PARAM_BOOL, "Whether the current user can react"),
            "reactions" => new external_multiple_structure(
                new external_single_structure([
                    "key" => new external_value(PARAM_ALPHANUMEXT, "Reaction key"),
                    "emoji" => new external_value(PARAM_RAW, "Reaction emoji"),
                    "label" => new external_value(PARAM_TEXT, "Reaction label"),
                    "count" => new external_value(PARAM_INT, "Reaction count"),
                    "mine" => new external_value(PARAM_BOOL, "Whether the current user selected it"),
                ])
            ),
        ]);
    }
}
