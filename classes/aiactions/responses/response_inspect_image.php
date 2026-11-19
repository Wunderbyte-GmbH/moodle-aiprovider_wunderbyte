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

namespace aiprovider_wunderbyte\aiactions\responses;

/**
 * Response of the inspect_image action.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_inspect_image extends \core_ai\aiactions\responses\response_base {
    use response_base_compat;

    /** @var string|null The generated content id. */
    private ?string $id = null;

    /** @var string|null The model fingerprint. */
    private ?string $fingerprint = null;

    /** @var string|null The generated content. */
    private ?string $generatedcontent = null;

    /** @var string|null Why generation stopped. */
    private ?string $finishreason = null;

    /** @var int|null Prompt token count. */
    private ?int $prompttokens = null;

    /** @var int|null Completion token count. */
    private ?int $completiontokens = null;

    /**
     * Create a new response.
     *
     * @param bool $success
     * @param int $errorcode
     * @param string $error
     * @param string $errormessage
     */
    public function __construct(bool $success, int $errorcode = 0, string $error = '', string $errormessage = '') {
        $this->construct_response_base($success, static::get_actionname_for_response(), $errorcode, $error, $errormessage);
    }

    /**
     * The action name of this response.
     *
     * @return string
     */
    protected static function get_actionname_for_response(): string {
        return 'inspect_image';
    }

    #[\Override]
    public function set_response_data(array $response): void {
        $this->id = $response['id'] ?? null;
        $this->fingerprint = $response['fingerprint'] ?? null;
        $this->generatedcontent = $response['generatedcontent'] ?? null;
        $this->finishreason = $response['finishreason'] ?? null;
        $this->prompttokens = $response['prompttokens'] ?? null;
        $this->completiontokens = $response['completiontokens'] ?? null;
        $this->model = $response['model'] ?? null;
    }

    #[\Override]
    public function get_response_data(): array {
        return [
            'id' => $this->id,
            'fingerprint' => $this->fingerprint,
            'generatedcontent' => $this->generatedcontent,
            'finishreason' => $this->finishreason,
            'prompttokens' => $this->prompttokens,
            'completiontokens' => $this->completiontokens,
            'model' => $this->model,
        ];
    }
}
