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

namespace aiprovider_wunderbyte;

use core\http_client;
use core_ai\ai_image;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Processor of Moodle's action "Generate image", for a model behind an OpenAI-compatible images endpoint.
 *
 * The request carries the model, the prompt, one image, and the size and quality that belong to the choices of
 * the action; the extra parameters of the provider instance are merged in last and can replace or (with null)
 * remove any of them, because image models differ in what they accept. The answer may carry the image as base64
 * data or as a URL; both are stored as a draft file of the user with Moodle's watermark for AI images.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @copyright  based on work by 2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_image extends abstract_processor {
    /** @var int The number of images per request. */
    private const NUMBER_OF_IMAGES = 1;

    /**
     * @var string[] The size sent for an aspect ratio of the action.
     *
     * The values Moodle's own OpenAI provider sends for a model it has no special rules for.
     */
    private const SIZES = [
        'square' => '1024x1024',
        'landscape' => '1536x1024',
        'portrait' => '1024x1536',
    ];

    /** @var string[] The quality sent for a quality of the action, as in Moodle's own OpenAI provider. */
    private const QUALITIES = [
        'standard' => 'medium',
        'hd' => 'high',
    ];

    /** @var string[] File extension by the MIME type of the image. */
    private const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /** @var int Error code for an answer of the service that holds no usable image. */
    private const ERROR_NO_IMAGE = 502;

    #[\Override]
    protected function query_ai_api(): array {
        $response = parent::query_ai_api();
        if (empty($response['success'])) {
            return $response;
        }

        try {
            // Placements cannot talk to the AI service, so the image goes to them as a file.
            $response['draftfile'] = $this->store_image((int)$this->action->get_configuration('userid'), $response);
        } catch (\Throwable $e) {
            debugging('aiprovider_wunderbyte: the generated image could not be stored: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $this->build_error_details(
                self::ERROR_NO_IMAGE,
                get_string('error:imagenotstored', 'aiprovider_wunderbyte'),
            );
        }
        // The image data is in the file now.
        unset($response['b64json']);

        return $response;
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $requestobj = new \stdClass();
        $requestobj->model = $this->get_model();
        $requestobj->user = $userid;
        $requestobj->prompt = $this->action->get_configuration('prompttext');
        $requestobj->n = self::NUMBER_OF_IMAGES;

        $size = self::SIZES[(string)$this->action->get_configuration('aspectratio')] ?? null;
        if ($size !== null) {
            $requestobj->size = $size;
        }
        $quality = self::QUALITIES[(string)$this->action->get_configuration('quality')] ?? null;
        if ($quality !== null) {
            $requestobj->quality = $quality;
        }

        // The extra parameters of the instance come last: they replace a default, and null removes it.
        foreach ($this->get_model_settings() as $setting => $value) {
            if ($value === null) {
                unset($requestobj->$setting);
                continue;
            }
            $requestobj->$setting = $value;
        }

        return new Request(
            method: 'POST',
            uri: '',
            headers: ['Content-Type' => 'application/json'],
            body: json_encode($requestobj, JSON_UNESCAPED_SLASHES),
        );
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $bodyobj = json_decode($response->getBody()->getContents());
        $item = is_object($bodyobj) && is_array($bodyobj->data ?? null) ? ($bodyobj->data[0] ?? null) : null;
        $b64json = is_object($item) ? (string)($item->b64_json ?? '') : '';
        $url = is_object($item) ? (string)($item->url ?? '') : '';

        if ($b64json === '' && $url === '') {
            return $this->build_error_details(self::ERROR_NO_IMAGE, get_string('error:noimage', 'aiprovider_wunderbyte'));
        }

        return [
            'success' => true,
            'b64json' => $b64json !== '' ? $b64json : null,
            'sourceurl' => $url !== '' ? $url : null,
            'revisedprompt' => isset($item->revised_prompt) ? (string)$item->revised_prompt : null,
            // The answer names no model, so the configured one is recorded.
            'model' => $this->get_model(),
        ];
    }

    /**
     * Store the image of an answer as a draft file of the user, with Moodle's watermark for AI images.
     *
     * @param int $userid The user the image is for.
     * @param array $response The answer as handle_api_success() returns it: base64 data or a URL.
     * @return \stored_file
     */
    private function store_image(int $userid, array $response): \stored_file {
        global $CFG;
        require_once("{$CFG->libdir}/filelib.php");

        if (!empty($response['b64json'])) {
            $bytes = base64_decode((string)$response['b64json'], true);
        } else {
            $client = \core\di::get(http_client::class);
            $bytes = $client->get((string)$response['sourceurl'], [
                'timeout' => $CFG->repositorygetfiletimeout,
            ])->getBody()->getContents();
        }
        if (!is_string($bytes) || $bytes === '') {
            throw new \moodle_exception('error:noimage', 'aiprovider_wunderbyte');
        }

        $imageinfo = getimagesizefromstring($bytes);
        $extension = self::EXTENSIONS[$imageinfo['mime'] ?? ''] ?? null;
        if ($extension === null) {
            throw new \moodle_exception('error:noimage', 'aiprovider_wunderbyte');
        }

        $filename = substr(hash('sha256', $bytes), 0, 16) . '.' . $extension;
        $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($tempdst, $bytes);

        $image = new ai_image($tempdst);
        $image->add_watermark()->save();

        // The file starts in the draft area of the user; the placement moves it to where it belongs.
        $fileinfo = new \stdClass();
        $fileinfo->contextid = \context_user::instance($userid)->id;
        $fileinfo->filearea = 'draft';
        $fileinfo->component = 'user';
        $fileinfo->itemid = file_get_unused_draft_itemid();
        $fileinfo->filepath = '/';
        $fileinfo->filename = $filename;

        return get_file_storage()->create_file_from_string($fileinfo, file_get_contents($tempdst));
    }
}
