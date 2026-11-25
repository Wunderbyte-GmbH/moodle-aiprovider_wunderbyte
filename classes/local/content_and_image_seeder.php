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

use aiprovider_wunderbyte\aiactions\generate_content;
use aiprovider_wunderbyte\aiactions\inspect_image;
use core_ai\aiactions\generate_text;

/**
 * Gives generate_content and inspect_image to provider instances that were written after the two actions came.
 *
 * The upgrade steps 2026101900 and 2026111800 gave both entries to every instance that existed then. An instance
 * that was created or rewritten later by a setup that did not know the two actions has none: until
 * Wunderbyte-GmbH/Wunderbyte-GmbH#2824 the trial and key setup of the Booking Wizard replaced the whole action
 * configuration of the instance with its own five entries. On such a site the content model and the reading of
 * images cannot be switched on. This seeder adds what is missing.
 *
 * The entries are the ones of {@see generate_content_seeder} and {@see inspect_image_seeder} (endpoint and extra
 * parameters of the instance's generate_text, switched on), with two differences that keep a repaired instance
 * working:
 * - An instance that does not talk to the Wunderbyte gateway gets the model of its own generate_text. Another
 *   endpoint does not know the model aliases of the gateway, and a request with an unknown model fails.
 * - An instance without an endpoint for generate_text is left alone: there is nothing to derive an entry from.
 *
 * An entry that exists is never changed.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class content_and_image_seeder {
    /** @var string Host of the Wunderbyte gateway; the default models of the plugin are aliases of this gateway. */
    private const GATEWAY_HOST = 'llm.wunderbyte.at';

    /**
     * Add the missing entries to every instance of this provider.
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
            $text = (array)($actionconfig[generate_text::class]['settings'] ?? []);
            if (trim((string)($text['endpoint'] ?? '')) === '') {
                continue;
            }

            $missing = [];
            if (!array_key_exists(generate_content::class, $actionconfig)) {
                $missing[generate_content::class] = generate_content_seeder::build_entry($actionconfig);
            }
            if (!array_key_exists(inspect_image::class, $actionconfig)) {
                $missing[inspect_image::class] = inspect_image_seeder::build_entry($actionconfig);
            }
            if (!$missing) {
                continue;
            }

            foreach ($missing as $actionclass => $entry) {
                $actionconfig[$actionclass] = self::with_the_model_of_the_instance($entry, $text);
            }
            $record->actionconfig = json_encode($actionconfig);
            $DB->update_record('ai_providers', $record);
            $changed++;
        }
        return $changed;
    }

    /**
     * Replace the gateway's model alias by the model of the instance when the instance talks to another endpoint.
     *
     * @param array $entry The entry a seeder built.
     * @param array $text The settings of the instance's generate_text.
     * @return array
     */
    private static function with_the_model_of_the_instance(array $entry, array $text): array {
        $host = strtolower((string)parse_url((string)$text['endpoint'], PHP_URL_HOST));
        $model = trim((string)($text['model'] ?? ''));
        if ($host !== self::GATEWAY_HOST && $model !== '') {
            $entry['settings']['model'] = $model;
        }
        return $entry;
    }
}
