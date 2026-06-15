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

namespace aiprovider_wunderbyte;

use core_ai\hook\after_ai_provider_form_hook;

/**
 * Hook listener for the Wunderbyte provider.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {
    /**
     * Add provider form fields.
     *
     * @param after_ai_provider_form_hook $hook The hook.
     */
    public static function set_form_definition_for_aiprovider_wunderbyte(after_ai_provider_form_hook $hook): void {
        if ($hook->plugin !== 'aiprovider_wunderbyte') {
            return;
        }

        global $PAGE;

        $mform = $hook->mform;
        $mform->addElement('passwordunmask', 'apikey', get_string('apikey', 'aiprovider_wunderbyte'), ['size' => 75]);
        $mform->setType('apikey', PARAM_TEXT);
        $mform->addRule('apikey', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('apikey', 'apikey', 'aiprovider_wunderbyte');

        // Show the live AI credit/usage bar for managers and admins. The bar
        // reads the instance id from the form at runtime (see initFromForm).
        if (has_capability('aiprovider/wunderbyte:viewusage', \context_system::instance())) {
            $mform->addElement(
                'static',
                'usagebar',
                get_string('usage_heading', 'aiprovider_wunderbyte'),
                \html_writer::div('', '', ['data-region' => 'wb-usage-bar']),
            );
            $PAGE->requires->js_call_amd(
                'aiprovider_wunderbyte/usage_bar',
                'initFromForm',
                ['[data-region="wb-usage-bar"]'],
            );
        }
    }
}
