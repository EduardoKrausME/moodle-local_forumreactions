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
 * Forum reaction data and business logic.
 *
 * @package   local_forumreactions
 * @copyright 2026 Forum reactions contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumreactions;

use context_module;
use moodle_exception;
use stdClass;

/**
 * Manages enabled reactions, visibility checks, summaries and reaction changes.
 */
class reaction_manager {
    /** Database table storing reactions. */
    public const TABLE = "local_forumreactions_reactions";

    /** Supported reactions and their language string keys. */
    private const REACTIONS = [
        "like" => ["emoji" => "👍", "string" => "reaction_like"],
        "love" => ["emoji" => "❤️", "string" => "reaction_love"],
        "laugh" => ["emoji" => "😂", "string" => "reaction_laugh"],
        "celebrate" => ["emoji" => "🎉", "string" => "reaction_celebrate"],
        "thinking" => ["emoji" => "🤔", "string" => "reaction_thinking"],
        "confused" => ["emoji" => "😕", "string" => "reaction_confused"],
    ];

    /**
     * Return enabled reaction definitions.
     *
     * @return array Reaction definitions indexed by reaction key.
     */
    public static function get_enabled_reactions(): array {
        $configured = get_config("local_forumreactions", "enabledreactions");

        if ($configured === false || $configured === "") {
            $enabled = array_keys(self::REACTIONS);
        } else if (is_array($configured)) {
            $enabled = $configured;
        } else {
            $enabled = explode(",", $configured);
        }

        $result = [];
        foreach ($enabled as $key) {
            $key = trim((string) $key);
            if (!isset(self::REACTIONS[$key])) {
                continue;
            }
            $result[$key] = [
                "key" => $key,
                "emoji" => self::REACTIONS[$key]["emoji"],
                "label" => get_string(self::REACTIONS[$key]["string"], "local_forumreactions"),
            ];
        }

        return $result;
    }

    /**
     * Load the forum objects required by the external functions.
     *
     * @param int $cmid Course module id.
     * @return array Course module, course, forum and module context.
     */
    public static function get_forum_data(int $cmid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . "/mod/forum/lib.php");

        $cm = get_coursemodule_from_id("forum", $cmid, 0, false, MUST_EXIST);
        $course = get_course($cm->course);
        $forum = $DB->get_record("forum", ["id" => $cm->instance], "*", MUST_EXIST);
        $context = context_module::instance($cm->id);

        return [$cm, $course, $forum, $context];
    }

    /**
     * Return only posts the current user can see in the requested forum.
     *
     * @param stdClass $forum Forum record.
     * @param stdClass $cm Course module record.
     * @param array $postids Post ids to validate.
     * @return array Visible posts indexed by post id.
     */
    public static function get_visible_posts(stdClass $forum, stdClass $cm, array $postids): array {
        global $DB;

        $postids = array_values(array_unique(array_filter(array_map("intval", $postids))));
        if (empty($postids)) {
            return [];
        }

        $posts = $DB->get_records_list("forum_posts", "id", $postids);
        if (empty($posts)) {
            return [];
        }

        $discussionids = [];
        foreach ($posts as $post) {
            $discussionids[(int) $post->discussion] = (int) $post->discussion;
        }
        $discussions = $DB->get_records_list("forum_discussions", "id", array_values($discussionids));

        $visible = [];
        foreach ($posts as $post) {
            $discussion = $discussions[$post->discussion] ?? null;
            if (!$discussion || (int) $discussion->forum !== (int) $forum->id) {
                continue;
            }

            if (!forum_user_can_see_post($forum, $discussion, $post, null, $cm)) {
                continue;
            }

            $visible[(int) $post->id] = $post;
        }

        return $visible;
    }

    /**
     * Build reaction counts and current-user state for forum posts.
     *
     * @param array $posts Forum posts indexed by post id.
     * @param int $userid Current user id.
     * @param bool $canreact Whether the current user may react.
     * @return array Reaction summary for the requested posts.
     */
    public static function get_summary(array $posts, int $userid, bool $canreact): array {
        global $DB;

        $definitions = self::get_enabled_reactions();
        if (empty($posts) || empty($definitions)) {
            return [];
        }

        $postids = array_keys($posts);
        [$insql, $params] = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, "postid");

        $counts = [];
        $sql = "SELECT postid, reaction, COUNT(id) AS reactioncount
                  FROM {" . self::TABLE . "}
                 WHERE postid {$insql}
              GROUP BY postid, reaction";
        $recordset = $DB->get_recordset_sql($sql, $params);
        foreach ($recordset as $record) {
            $counts[(int) $record->postid][$record->reaction] = (int) $record->reactioncount;
        }
        $recordset->close();

        $mine = [];
        if ($userid > 0 && !isguestuser()) {
            $mineparams = $params;
            $mineparams["userid"] = $userid;
            $sql = "SELECT postid, reaction
                      FROM {" . self::TABLE . "}
                     WHERE postid {$insql}
                       AND userid = :userid";
            $recordset = $DB->get_recordset_sql($sql, $mineparams);
            foreach ($recordset as $record) {
                $mine[(int) $record->postid][$record->reaction] = true;
            }
            $recordset->close();
        }

        $result = [];
        foreach ($posts as $postid => $post) {
            $reactions = [];
            foreach ($definitions as $definition) {
                $key = $definition["key"];
                $reactions[] = [
                    "key" => $key,
                    "emoji" => $definition["emoji"],
                    "label" => $definition["label"],
                    "count" => $counts[$postid][$key] ?? 0,
                    "mine" => !empty($mine[$postid][$key]),
                ];
            }

            $result[] = [
                "postid" => (int) $postid,
                "canreact" => $canreact,
                "reactions" => $reactions,
            ];
        }

        return $result;
    }

    /**
     * Add or remove one reaction for a user and post.
     *
     * @param stdClass $forum Forum record.
     * @param stdClass $post Forum post record.
     * @param int $userid User id.
     * @param string $reaction Reaction key.
     */
    public static function toggle(stdClass $forum, stdClass $post, int $userid, string $reaction): void {
        global $DB;

        $definitions = self::get_enabled_reactions();
        if (!isset($definitions[$reaction])) {
            throw new moodle_exception("invalidreaction", "local_forumreactions");
        }

        $allowself = get_config("local_forumreactions", "allowself");
        if ($allowself !== false && empty($allowself) && (int) $post->userid === $userid) {
            throw new moodle_exception("selfreactiondisabled", "local_forumreactions");
        }

        $params = [
            "postid" => (int) $post->id,
            "userid" => $userid,
            "reaction" => $reaction,
        ];

        $existing = $DB->get_record(self::TABLE, $params, "id", IGNORE_MISSING);
        if ($existing) {
            $DB->delete_records(self::TABLE, ["id" => $existing->id]);
            return;
        }

        $allowmultiple = get_config("local_forumreactions", "allowmultiple");
        if ($allowmultiple !== false && empty($allowmultiple)) {
            $DB->delete_records(self::TABLE, [
                "postid" => (int) $post->id,
                "userid" => $userid,
            ]);
        }

        $record = (object) [
            "forumid" => (int) $forum->id,
            "discussionid" => (int) $post->discussion,
            "postid" => (int) $post->id,
            "userid" => $userid,
            "reaction" => $reaction,
            "timecreated" => time(),
        ];

        $DB->insert_record(self::TABLE, $record);
    }
}
