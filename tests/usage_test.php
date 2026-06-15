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

/**
 * Unit tests for the usage data contract.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_wunderbyte\local\usage
 */
final class usage_test extends \advanced_testcase {
    /**
     * A capped budget produces remaining + percent figures.
     */
    public function test_capped_budget(): void {
        $usage = usage::from_key_info([
            'spend' => 0.83,
            'max_budget' => 2.0,
            'budget_duration' => '14d',
            'budget_reset_at' => '2026-06-29T00:00:00Z',
        ]);

        $this->assertTrue($usage->available);
        $this->assertFalse($usage->unlimited);
        $this->assertEqualsWithDelta(1.17, $usage->remaining, 0.0001);
        $this->assertEqualsWithDelta(41.5, $usage->percent_used(), 0.1);
        $this->assertSame('14d', $usage->budgetduration);
        $this->assertSame(strtotime('2026-06-29T00:00:00Z'), $usage->resetat);

        $array = $usage->to_array();
        $this->assertFalse($array['unlimited']);
        $this->assertEqualsWithDelta(1.17, $array['remaining'], 0.0001);
    }

    /**
     * A null max_budget means "no limit": no remaining, no percentage.
     */
    public function test_no_limit_null_budget(): void {
        $usage = usage::from_key_info([
            'spend' => 5.0,
            'max_budget' => null,
        ]);

        $this->assertTrue($usage->available);
        $this->assertTrue($usage->unlimited);
        $this->assertNull($usage->remaining);
        $this->assertNull($usage->maxbudget);
        $this->assertNull($usage->percent_used());
        $this->assertTrue($usage->to_array()['unlimited']);
    }

    /**
     * An absent max_budget key is also treated as "no limit".
     */
    public function test_no_limit_absent_budget(): void {
        $usage = usage::from_key_info(['spend' => 1.0]);

        $this->assertTrue($usage->unlimited);
        $this->assertNull($usage->remaining);
    }

    /**
     * A fresh key with no spend reported defaults spend to zero.
     */
    public function test_fresh_key_defaults_spend(): void {
        $usage = usage::from_key_info(['max_budget' => 2.0]);

        $this->assertSame(0.0, $usage->spend);
        $this->assertEqualsWithDelta(2.0, $usage->remaining, 0.0001);
        $this->assertEqualsWithDelta(0.0, $usage->percent_used(), 0.0001);
    }

    /**
     * The unavailable factory yields an error state with no figures.
     */
    public function test_unavailable(): void {
        $usage = usage::unavailable('http');

        $this->assertFalse($usage->available);
        $this->assertSame('http', $usage->error);
        $this->assertNull($usage->spend);
        $this->assertNull($usage->percent_used());
        $this->assertFalse($usage->to_array()['available']);
    }
}
