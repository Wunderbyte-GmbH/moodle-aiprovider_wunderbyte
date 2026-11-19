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

/**
 * Empties the system instruction of every chat action on every Wunderbyte provider instance.
 *
 * The provider sends no instruction of its own: every caller brings its complete prompt, so switching a caller to
 * another action changes only the model (George 2026-09-30, Wunderbyte-GmbH/Wunderbyte-GmbH#2505). The admin field
 * stays; this clears what earlier versions stored in it, custom texts included.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class system_instruction_cleaner {
    /** @var string[] Chat actions whose instruction is emptied. */
    private const CHAT_ACTIONS = [
        \aiprovider_wunderbyte\aiactions\planner_decide::class,
        \aiprovider_wunderbyte\aiactions\planner_construct::class,
        \aiprovider_wunderbyte\aiactions\generate_agent_reply::class,
        \aiprovider_wunderbyte\aiactions\generate_content::class,
        \aiprovider_wunderbyte\aiactions\inspect_image::class,
        \core_ai\aiactions\generate_text::class,
    ];

    /**
     * Empty the chat instructions of all Wunderbyte provider instances.
     *
     * @return int Number of instances changed.
     */
    public static function clear_all(): int {
        global $DB;

        $changed = 0;
        $records = $DB->get_records('ai_providers', ['provider' => 'aiprovider_wunderbyte\\provider']);
        foreach ($records as $record) {
            $actionconfig = json_decode((string)($record->actionconfig ?? ''), true);
            if (!is_array($actionconfig)) {
                continue;
            }
            $touched = false;
            foreach (self::CHAT_ACTIONS as $actionclass) {
                if ((string)($actionconfig[$actionclass]['settings']['systeminstruction'] ?? '') === '') {
                    continue;
                }
                $actionconfig[$actionclass]['settings']['systeminstruction'] = '';
                $touched = true;
            }
            if (!$touched) {
                continue;
            }
            $record->actionconfig = json_encode($actionconfig);
            $DB->update_record('ai_providers', $record);
            $changed++;
        }

        return $changed;
    }
}
