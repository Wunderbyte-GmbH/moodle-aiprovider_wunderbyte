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

use core\http_client;
use core_ai\process_base;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Base processor for Wunderbyte OpenAI-compatible endpoints.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class abstract_processor extends process_base {
    /**
     * Get the endpoint URI.
     *
     * @return UriInterface
     */
    protected function get_endpoint(): UriInterface {
        return new Uri((string)$this->provider->actionconfig[$this->action::class]['settings']['endpoint']);
    }

    /**
     * Get the model name.
     *
     * @return string
     */
    protected function get_model(): string {
        return (string)($this->provider->actionconfig[$this->action::class]['settings']['model'] ?? '');
    }

    /**
     * Get extra model settings.
     *
     * @return array
     */
    protected function get_model_settings(): array {
        $settings = $this->provider->actionconfig[$this->action::class]['settings'];
        // Merge the free-form extra parameters (JSON, e.g. {"max_tokens": 500}) into the settings so they
        // are spread into the request body — this is how a hard output cap is enforced at the provider.
        if (!empty($settings['modelextraparams'])) {
            $params = json_decode($settings['modelextraparams'], true);
            if (is_array($params)) {
                foreach ($params as $key => $value) {
                    $settings[$key] = $value;
                }
            }
        }
        // Strip control/meta keys that are NOT model parameters before they get spread into the request
        // body: model/endpoint/systeminstruction are consumed separately, providerid is a Moodle-internal
        // form field that must never reach the LLM API (OpenAI-style endpoints reject it outright with
        // "Unknown parameter: 'providerid'", which empties the response — e.g. the gpt-5-mini route), and
        // modelextraparams is the raw JSON container, already unpacked above.
        unset(
            $settings['model'],
            $settings['endpoint'],
            $settings['systeminstruction'],
            $settings['providerid'],
            $settings['modelextraparams'],
        );
        return $settings;
    }

    /**
     * Get the system instruction.
     *
     * @return string
     */
    protected function get_system_instruction(): string {
        return (string)$this->action::get_system_instruction();
    }

    /**
     * Create the request object.
     *
     * @param string $userid The user id.
     * @return RequestInterface
     */
    abstract protected function create_request_object(string $userid): RequestInterface;

    /**
     * Handle a successful response.
     *
     * @param ResponseInterface $response The response.
     * @return array
     */
    abstract protected function handle_api_success(ResponseInterface $response): array;

    /**
     * Identify this site in the request payload via LiteLLM request metadata.
     *
     * The proxy uses metadata.moodle_site to attribute API keys to the Moodle sites
     * actually using them (key management and support) - no separate call, the value
     * rides on the requests that are sent anyway. The transmission is documented in
     * the privacy provider and in README "Data & privacy". `metadata` is a
     * first-class request field on LiteLLM and the OpenAI chat API.
     *
     * @param RequestInterface $request
     * @return RequestInterface
     */
    protected function attach_site_metadata(RequestInterface $request): RequestInterface {
        global $CFG;

        $body = json_decode((string)$request->getBody());
        if (!is_object($body)) {
            return $request;
        }
        $metadata = (array)($body->metadata ?? []);
        $metadata['moodle_site'] = (string)$CFG->wwwroot;
        $body->metadata = (object)$metadata;

        return $request->withBody(\GuzzleHttp\Psr7\Utils::streamFor(json_encode($body)));
    }

    #[\Override]
    protected function query_ai_api(): array {
        $request = $this->create_request_object(
            userid: $this->provider->generate_userid($this->action->get_configuration('userid')),
        );
        $request = $this->attach_site_metadata($request);
        $request = $this->provider->add_authentication_headers($request);

        $client = \core\di::get(http_client::class);
        try {
            $response = $client->send($request, [
                'base_uri' => $this->get_endpoint(),
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (RequestException $e) {
            return \core_ai\error\factory::create($e->getCode(), $e->getMessage())->get_error_details();
        }

        if ($response->getStatusCode() === 200) {
            return $this->handle_api_success($response);
        }

        return $this->handle_api_error($response);
    }

    /**
     * Handle an API error.
     *
     * @param ResponseInterface $response The response.
     * @return array
     */
    protected function handle_api_error(ResponseInterface $response): array {
        $status = $response->getStatusCode();

        // Rate limit (429) or temporary overload (503): the user is not at fault and the
        // condition is transient. Show ONE clear, localized, actionable message instead of
        // a generic provider error. We return the curated string directly (core_ai hides
        // the provider message in non-developer mode), so the user always gets the guidance.
        if ($status === 429 || $status === 503) {
            $message = get_string('error:busy', 'aiprovider_wunderbyte');
            return [
                'success' => false,
                'errorcode' => $status,
                'error' => $message,
                'errormessage' => $message,
            ];
        }

        if ($status >= 500 && $status < 600) {
            $errormessage = $response->getReasonPhrase();
        } else {
            $bodyobj = json_decode($response->getBody()->getContents());
            $errormessage = $bodyobj->error->message ?? $response->getReasonPhrase();
        }

        return \core_ai\error\factory::create($status, $errormessage)->get_error_details();
    }
}
