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

namespace aiprovider_wunderbyte\aiactions;

use aiprovider_wunderbyte\local\action_tables;

/**
 * An action of this provider stores its calls in a table of this plugin.
 *
 * The actions of Moodle's AI subsystem store their calls in ai_action_<action>, tables the subsystem defines itself
 * (core_ai\aiactions\base::get_tablename()). The tables of this plugin carry the component name instead;
 * local\action_tables lists them.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait provider_table {
    /**
     * The table the action stores its calls in.
     *
     * @return string
     */
    #[\Override]
    protected function get_tablename(): string {
        return action_tables::by_action()[static::get_basename()];
    }
}
