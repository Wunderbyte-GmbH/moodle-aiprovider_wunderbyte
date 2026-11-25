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

use aiprovider_wunderbyte\aiactions\planner_decide;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * The detail line of the AI credit bar comes from the language pack.
 *
 * When the usage of a key cannot be read, the bar shows a detail below its message (templates usage_bar and
 * usage_bar_compact). That text is shown to the administrator, so it is a language string and no fixed English text.
 *
 * @package    aiprovider_wunderbyte
 * @category   test
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\provider::get_key_usage
 */
final class usage_detail_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * A provider instance with a key and one action.
     *
     * @param array $settings The settings of the action.
     * @return provider
     */
    private function instance(array $settings): provider {
        return \core\di::get(\core_ai\manager::class)->create_provider_instance(
            classname: '\aiprovider_wunderbyte\provider',
            name: 'usage',
            enabled: true,
            config: ['apikey' => 'key'],
            actionconfig: [
                planner_decide::class => ['enabled' => true, 'settings' => $settings],
            ],
        );
    }

    /**
     * A provider instance whose action has an endpoint.
     *
     * @return provider
     */
    private function instance_with_endpoint(): provider {
        return $this->instance(['model' => 'm', 'endpoint' => 'https://llm.example.com/v1/chat/completions']);
    }

    /**
     * Without an endpoint the address of the usage service is unknown.
     */
    public function test_no_endpoint(): void {
        $usage = $this->instance(['model' => 'm'])->get_key_usage()->to_array();

        $this->assertFalse($usage['available']);
        $this->assertSame('unsupported', $usage['error']);
        $this->assertSame(get_string('usage_detail_noendpoint', 'aiprovider_wunderbyte'), $usage['detail']);
    }

    /**
     * An answer that is not HTTP 200 names its status.
     */
    public function test_http_status(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(503));

        $usage = $this->instance_with_endpoint()->get_key_usage()->to_array();

        $this->assertFalse($usage['available']);
        $this->assertSame('http', $usage['error']);
        $this->assertSame(get_string('usage_detail_httpstatus', 'aiprovider_wunderbyte', 503), $usage['detail']);
    }

    /**
     * An answer that is no JSON object.
     */
    public function test_unreadable_answer(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, ['Content-Type' => 'text/html'], '<html>maintenance</html>'));

        $usage = $this->instance_with_endpoint()->get_key_usage()->to_array();

        $this->assertFalse($usage['available']);
        $this->assertSame('badresponse', $usage['error']);
        $this->assertSame(get_string('usage_detail_badresponse', 'aiprovider_wunderbyte'), $usage['detail']);
    }

    /**
     * A request that fails carries the message of the failure inside the translated sentence.
     */
    public function test_failed_request(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new ConnectException('no route to host', new Request('POST', 'https://llm.example.com/api/shop/usage')));

        $usage = $this->instance_with_endpoint()->get_key_usage()->to_array();

        $this->assertFalse($usage['available']);
        $this->assertSame('http', $usage['error']);
        $this->assertSame(
            get_string('usage_detail_requestfailed', 'aiprovider_wunderbyte', 'no route to host'),
            $usage['detail'],
        );
        $this->assertDebuggingCalled();
    }
}
