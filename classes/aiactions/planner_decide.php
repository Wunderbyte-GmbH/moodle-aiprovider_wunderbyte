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

namespace aiprovider_wunderbyte\aiactions;

use core_ai\aiactions\base;
use core_ai\aiactions\responses\response_base;

/**
 * Planner action.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class planner_decide extends base {
    /** @var int Moodle user id owning the request. */
    protected int $userid;

    /** @var string Planner prompt payload. */
    protected string $prompttext;

    /**
     * Constructor.
     *
     * @param int $contextid Context id.
     * @param int $userid User id.
     * @param string $prompttext Planner prompt text.
     */
    public function __construct(
        int $contextid,
        int $userid,
        string $prompttext,
    ) {
        parent::__construct($contextid);
        $this->userid = $userid;
        $this->prompttext = $prompttext;
    }

    /**
     * Human-readable action name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('action_planner_decide', 'aiprovider_wunderbyte');
    }

    /**
     * Action description text.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('action_planner_decide_desc', 'aiprovider_wunderbyte');
    }

    /**
     * Default system instruction.
     *
     * @return string
     */
    public static function get_system_instruction(): string {
        return get_string('action_planner_decide_instruction', 'aiprovider_wunderbyte');
    }

    /**
     * Return provider-specific response class name.
     *
     * @return string
     */
    #[\Override]
    public static function get_response_classname(): string {
        return \aiprovider_wunderbyte\aiactions\responses\response_planner_decide::class;
    }

    #[\Override]
    public function store(response_base $response): int {
        global $DB;

        $responsearr = $response->get_response_data();
        $record = new \stdClass();
        $record->prompt = $this->prompttext;
        $record->responseid = $responsearr['id'] ?? null;
        $record->fingerprint = $responsearr['fingerprint'] ?? null;
        $record->generatedcontent = $responsearr['generatedcontent'] ?? null;
        $record->finishreason = $responsearr['finishreason'] ?? null;
        $record->prompttokens = $responsearr['prompttokens'] ?? null;
        $record->completiontoken = $responsearr['completiontokens'] ?? null;

        return $DB->insert_record($this->get_tablename(), $record);
    }
}
