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

/**
 * External service: read the budget/usage of a Wunderbyte AI provider instance.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace aiprovider_wunderbyte\external;

use aiprovider_wunderbyte\local\usage;
use aiprovider_wunderbyte\provider;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns normalised LiteLLM key usage for a Wunderbyte provider instance.
 *
 * This is the single API every UI placement (provider settings page, agent
 * interface, ...) calls to render the usage bar, so the data contract stays
 * identical everywhere.
 */
class get_usage extends external_api {
    /** @var string[] Actions whose serving instance is the site's AI credit, in priority order. */
    private const SERVING_ACTIONS = [
        \aiprovider_wunderbyte\aiactions\planner_decide::class,
        \aiprovider_wunderbyte\aiactions\generate_agent_reply::class,
        \core_ai\aiactions\generate_text::class,
    ];

    /**
     * Describe input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'providerid' => new external_value(
                PARAM_INT,
                'Provider instance id; 0 for the Wunderbyte instance that serves the agent.',
                VALUE_DEFAULT,
                0,
            ),
        ]);
    }

    /**
     * Read the usage for the requested (or active) Wunderbyte provider instance.
     *
     * @param int $providerid
     * @return array
     */
    public static function execute(int $providerid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'providerid' => $providerid,
        ]);

        // Usage exposes organisation-level spend: gate it at system level.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('aiprovider/wunderbyte:viewusage', $context);

        $provider = self::resolve_provider((int)$params['providerid']);
        if ($provider === null) {
            return usage::unavailable('noprovider')->to_array();
        }

        return $provider->get_key_usage()->to_array();
    }

    /**
     * Resolve a Wunderbyte provider instance object.
     *
     * With an id, exactly that instance. Without one (placements that show the site's credit, e.g. the agent),
     * the instance core_ai actually uses for the agent's calls: the first enabled and configured Wunderbyte
     * instance in the admin's provider order that has the planner action enabled, then the reply action, then
     * generate_text. Only if none serves an action, the first enabled instance in provider order.
     *
     * @param int $providerid Specific instance id, or 0 for the instance that serves the agent.
     * @return provider|null
     */
    private static function resolve_provider(int $providerid): ?provider {
        $manager = \core\di::get(\core_ai\manager::class);
        if ($providerid > 0) {
            $instances = $manager->get_provider_instances(['provider' => provider::class, 'id' => $providerid]);
            $instance = reset($instances);
            return $instance instanceof provider ? $instance : null;
        }

        foreach (self::SERVING_ACTIONS as $actionclass) {
            if (!class_exists($actionclass)) {
                continue;
            }
            $providers = $manager->get_providers_for_actions([$actionclass], true);
            foreach ($providers[$actionclass] ?? [] as $instance) {
                if ($instance instanceof provider) {
                    return $instance;
                }
            }
        }

        foreach ($manager->get_sorted_providers() as $instance) {
            if ($instance instanceof provider && $instance->enabled) {
                return $instance;
            }
        }
        return null;
    }

    /**
     * Describe return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'available' => new external_value(PARAM_BOOL, 'Whether usage data could be read.'),
            'unlimited' => new external_value(PARAM_BOOL, 'Whether the key has no budget cap ("no limit").'),
            'spend' => new external_value(PARAM_FLOAT, 'Amount spent in the current window.', VALUE_OPTIONAL),
            'maxbudget' => new external_value(PARAM_FLOAT, 'Budget cap for the current window.', VALUE_OPTIONAL),
            'remaining' => new external_value(PARAM_FLOAT, 'Remaining budget.', VALUE_OPTIONAL),
            'currency' => new external_value(PARAM_ALPHA, 'Currency code budgets are denominated in.'),
            'budgetduration' => new external_value(PARAM_RAW, 'LiteLLM budget_duration, e.g. "14d".', VALUE_OPTIONAL),
            'resetat' => new external_value(PARAM_INT, 'Unix timestamp when spend resets.', VALUE_OPTIONAL),
            'expiresat' => new external_value(PARAM_INT, 'Unix timestamp when the key expires.', VALUE_OPTIONAL),
            'percentused' => new external_value(PARAM_FLOAT, 'Percent of budget spent (0-100).', VALUE_OPTIONAL),
            'error' => new external_value(PARAM_ALPHANUMEXT, 'Machine-readable error code when unavailable.', VALUE_OPTIONAL),
            'detail' => new external_value(PARAM_RAW, 'Human-readable diagnostic detail (no secrets).', VALUE_OPTIONAL),
            'shopurl' => new external_value(PARAM_URL, 'URL where new AI credit can be purchased.', VALUE_OPTIONAL),
        ]);
    }
}
