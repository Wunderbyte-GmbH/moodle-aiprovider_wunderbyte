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

/**
 * External service definitions for aiprovider_wunderbyte.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'aiprovider_wunderbyte_get_usage' => [
        'classname'    => '\\aiprovider_wunderbyte\\external\\get_usage',
        'methodname'   => 'execute',
        'description'  => 'Read the LiteLLM key budget/usage for a Wunderbyte AI provider instance.',
        'type'         => 'read',
        'capabilities' => 'aiprovider/wunderbyte:viewusage',
        'ajax'         => 1,
    ],
];
