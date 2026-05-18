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
 * Generate embeddings response.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_generate_embeddings extends \core_ai\aiactions\responses\response_base {
    /** @var string|null Provider response id. */
    private ?string $id = null;

    /** @var array<float|int> Embedding vector values. */
    private array $embedding = [];

    /** @var int|null Prompt token usage. */
    private ?int $prompttokens = null;

    /** @var int|null Embedding dimensions. */
    private ?int $dimensions = null;

    /**
     * Constructor.
     *
     * @param bool $success Whether the action call succeeded.
     * @param int $errorcode Optional error code.
     * @param string $error Optional error identifier.
     * @param string $errormessage Optional error message.
     */
    public function __construct(bool $success, int $errorcode = 0, string $error = '', string $errormessage = '') {
        parent::__construct(
            success: $success,
            actionname: 'generate_embeddings',
            errorcode: $errorcode,
            error: $error,
            errormessage: $errormessage,
        );
    }

    #[\Override]
    public function set_response_data(array $response): void {
        $this->id = $response['id'] ?? null;
        $this->embedding = $response['embedding'] ?? [];
        $this->prompttokens = $response['prompttokens'] ?? null;
        $this->dimensions = $response['dimensions'] ?? null;
        $this->model = $response['model'] ?? null;
    }

    #[\Override]
    public function get_response_data(): array {
        return [
            'id' => $this->id,
            'embedding' => $this->embedding,
            'prompttokens' => $this->prompttokens,
            'dimensions' => $this->dimensions,
            'model' => $this->model,
        ];
    }
}
