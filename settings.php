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
 * Plugin administration settings.
 *
 * @package   local_forumreactions
 * @copyright 2026 Forum reactions contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if ($hassiteconfig) {
    $settings = new admin_settingpage("local_forumreactions", get_string("pluginname", "local_forumreactions"));

    $settings->add(new admin_setting_configcheckbox(
        "local_forumreactions/enabled",
        get_string("enabled", "local_forumreactions"),
        get_string("enabled_desc", "local_forumreactions"),
        1
    ));

    $choices = [
        "like" => "👍 " . get_string("reaction_like", "local_forumreactions"),
        "love" => "❤️ " . get_string("reaction_love", "local_forumreactions"),
        "laugh" => "😂 " . get_string("reaction_laugh", "local_forumreactions"),
        "celebrate" => "🎉 " . get_string("reaction_celebrate", "local_forumreactions"),
        "thinking" => "🤔 " . get_string("reaction_thinking", "local_forumreactions"),
        "confused" => "😕 " . get_string("reaction_confused", "local_forumreactions"),
    ];

    $settings->add(new admin_setting_configmultiselect(
        "local_forumreactions/enabledreactions",
        get_string("enabledreactions", "local_forumreactions"),
        get_string("enabledreactions_desc", "local_forumreactions"),
        array_keys($choices),
        $choices
    ));

    $settings->add(new admin_setting_configcheckbox(
        "local_forumreactions/allowmultiple",
        get_string("allowmultiple", "local_forumreactions"),
        get_string("allowmultiple_desc", "local_forumreactions"),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        "local_forumreactions/allowself",
        get_string("allowself", "local_forumreactions"),
        get_string("allowself_desc", "local_forumreactions"),
        1
    ));

    $ADMIN->add("localplugins", $settings);
}
