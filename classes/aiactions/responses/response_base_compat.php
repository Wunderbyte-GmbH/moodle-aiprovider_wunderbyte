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

namespace aiprovider_wunderbyte\aiactions\responses;

/**
 * Cross-version construction of core_ai's response_base.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait response_base_compat {
    /**
     * Call response_base::__construct in a way that works on Moodle 5.0 and 5.1+.
     *
     * Moodle 5.0's response_base has no $error constructor parameter (it and
     * get_error() exist from 5.1 on), so passing it as a named argument there is
     * a fatal "Unknown named parameter $error" — thrown even for successful calls.
     *
     * @param bool $success Whether the action call succeeded.
     * @param string $actionname Name of the action that was processed.
     * @param int $errorcode Optional error code.
     * @param string $error Optional error identifier.
     * @param string $errormessage Optional error message.
     */
    private function construct_response_base(
        bool $success,
        string $actionname,
        int $errorcode,
        string $error,
        string $errormessage
    ): void {
        $args = [
            'success' => $success,
            'actionname' => $actionname,
            'errorcode' => $errorcode,
            'errormessage' => $errormessage,
        ];
        if (method_exists(\core_ai\aiactions\responses\response_base::class, 'get_error')) {
            $args['error'] = $error;
        } else if ($errormessage === '' && $error !== '') {
            // Moodle 5.0 rejects failure responses with an empty errormessage.
            $args['errormessage'] = $error;
        }
        parent::__construct(...$args);
    }
}
