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

namespace aiprovider_wunderbyte\form;

use core_ai\form\action_settings_form;

/**
 * Generic chat action settings form.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_chat_form extends action_settings_form {
    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;
        $actionconfig = $this->_customdata['actionconfig']['settings'] ?? [];
        $actionname = $this->_customdata['actionname'];
        $providername = $this->_customdata['providername'] ?? 'aiprovider_wunderbyte';

        $mform->addElement('header', 'generalsettingsheader', get_string('general', 'core'));

        $mform->addElement('text', 'endpoint', get_string('endpoint', 'aiprovider_wunderbyte'), ['size' => 60]);
        $mform->setType('endpoint', PARAM_URL);
        $mform->addRule('endpoint', null, 'required', null, 'client');
        $mform->setDefault('endpoint', $actionconfig['endpoint'] ?? 'https://llm.wunderbyte.at/v1/chat/completions');

        $mform->addElement('text', 'model', get_string('model', 'aiprovider_wunderbyte'), ['size' => 40]);
        $mform->setType('model', PARAM_TEXT);
        $mform->addRule('model', null, 'required', null, 'client');
        $mform->setDefault('model', $actionconfig['model'] ?? 'gpt-4o');

        $mform->addElement(
            'textarea',
            'systeminstruction',
            get_string('systeminstruction', 'aiprovider_wunderbyte'),
            ['rows' => 6, 'cols' => 60],
        );
        $mform->setType('systeminstruction', PARAM_TEXT);
        $mform->setDefault(
            'systeminstruction',
            $actionconfig['systeminstruction'] ??
                get_string("action_{$actionname}_instruction", 'aiprovider_wunderbyte'),
        );

        // Free-form extra request parameters as JSON, merged verbatim into the request body. Use this to
        // enforce a hard output cap, e.g. {"max_tokens": 500} (or {"max_completion_tokens": 500} for newer models).
        $mform->addElement(
            'textarea',
            'modelextraparams',
            get_string('extraparams', 'aiprovider_wunderbyte'),
            ['rows' => 5, 'cols' => 60],
        );
        $mform->setType('modelextraparams', PARAM_TEXT);
        $mform->setDefault('modelextraparams', $actionconfig['modelextraparams'] ?? '');
        $mform->addElement('static', 'modelextraparams_help', null, get_string('extraparams_help', 'aiprovider_wunderbyte'));

        $mform->addElement('hidden', 'action', $this->_customdata['action']);
        $mform->setType('action', PARAM_TEXT);
        $mform->addElement('hidden', 'provider', $providername);
        $mform->setType('provider', PARAM_TEXT);
        $mform->addElement('hidden', 'providerid', (int)($this->_customdata['providerid'] ?? 0));
        $mform->setType('providerid', PARAM_INT);

        if (!empty($this->_customdata['returnurl'])) {
            $mform->addElement('hidden', 'returnurl', $this->_customdata['returnurl']);
            $mform->setType('returnurl', PARAM_LOCALURL);
        }

        $this->set_data($this->_customdata['actionconfig'] ?? []);
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (!empty($data['modelextraparams'])) {
            json_decode($data['modelextraparams']);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors['modelextraparams'] = get_string('invalidjson', 'aiprovider_wunderbyte');
            }
        }

        return $errors;
    }
}
