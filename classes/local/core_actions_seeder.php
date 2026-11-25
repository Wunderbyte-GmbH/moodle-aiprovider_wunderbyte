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

namespace aiprovider_wunderbyte\local;

use aiprovider_wunderbyte\form\action_chat_form;
use aiprovider_wunderbyte\form\action_image_form;
use core_ai\aiactions\explain_text;
use core_ai\aiactions\generate_image;
use core_ai\aiactions\generate_text;
use core_ai\aiactions\summarise_text;

/**
 * Gives existing provider instances the entries of Moodle's actions the provider offers since release 1.0.4:
 * summarise_text, explain_text and generate_image.
 *
 * Every entry is switched off. Nothing changes on a site until an administrator switches an action on; the entry is
 * there so that the action then has settings to work with. The two text actions start with the endpoint, the model
 * and the extra parameters of the instance's generate_text, so an instance that points to a site's own model keeps
 * pointing there. Image generation starts without a model.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class core_actions_seeder {
    /** @var string[] The actions this step adds. A record of release 1.0.4. */
    private const ACTIONS = [
        summarise_text::class,
        explain_text::class,
        generate_image::class,
    ];

    /**
     * Add the missing entries to every instance of this provider. An entry that exists is never changed.
     *
     * @return int Number of instances changed.
     */
    public static function seed_missing(): int {
        global $DB;

        $changed = 0;
        $records = $DB->get_records('ai_providers', ['provider' => 'aiprovider_wunderbyte\\provider']);
        foreach ($records as $record) {
            $actionconfig = json_decode((string)($record->actionconfig ?? ''), true);
            if (!is_array($actionconfig)) {
                continue;
            }
            $added = false;
            foreach (self::ACTIONS as $actionclass) {
                if (array_key_exists($actionclass, $actionconfig)) {
                    continue;
                }
                $actionconfig[$actionclass] = self::build_entry($actionclass, $actionconfig);
                $added = true;
            }
            if ($added) {
                $record->actionconfig = json_encode($actionconfig);
                $DB->update_record('ai_providers', $record);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * The entry of one action for one instance: switched off, with settings to start from.
     *
     * @param string $actionclass One of the actions of this step.
     * @param array $actionconfig The instance's decoded action configuration.
     * @return array
     */
    public static function build_entry(string $actionclass, array $actionconfig): array {
        if ($actionclass === generate_image::class) {
            return [
                'enabled' => false,
                'settings' => [
                    'endpoint' => action_image_form::DEFAULT_ENDPOINT,
                    'model' => '',
                ],
            ];
        }

        $text = (array)($actionconfig[generate_text::class]['settings'] ?? []);
        $settings = [
            'endpoint' => (string)($text['endpoint'] ?? ''),
            'model' => (string)($text['model'] ?? '') !== ''
                ? (string)$text['model']
                : action_chat_form::default_model($actionclass::get_basename()),
            'systeminstruction' => action_chat_form::default_system_instruction($actionclass),
        ];
        if ((string)($text['modelextraparams'] ?? '') !== '') {
            $settings['modelextraparams'] = (string)$text['modelextraparams'];
        }

        return [
            'enabled' => false,
            'settings' => $settings,
        ];
    }
}
