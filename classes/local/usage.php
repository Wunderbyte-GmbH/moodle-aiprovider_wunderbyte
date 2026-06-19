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

namespace aiprovider_wunderbyte\local;

/**
 * Normalised representation of a LiteLLM virtual key's budget/usage.
 *
 * This is the canonical data contract for usage information. It is produced by
 * {@see \aiprovider_wunderbyte\provider::get_key_usage()} and consumed by the
 * external service, the renderable and (indirectly) every UI placement, so the
 * shape stays identical wherever the bar is shown.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage {
    /**
     * Constructor. Use the named factory methods instead of calling directly.
     *
     * @param bool $available Whether usage data could be read at all.
     * @param bool $unlimited Whether the key has no budget cap ("no limit").
     * @param float|null $spend Amount spent in the current budget window.
     * @param float|null $maxbudget Budget cap for the current window (null when unlimited).
     * @param float|null $remaining Remaining budget (null when unlimited/unknown).
     * @param string $currency ISO currency code budgets are denominated in.
     * @param string|null $budgetduration LiteLLM budget_duration string, e.g. "14d", "1mo".
     * @param int|null $resetat Unix timestamp when spend resets, or null.
     * @param int|null $expiresat Unix timestamp when the key expires, or null.
     * @param string|null $error Machine-readable error code when not available.
     */
    private function __construct(
        /** @var bool Whether usage data could be read at all. */
        public readonly bool $available,
        /** @var bool Whether the key has no budget cap ("no limit"). */
        public readonly bool $unlimited,
        /** @var float|null Amount spent in the current budget window. */
        public readonly ?float $spend,
        /** @var float|null Budget cap for the current window (null when unlimited). */
        public readonly ?float $maxbudget,
        /** @var float|null Remaining budget (null when unlimited/unknown). */
        public readonly ?float $remaining,
        /** @var string ISO currency code budgets are denominated in. */
        public readonly string $currency,
        /** @var string|null LiteLLM budget_duration string, e.g. "14d", "1mo". */
        public readonly ?string $budgetduration,
        /** @var int|null Unix timestamp when spend resets, or null. */
        public readonly ?int $resetat,
        /** @var int|null Unix timestamp when the key expires, or null. */
        public readonly ?int $expiresat,
        /** @var float|null Percent of budget used (0-100), or null when unlimited/unknown. */
        public readonly ?float $percentused,
        /** @var string|null Machine-readable error code when not available. */
        public readonly ?string $error,
        /** @var string|null Human-readable diagnostic detail (never contains secrets). */
        public readonly ?string $detail = null,
    ) {
    }

    /**
     * Build a usage object from a LiteLLM /key/info "info" payload.
     *
     * Handles the "no limit" case: when LiteLLM reports a null max_budget the
     * key is uncapped, so remaining is undefined and {@see $unlimited} is set.
     *
     * @param array $info The decoded "info" object from /key/info.
     * @param string $currency Currency the budgets are denominated in.
     * @return self
     */
    public static function from_key_info(array $info, string $currency = 'USD'): self {
        // Spend is always a number; LiteLLM defaults it to 0 for fresh keys.
        $spend = isset($info['spend']) ? (float)$info['spend'] : 0.0;

        // A null/absent max_budget means the key is uncapped ("no limit").
        $hasbudget = array_key_exists('max_budget', $info) && $info['max_budget'] !== null;
        $maxbudget = $hasbudget ? (float)$info['max_budget'] : null;
        $remaining = $hasbudget ? max(0.0, $maxbudget - $spend) : null;
        $percentused = ($hasbudget && $maxbudget > 0)
            ? min(100.0, max(0.0, ($spend / $maxbudget) * 100))
            : null;

        return new self(
            available: true,
            unlimited: !$hasbudget,
            spend: $spend,
            maxbudget: $maxbudget,
            remaining: $remaining,
            currency: $currency,
            budgetduration: !empty($info['budget_duration']) ? (string)$info['budget_duration'] : null,
            resetat: self::to_timestamp($info['budget_reset_at'] ?? null),
            expiresat: self::to_timestamp($info['expires'] ?? null),
            percentused: $percentused,
            error: null,
        );
    }

    /**
     * Build a usage object from the privacy-preserving gateway response.
     *
     * The gateway ({@see POST /api/shop/usage}) deliberately returns only a
     * percentage and reset/expiry timing — never euro amounts — so this object
     * carries no money fields (spend/maxbudget/remaining stay null).
     *
     * @param array $payload Decoded gateway response: {state, percent, resetat, expiresat}.
     * @return self
     */
    public static function from_gateway(array $payload): self {
        $state = (string)($payload['state'] ?? 'unavailable');
        if ($state === 'unavailable') {
            return self::unavailable('gateway');
        }
        $unlimited = ($state === 'unlimited');
        $percent = (!$unlimited && isset($payload['percent']) && $payload['percent'] !== null)
            ? (float)$payload['percent']
            : null;

        return new self(
            available: true,
            unlimited: $unlimited,
            spend: null,
            maxbudget: null,
            remaining: null,
            currency: 'EUR',
            budgetduration: null,
            resetat: self::to_timestamp($payload['resetat'] ?? null),
            expiresat: self::to_timestamp($payload['expiresat'] ?? null),
            percentused: $percent,
            error: null,
        );
    }

    /**
     * Build a usage object representing "could not read usage".
     *
     * @param string $error Machine-readable error code (e.g. 'unconfigured', 'http', 'unsupported').
     * @param string|null $detail Human-readable diagnostic detail (must not contain secrets).
     * @return self
     */
    public static function unavailable(string $error, ?string $detail = null): self {
        return new self(
            available: false,
            unlimited: false,
            spend: null,
            maxbudget: null,
            remaining: null,
            currency: 'USD',
            budgetduration: null,
            resetat: null,
            expiresat: null,
            percentused: null,
            error: $error,
            detail: $detail,
        );
    }

    /**
     * Convert a LiteLLM timestamp (ISO-8601 string or epoch) to a Unix timestamp.
     *
     * @param mixed $value The raw value from the API.
     * @return int|null
     */
    private static function to_timestamp(mixed $value): ?int {
        if (empty($value)) {
            return null;
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        $parsed = strtotime((string)$value);
        return $parsed !== false ? $parsed : null;
    }

    /**
     * Percentage of the budget already spent (0-100), or null when unlimited/unknown.
     *
     * @return float|null
     */
    public function percent_used(): ?float {
        return $this->percentused;
    }

    /**
     * Flat associative array form, suitable for the external service return value.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'available' => $this->available,
            'unlimited' => $this->unlimited,
            'spend' => $this->spend,
            'maxbudget' => $this->maxbudget,
            'remaining' => $this->remaining,
            'currency' => $this->currency,
            'budgetduration' => $this->budgetduration,
            'resetat' => $this->resetat,
            'expiresat' => $this->expiresat,
            'percentused' => $this->percent_used(),
            'error' => $this->error,
            'detail' => $this->detail,
        ];
    }
}
