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
 * A question about one or more images: the prompt and the images go to a model that can see them.
 *
 * Its own action so a site can choose the model for image input and the gateway can tell these calls
 * apart (metadata.action).
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class inspect_image extends base {
    /** @var int The user who asked. */
    protected int $userid;

    /** @var string The complete prompt of the caller. */
    protected string $prompttext;

    /** @var array[] The images, each as ['mimetype' => string, 'data' => base64 string]. */
    protected array $images;

    /**
     * Create a new instance of the action.
     *
     * @param int $contextid The context id the action was created in.
     * @param int $userid The user id making the request.
     * @param string $prompttext The complete prompt.
     * @param array[] $images The images, each as ['mimetype' => string, 'data' => base64 string].
     */
    public function __construct(
        int $contextid,
        int $userid,
        string $prompttext,
        array $images = [],
    ) {
        parent::__construct($contextid);
        $this->userid = $userid;
        $this->prompttext = $prompttext;
        $this->images = array_values($images);
    }

    /**
     * Get the name of the action.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('action_inspect_image', 'aiprovider_wunderbyte');
    }

    /**
     * Get the description of the action.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('action_inspect_image_desc', 'aiprovider_wunderbyte');
    }

    /**
     * No instruction of its own: every caller brings its complete prompt.
     *
     * @return string
     */
    public static function get_system_instruction(): string {
        return '';
    }

    #[\Override]
    public static function get_response_classname(): string {
        return \aiprovider_wunderbyte\aiactions\responses\response_inspect_image::class;
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
