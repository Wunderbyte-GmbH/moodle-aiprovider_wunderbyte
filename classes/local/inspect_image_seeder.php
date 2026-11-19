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

namespace aiprovider_wunderbyte\local;

use aiprovider_wunderbyte\aiactions\inspect_image;
use aiprovider_wunderbyte\form\action_chat_form;
use core_ai\aiactions\generate_text;

/**
 * Gives existing provider instances the inspect_image action entry (endpoint and extra parameters of
 * generate_text, the default chat model as model).
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inspect_image_seeder {
    /**
     * Add the entry to every instance that has none yet.
     *
     * @return int Number of instances changed.
     */
    public static function seed_missing(): int {
        global $DB;

        $changed = 0;
        $records = $DB->get_records('ai_providers', ['provider' => 'aiprovider_wunderbyte\\provider']);
        foreach ($records as $record) {
            $actionconfig = json_decode((string)($record->actionconfig ?? ''), true);
            if (!is_array($actionconfig) || array_key_exists(inspect_image::class, $actionconfig)) {
                continue;
            }
            $actionconfig[inspect_image::class] = self::build_entry($actionconfig);
            $record->actionconfig = json_encode($actionconfig);
            $DB->update_record('ai_providers', $record);
            $changed++;
        }

        return $changed;
    }

    /**
     * The entry for one instance, derived from its generate_text settings.
     *
     * @param array $actionconfig The instance's decoded action configuration.
     * @return array
     */
    public static function build_entry(array $actionconfig): array {
        $text = (array)($actionconfig[generate_text::class]['settings'] ?? []);
        $settings = [
            'endpoint' => (string)($text['endpoint'] ?? ''),
            'model' => action_chat_form::default_model('inspect_image'),
            'systeminstruction' => '',
        ];
        if ((string)($text['modelextraparams'] ?? '') !== '') {
            $settings['modelextraparams'] = (string)$text['modelextraparams'];
        }

        return [
            'enabled' => true,
            'settings' => $settings,
        ];
    }
}
