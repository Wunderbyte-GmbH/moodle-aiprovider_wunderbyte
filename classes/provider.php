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

use core_ai\form\action_settings_form;
use Psr\Http\Message\RequestInterface;

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
}
