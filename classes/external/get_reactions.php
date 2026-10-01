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
 * External function to read forum reactions.
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
use invalid_parameter_exception;
use local_forumreactions\reaction_manager;

/**
 * External API for loading forum reaction summaries.
 */
class get_reactions extends external_api {
    /**
     * Describe external function parameters.
     *
     * @return external_function_parameters Parameters definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            "cmid" => new external_value(PARAM_INT, "Forum course module id"),
            "postids" => new external_multiple_structure(
                new external_value(PARAM_INT, "Forum post id"),
                "Forum post ids",
                VALUE_REQUIRED
            ),
        ]);
    }

    /**
     * Load reactions for visible forum posts.
     *
     * @param int $cmid Forum course module id.
     * @param array $postids Forum post ids.
     * @return array Reaction summaries.
     */
    public static function execute(int $cmid, array $postids): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            "cmid" => $cmid,
            "postids" => $postids,
        ]);

        if (count($params["postids"]) > 200) {
            throw new invalid_parameter_exception("A maximum of 200 posts can be requested at once.");
        }

        [$cm, $course, $forum, $context] = reaction_manager::get_forum_data($params["cmid"]);
        self::validate_context($context);
        require_login($course, true, $cm);
        require_capability("mod/forum:viewdiscussion", $context);

        $posts = reaction_manager::get_visible_posts($forum, $cm, $params["postids"]);
        $canreact = has_capability("local/forumreactions:react", $context) && !isguestuser();

        return reaction_manager::get_summary($posts, (int)$USER->id, $canreact);
    }

    /**
     * Describe external function return data.
     *
     * @return external_multiple_structure Reaction summary structure.
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
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
            ])
        );
    }
}
