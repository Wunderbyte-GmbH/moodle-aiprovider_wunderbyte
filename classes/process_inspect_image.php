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

namespace aiprovider_wunderbyte;

/**
 * Processor of the inspect_image action: a chat completion whose user message carries the prompt and the images.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_inspect_image extends process_chat_action {
    /**
     * The prompt as a text part, followed by one image part per image (OpenAI-compatible content parts).
     *
     * @return string|array
     */
    #[\Override]
    protected function build_user_content(): string|array {
        $parts = [(object)['type' => 'text', 'text' => (string)$this->action->get_configuration('prompttext')]];
        foreach ((array)$this->action->get_configuration('images') as $image) {
            $mimetype = (string)($image['mimetype'] ?? '');
            $data = (string)($image['data'] ?? '');
            if ($mimetype === '' || $data === '') {
                continue;
            }
            $parts[] = (object)[
                'type' => 'image_url',
                'image_url' => (object)['url' => 'data:' . $mimetype . ';base64,' . $data],
            ];
        }
        return $parts;
    }
}
