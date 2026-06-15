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

/**
 * Reusable "AI credit" usage bar.
 *
 * Fetches normalised usage data from the aiprovider_wunderbyte_get_usage web
 * service and renders the aiprovider_wunderbyte/usage_bar template into a target
 * node. This is the single presentation/formatting path, so every placement
 * (provider settings page, agent interface, ...) shows an identical bar by
 * calling init() with a container.
 *
 * @module     aiprovider_wunderbyte/usage_bar
 * @copyright  2026 Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import {getString} from 'core/str';
import Templates from 'core/templates';
import Log from 'core/log';

const TEMPLATE = 'aiprovider_wunderbyte/usage_bar';
const TEMPLATE_COMPACT = 'aiprovider_wunderbyte/usage_bar_compact';
const COMPONENT = 'aiprovider_wunderbyte';

/**
 * Resolve a target into a DOM node.
 *
 * @param {HTMLElement|string} target A node or a selector.
 * @return {HTMLElement|null}
 */
const resolveNode = (target) => {
    if (typeof target === 'string') {
        return document.querySelector(target);
    }
    return target instanceof HTMLElement ? target : null;
};

/**
 * Format an amount in the given currency using the browser locale.
 *
 * @param {number} amount The amount.
 * @param {string} currency ISO currency code.
 * @return {string}
 */
const formatMoney = (amount, currency) => {
    try {
        return new Intl.NumberFormat(undefined, {style: 'currency', currency}).format(amount);
    } catch (e) {
        // Unknown currency code: fall back to a plain number plus the code.
        return `${Number(amount).toFixed(2)} ${currency}`;
    }
};

/**
 * Format a unix timestamp as a localised date.
 *
 * @param {number} timestamp Seconds since the epoch.
 * @return {string}
 */
const formatDate = (timestamp) => new Date(timestamp * 1000).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
});

/**
 * Whole days from now until a unix timestamp (0 if already due/past).
 *
 * @param {number} timestamp Seconds since the epoch.
 * @return {number}
 */
const daysUntil = (timestamp) => Math.max(0, Math.ceil((timestamp * 1000 - Date.now()) / 86400000));

/**
 * Pick a bar colour from the spent percentage.
 *
 * @param {number} percent Percent of budget used.
 * @return {string} Bootstrap background class.
 */
const barClass = (percent) => {
    if (percent < 60) {
        return 'bg-success';
    }
    if (percent < 85) {
        return 'bg-warning';
    }
    return 'bg-danger';
};

/**
 * Map raw web-service usage data to the template context.
 *
 * @param {object} data The normalised usage payload.
 * @return {Promise<object>} The mustache context.
 */
const buildContext = async(data) => {
    const heading = await getString('usage_heading', COMPONENT);

    // Could not read usage: configuration missing vs. transient/other error.
    if (!data.available) {
        const key = data.error === 'unconfigured' ? 'usage_unconfigured' : 'usage_unavailable';
        return {
            heading,
            ismessage: true,
            message: await getString(key, COMPONENT),
            detail: data.detail || '',
        };
    }

    // No spending cap on the key ("no limit").
    if (data.unlimited) {
        const [unlimitedlabel, unlimiteddetail] = await Promise.all([
            getString('usage_unlimited', COMPONENT),
            getString('usage_unlimited_detail', COMPONENT),
        ]);
        return {heading, isunlimited: true, unlimitedlabel, unlimiteddetail};
    }

    // Normal capped budget.
    const currency = data.currency || 'USD';
    const percent = Math.round(data.percentused || 0);
    const [remaininglabel, spentlabel] = await Promise.all([
        getString('usage_remaining', COMPONENT, {
            remaining: formatMoney(data.remaining, currency),
            total: formatMoney(data.maxbudget, currency),
        }),
        getString('usage_spent', COMPONENT, {spend: formatMoney(data.spend, currency)}),
    ]);

    const context = {
        heading,
        isok: true,
        percent,
        barclass: barClass(percent),
        // Compact (header) variant reuses the same colour thresholds as a badge.
        badgeclass: barClass(percent).replace('bg-', 'badge-'),
        shortlabel: await getString('usage_left', COMPONENT, formatMoney(data.remaining, currency)),
        remaininglabel,
        spentlabel,
        resetlabel: '',
        expireslabel: '',
    };

    if (data.resetat) {
        // Relative "resets in X days" reads better than an absolute date; handle
        // the today/tomorrow cases for correct grammar across languages.
        const days = daysUntil(data.resetat);
        if (days <= 0) {
            context.resetlabel = await getString('usage_reset_today', COMPONENT);
        } else if (days === 1) {
            context.resetlabel = await getString('usage_reset_tomorrow', COMPONENT);
        } else {
            context.resetlabel = await getString('usage_reset_days', COMPONENT, days);
        }
    }
    if (data.expiresat) {
        context.expireslabel = await getString('usage_expires', COMPONENT, formatDate(data.expiresat));
    }

    return context;
};

