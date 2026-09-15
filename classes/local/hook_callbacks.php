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
 * Output hook callbacks.
 *
 * @package   local_forumreactions
 * @copyright 2026 Forum reactions contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forumreactions\local;

use core\hook\output\before_standard_top_of_body_html_generation;

/**
 * Registers the reactions AMD module on forum activity pages.
 */
class hook_callbacks {
    /**
     * Queue the forum reactions JavaScript when rendering a forum activity.
     *
     * @param before_standard_top_of_body_html_generation $hook Output hook.
     */
    public static function before_standard_top_of_body_html(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;

        if (!isset($PAGE->cm->modname) || $PAGE->cm->modname != "forum") {
            return;
        }

        if (during_initial_install()) {
            return;
        }

        if (!get_config("local_forumreactions", "version")) {
            return;
        }

        $enabled = get_config("local_forumreactions", "enabled");
        if ($enabled !== false && empty($enabled)) {
            return;
        }

        if (!isloggedin()) {
            return;
        }

        $PAGE->requires->js_call_amd("local_forumreactions/reactions", "init", [["cmid" => $PAGE->cm->id]]);
    }
}
