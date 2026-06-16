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

use aiprovider_wunderbyte\local\usage;
use core\http_client;
use core_ai\form\action_settings_form;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Wunderbyte AI provider.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /**
     * Get the list of actions supported by this provider.
     *
     * @return array
     */
    public static function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
            \aiprovider_wunderbyte\aiactions\generate_embeddings::class,
            \aiprovider_wunderbyte\aiactions\planner_decide::class,
            \aiprovider_wunderbyte\aiactions\generate_agent_reply::class,
        ];
    }

    #[\Override]
    public function add_authentication_headers(RequestInterface $request): RequestInterface {
        return $request->withAddedHeader('Authorization', 'Bearer ' . $this->config['apikey']);
    }

    #[\Override]
    public static function get_action_settings(string $action, array $customdata = []): action_settings_form|bool {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $customdata['actionname'] = $actionname;
        $customdata['action'] = $action;
        $customdata['providername'] = 'aiprovider_wunderbyte';

        if ($actionname === 'generate_embeddings') {
            return new form\action_embeddings_form(customdata: $customdata);
        }

        if ($actionname === 'planner_decide' || $actionname === 'generate_agent_reply'
                || $actionname === 'generate_text') {
            return new form\action_chat_form(customdata: $customdata);
        }

        return false;
    }

    #[\Override]
    public static function get_action_setting_defaults(string $action): array {
        $actionname = substr($action, (strrpos($action, '\\') + 1));
        $customdata = [
            'actionname' => $actionname,
            'action' => $action,
            'providername' => 'aiprovider_wunderbyte',
        ];

        if ($actionname === 'generate_embeddings') {
            $mform = new form\action_embeddings_form(customdata: $customdata);
            return $mform->get_defaults();
        }

        if ($actionname === 'planner_decide' || $actionname === 'generate_agent_reply'
                || $actionname === 'generate_text') {
            $mform = new form\action_chat_form(customdata: $customdata);
            return $mform->get_defaults();
        }

        return [];
    }

    /**
     * Check this provider has the minimal configuration to work.
     *
     * @return bool
     */
    public function is_provider_configured(): bool {
        return !empty($this->config['apikey']);
    }

    /**
     * Read the current budget/usage for this provider instance's API key.
     *
     * This is a management read against the LiteLLM proxy (GET /key/info), not
     * an AI inference action: it consumes no tokens, produces no content and is
     * deliberately NOT modelled as a core_ai aiaction. It self-introspects using
     * the instance's own virtual key as the bearer token, so no master key is
     * required on the Moodle side.
     *
     * @return usage Normalised usage data; an "unavailable" instance on any error.
     */
    public function get_key_usage(): usage {
        if (empty($this->config['apikey'])) {
            return usage::unavailable('unconfigured');
        }

        $endpoint = $this->get_management_endpoint();
        if ($endpoint === null) {
            return usage::unavailable('unsupported', 'No action endpoint configured to derive the management URL from.');
        }

        $request = $this->add_authentication_headers(
            new Request('GET', $endpoint, ['Accept' => 'application/json']),
        );

        $client = \core\di::get(http_client::class);
        try {
            $response = $client->send($request, [RequestOptions::HTTP_ERRORS => false]);
        } catch (GuzzleException $e) {
            debugging('aiprovider_wunderbyte usage lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return usage::unavailable('http', 'Request to ' . $endpoint . ' failed: ' . $e->getMessage());
        }

        $status = $response->getStatusCode();
        if ($status !== 200) {
            return usage::unavailable('http', 'GET ' . $endpoint . ' returned HTTP ' . $status . '.');
        }

        $body = json_decode($response->getBody()->getContents(), true);
        if (!is_array($body)) {
            return usage::unavailable('badresponse', 'Response from ' . $endpoint . ' was not a JSON object.');
        }

        // LiteLLM returns {"key": "...", "info": {...}}; tolerate a flat shape too.
        $info = $body['info'] ?? $body;
        if (!is_array($info)) {
            return usage::unavailable('badresponse', 'Response had no "info" object.');
        }

        // The Wunderbyte LiteLLM proxy denominates all budgets in EUR; /key/info
        // itself carries no currency code, so state it explicitly here.
        return usage::from_key_info($info, 'EUR');
    }

    /**
     * Derive the LiteLLM management endpoint (/key/info) from a configured action endpoint.
     *
     * The chat endpoint is e.g. https://llm.wunderbyte.at/v1/chat/completions; the
     * management API lives at the host root (https://llm.wunderbyte.at/key/info).
     * We therefore keep only scheme/host/port and append the management path.
     *
     * @return UriInterface|null Null when no usable endpoint is configured.
     */
    protected function get_management_endpoint(): ?UriInterface {
        $base = $this->get_configured_base_uri();
        if ($base === null) {
            return null;
        }
        return $base->withPath('/key/info')->withQuery('')->withFragment('');
    }

    /**
     * Find the first configured action endpoint and reduce it to its host root.
     *
     * @return UriInterface|null
     */
    private function get_configured_base_uri(): ?UriInterface {
        foreach ($this->actionconfig as $action) {
            $endpoint = $action['settings']['endpoint'] ?? '';
            if ($endpoint === '') {
                continue;
            }
            $uri = new Uri((string)$endpoint);
            if ($uri->getHost() !== '') {
                return $uri;
            }
        }
        return null;
    }
}