/**
 * Fetch usage data for a provider instance.
 *
 * @param {number} providerid Provider instance id, or 0 to auto-resolve.
 * @return {Promise<object>}
 */
const fetchUsage = (providerid) => fetchMany([{
    methodname: 'aiprovider_wunderbyte_get_usage',
    args: {providerid},
}])[0];

/**
 * Render (or re-render) the usage bar into a node.
 *
 * @param {HTMLElement|string} target Container node or selector.
 * @param {number} [providerid=0] Provider instance id, 0 to auto-resolve.
 * @param {object} [options] Render options.
 * @param {boolean} [options.compact=false] Render the compact (header) variant.
 * @return {Promise<void>}
 */
export const render = async(target, providerid = 0, options = {}) => {
    const node = resolveNode(target);
    if (!node) {
        return;
    }
    try {
        const data = await fetchUsage(parseInt(providerid, 10) || 0);
        const context = await buildContext(data);
        const template = options.compact ? TEMPLATE_COMPACT : TEMPLATE;
        const {html, js} = await Templates.renderForPromise(template, context);
        Templates.replaceNodeContents(node, html, js);
    } catch (error) {
        // A usage widget must never break the host page; log and leave it empty.
        Log.debug(`aiprovider_wunderbyte/usage_bar: ${error.message || error}`);
    }
};

/**
 * Initialise the usage bar in a container.
 *
 * @param {HTMLElement|string} target Container node or selector.
 * @param {number} [providerid=0] Provider instance id, 0 to auto-resolve.
 * @param {object} [options] Init options.
 * @param {boolean} [options.compact=false] Render the compact (header) variant.
 * @param {boolean} [options.lazy=false] Defer the fetch until the node is visible.
 */
export const init = (target, providerid = 0, options = {}) => {
    const node = resolveNode(target);
    if (!node) {
        return;
    }
    const pid = parseInt(providerid, 10) || 0;
    const renderoptions = {compact: !!options.compact};

    // Lazy: only fetch usage once the container actually scrolls into view
    // (e.g. when the agent panel becomes visible).
    if (options.lazy && typeof IntersectionObserver !== 'undefined') {
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    obs.disconnect();
                    render(node, pid, renderoptions);
                }
            });
        });
        observer.observe(node);
        return;
    }

    render(node, pid, renderoptions);
};

/**
 * Initialise the usage bar on a provider settings form, reading the instance id
 * from the form's hidden "id" input at runtime.
 *
 * The settings form dispatches its hook before adding the hidden id element, so
 * the id is not known when the container is injected; reading it from the live
 * DOM avoids that ordering problem and targets the exact instance being edited.
 *
 * @param {HTMLElement|string} target Container node or selector.
 */
export const initFromForm = (target) => {
    const node = resolveNode(target);
    if (!node) {
        return;
    }
    const form = node.closest('form');
    const idinput = form ? form.querySelector('input[name="id"]') : null;
    const providerid = idinput ? parseInt(idinput.value, 10) || 0 : 0;
    render(node, providerid);
};
