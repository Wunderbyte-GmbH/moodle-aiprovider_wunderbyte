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

namespace aiprovider_wunderbyte\form;

use core_ai\form\action_settings_form;

/**
 * Settings form of the action "Generate image": the endpoint for image generation, the model and extra parameters.
 *
 * The model has no default: the default service of this provider has no model that creates images. A site with such
 * a model behind an OpenAI-compatible endpoint enters it here.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action_image_form extends action_settings_form {
    /** @var string The endpoint a new entry of the action starts with. */
    public const DEFAULT_ENDPOINT = 'https://llm.wunderbyte.at/v1/images/generations';

    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;
        $actionconfig = $this->_customdata['actionconfig']['settings'] ?? [];
        $providername = $this->_customdata['providername'] ?? 'aiprovider_wunderbyte';

        $mform->addElement('header', 'generalsettingsheader', get_string('general', 'core'));

        $mform->addElement('text', 'endpoint', get_string('endpoint', 'aiprovider_wunderbyte'), ['size' => 60]);
        $mform->setType('endpoint', PARAM_URL);
        $mform->addRule('endpoint', null, 'required', null, 'client');
        $mform->setDefault('endpoint', $actionconfig['endpoint'] ?? self::DEFAULT_ENDPOINT);

        $mform->addElement('text', 'model', get_string('model', 'aiprovider_wunderbyte'), ['size' => 40]);
        $mform->setType('model', PARAM_TEXT);
        $mform->addRule('model', null, 'required', null, 'client');
        $mform->setDefault('model', $actionconfig['model'] ?? '');
        $mform->addElement('static', 'model_help', null, get_string('imagemodel_help', 'aiprovider_wunderbyte'));

        // Free-form extra request parameters as JSON, merged into the request body last, so they can replace the
        // size and the quality the provider sends by default.
        $mform->addElement(
            'textarea',
            'modelextraparams',
            get_string('extraparams', 'aiprovider_wunderbyte'),
            ['rows' => 5, 'cols' => 60],
        );
        $mform->setType('modelextraparams', PARAM_TEXT);
        $mform->setDefault('modelextraparams', $actionconfig['modelextraparams'] ?? '');
        $mform->addElement('static', 'modelextraparams_help', null, get_string('imageextraparams_help', 'aiprovider_wunderbyte'));

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
