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

use core_ai\aiactions\generate_image;
use GuzzleHttp\Psr7\Response;

/**
 * Image generation for a model behind an OpenAI-compatible images endpoint.
 *
 * The answers of the service are simulated: the test site has no model that creates images. What is pinned is the
 * request the provider builds, the two shapes of an answer it accepts (base64 data and a URL), the file the placement
 * receives, and what the user gets when the answer holds no usable image.
 *
 * @package    aiprovider_wunderbyte
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\process_generate_image
 */
final class process_generate_image_test extends \advanced_testcase {
    /** @var \stdClass The user who asks for the image. */
    private \stdClass $user;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->user = $this->getDataGenerator()->create_user();
        $this->setUser($this->user);
    }

    /**
     * A provider instance with image generation switched on.
     *
     * @param string $extraparams The extra parameters of the action as JSON, or an empty string.
     * @return \core_ai\provider
     */
    private function provider(string $extraparams = ''): \core_ai\provider {
        $settings = ['model' => 'the-image-model', 'endpoint' => 'https://ai.example.org/v1/images/generations'];
        if ($extraparams !== '') {
            $settings['modelextraparams'] = $extraparams;
        }
        return \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'images',
            enabled: true,
            config: ['apikey' => '123'],
            actionconfig: [generate_image::class => ['enabled' => true, 'settings' => $settings]],
        );
    }

    /**
     * The action as a placement creates it.
     *
     * @param string $aspectratio
     * @param string $quality
     * @return generate_image
     */
    private function action(string $aspectratio = 'landscape', string $quality = 'hd'): generate_image {
        return new generate_image(
            contextid: \context_system::instance()->id,
            userid: (int)$this->user->id,
            prompttext: 'A lighthouse at dawn',
            quality: $quality,
            aspectratio: $aspectratio,
            numimages: 1,
            style: 'vivid',
        );
    }

    /**
     * The decoded request body the processor would send.
     *
     * @param process_generate_image $processor
     * @return \stdClass
     */
    private function request_body(process_generate_image $processor): \stdClass {
        $method = new \ReflectionMethod($processor, 'create_request_object');
        return json_decode($method->invoke($processor, 'hashed user')->getBody()->getContents());
    }

    /**
     * The bytes of a small PNG image.
     *
     * @return string
     */
    private function png(): string {
        $image = imagecreatetruecolor(320, 240);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 90, 160));
        ob_start();
        imagepng($image);
        return (string)ob_get_clean();
    }

    /**
     * The answer of an images endpoint with one entry.
     *
     * @param array $entry The entry of the data list.
     * @return Response
     */
    private function answer(array $entry): Response {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['created' => 1, 'data' => [$entry]]));
    }

    /**
     * The request names the model, the prompt, one image, and the size and quality for the choices of the action.
     */
    public function test_the_request_carries_model_prompt_size_and_quality(): void {
        $provider = $this->provider();

        $body = $this->request_body(new process_generate_image($provider, $this->action('landscape', 'hd')));
        $this->assertSame('the-image-model', $body->model);
        $this->assertSame('A lighthouse at dawn', $body->prompt);
        $this->assertSame(1, $body->n);
        $this->assertSame('1536x1024', $body->size);
        $this->assertSame('high', $body->quality);
        $this->assertSame('hashed user', $body->user);
        // Settings of the instance that are no request parameters stay at home.
        $this->assertObjectNotHasProperty('endpoint', $body);
        $this->assertObjectNotHasProperty('modelextraparams', $body);

        $body = $this->request_body(new process_generate_image($provider, $this->action('square', 'standard')));
        $this->assertSame('1024x1024', $body->size);
        $this->assertSame('medium', $body->quality);

        $body = $this->request_body(new process_generate_image($provider, $this->action('portrait', 'standard')));
        $this->assertSame('1024x1536', $body->size);
    }

    /**
     * The extra parameters of the instance replace a default, add a parameter, and remove one with null.
     */
    public function test_the_extra_parameters_replace_add_and_remove(): void {
        $provider = $this->provider('{"size": "1792x1024", "style": "natural", "quality": null}');

        $body = $this->request_body(new process_generate_image($provider, $this->action('landscape', 'hd')));

        $this->assertSame('1792x1024', $body->size);
        $this->assertSame('natural', $body->style);
        $this->assertObjectNotHasProperty('quality', $body);
        $this->assertSame('the-image-model', $body->model);
    }

    /**
     * An answer with base64 data becomes a draft file of the user, an image with Moodle's watermark.
     */
    public function test_an_answer_with_base64_data_becomes_a_draft_file(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $png = $this->png();
        $mock->append($this->answer(['b64_json' => base64_encode($png), 'revised_prompt' => 'A lighthouse, early light']));

        $result = (new process_generate_image($this->provider(), $this->action()))->process();

        $this->assertTrue($result->get_success());
        $data = $result->get_response_data();
        $file = $data['draftfile'];
        $this->assertInstanceOf(\stored_file::class, $file);
        $this->assertSame('draft', $file->get_filearea());
        $this->assertSame(\context_user::instance($this->user->id)->id, (int)$file->get_contextid());
        $this->assertStringEndsWith('.png', $file->get_filename());
        $imageinfo = getimagesizefromstring($file->get_content());
        $this->assertSame('image/png', $imageinfo['mime']);
        $this->assertSame([320, 240], [$imageinfo[0], $imageinfo[1]]);
        // The watermark changed the picture.
        $this->assertNotSame(sha1($png), sha1($file->get_content()));
        $this->assertSame('A lighthouse, early light', $data['revisedprompt']);
        $this->assertSame('the-image-model', $data['model']);
    }

    /**
     * An answer with a URL is downloaded and stored the same way.
     */
    public function test_an_answer_with_a_url_is_downloaded(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append($this->answer(['url' => 'https://example.com/generated/lighthouse.png']));
        $mock->append(new Response(200, ['Content-Type' => 'image/png'], $this->png()));

        $result = (new process_generate_image($this->provider(), $this->action()))->process();

        $this->assertTrue($result->get_success());
        $data = $result->get_response_data();
        $this->assertInstanceOf(\stored_file::class, $data['draftfile']);
        $this->assertSame('image/png', getimagesizefromstring($data['draftfile']->get_content())['mime']);
        $this->assertSame('https://example.com/generated/lighthouse.png', $data['sourceurl']);
    }

    /**
     * An answer without an image is an error with a translated message, and no file is stored.
     */
    public function test_an_answer_without_an_image_is_an_error(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], json_encode(['created' => 1, 'data' => []])));

        $result = (new process_generate_image($this->provider(), $this->action()))->process();

        $this->assertFalse($result->get_success());
        $this->assertSame(502, $result->get_errorcode());
        $this->assertSame(get_string('error:noimage', 'aiprovider_wunderbyte'), $result->get_errormessage());
    }

    /**
     * Data that is no image cannot be stored: an error with a translated message instead of an exception.
     */
    public function test_data_that_is_no_image_is_an_error(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append($this->answer(['b64_json' => base64_encode('this is text, not a picture')]));

        $result = (new process_generate_image($this->provider(), $this->action()))->process();

        $this->assertFalse($result->get_success());
        $this->assertSame(get_string('error:imagenotstored', 'aiprovider_wunderbyte'), $result->get_errormessage());
        $this->assertDebuggingCalled();
    }

    /**
     * An error of the service keeps its status.
     */
    public function test_an_error_of_the_service_keeps_its_status(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => ['message' => 'Invalid Authentication']]),
        ));

        $result = (new process_generate_image($this->provider(), $this->action()))->process();

        $this->assertFalse($result->get_success());
        $this->assertSame(401, $result->get_errorcode());
    }
}
