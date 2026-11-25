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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace aiprovider_wunderbyte\aiactions;

/**
 * Parameter construction action of the agent planner.
 *
 * The agent's constructor phase builds the schema-bound parameters of the selected skill. It is the most
 * demanding planner call, so it gets its own action: the instance configures model and extra parameters for it
 * separately from the selector (planner_decide), and the action register reports its calls separately. The request
 * and the stored record are the same as for planner_decide; the record goes into the planner_decide table. Like every
 * action of this provider it sends no instruction of its own, so only the model may differ from planner_decide.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planner_construct extends planner_decide {
    #[\Override]
    public static function get_name(): string {
        return get_string('action_planner_construct', 'aiprovider_wunderbyte');
    }

    #[\Override]
    public static function get_description(): string {
        return get_string('action_planner_construct_desc', 'aiprovider_wunderbyte');
    }

    #[\Override]
    public static function get_response_classname(): string {
        return \aiprovider_wunderbyte\aiactions\responses\response_planner_construct::class;
    }
}
