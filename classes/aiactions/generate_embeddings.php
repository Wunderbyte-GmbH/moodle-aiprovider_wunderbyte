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
 * Generate embeddings action.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_embeddings extends base {
    /** @var int Moodle user id owning the request. */
    protected int $userid;

    /** @var string Input text to convert into an embedding vector. */
    protected string $inputtext;

    /** @var int|null Optional requested embedding dimensions. */
    protected ?int $dimensions;

    /**
     * Constructor.
     *
     * @param int $contextid Context id.
     * @param int $userid User id.
     * @param string $inputtext Text to embed.
     * @param int|null $dimensions Optional dimensions override.
     */
    public function __construct(
        int $contextid,
        int $userid,
        string $inputtext,
        ?int $dimensions = null,
    ) {
        parent::__construct($contextid);
        $this->userid = $userid;
        $this->inputtext = $inputtext;
        $this->dimensions = $dimensions;
    }

    /**
     * Human-readable action name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('action_generate_embeddings', 'aiprovider_wunderbyte');
    }

    /**
     * Action description text.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('action_generate_embeddings_desc', 'aiprovider_wunderbyte');
    }

    /**
     * Default system instruction.
     *
     * @return string
     */
    public static function get_system_instruction(): string {
        return get_string('action_generate_embeddings_instruction', 'aiprovider_wunderbyte');
    }

    /**
     * Return provider-specific response class name.
     *
     * @return string
     */
    #[\Override]
    public static function get_response_classname(): string {
        return \aiprovider_wunderbyte\aiactions\responses\response_generate_embeddings::class;
    }

    #[\Override]
    public function store(response_base $response): int {
        global $DB;

        $responsearr = $response->get_response_data();
        $record = new \stdClass();
        $record->inputtext = $this->inputtext;
        $record->responseid = $responsearr['id'] ?? null;
        $record->model = $responsearr['model'] ?? null;
        $record->embedding = json_encode($responsearr['embedding'] ?? [], JSON_UNESCAPED_UNICODE);
        $record->dimensions = $responsearr['dimensions'] ?? $this->dimensions;
        $record->prompttokens = $responsearr['prompttokens'] ?? null;

        return $DB->insert_record($this->get_tablename(), $record);
    }
}
