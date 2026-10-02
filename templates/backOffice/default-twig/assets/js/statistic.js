// Statistics tabs of the back office (home and tool page). Each [data-statistic-panel] reads its period and its
// filters, asks the module controllers for the data and draws it with chart.js, the chart library of the
// default-twig dashboard, with the same look. chart.js and bootstrap come from the back-office importmap.
import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    BarController,
    BarElement,
    LinearScale,
    CategoryScale,
    Tooltip,
    Legend,
    Filler,
} from 'chart.js';
import { Modal } from 'bootstrap';

Chart.register(LineController, LineElement, PointElement, BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend, Filler);

// Colours of the dashboard charts (default-twig bo-chart controller), then of the compared series.
const BRAND = '#f26041';
const BRAND_SOFT = 'rgba(242, 96, 65, 0.15)';
const GRID = 'rgba(0, 0, 0, 0.06)';
const SERIES_COLORS = [BRAND, '#5bc0de', '#5cb85c', '#f39922', '#986dff', '#6c757d'];

const PAGE_SIZE = 30;
const LOCALE = (document.documentElement.lang || 'fr-FR').replace('_', '-');

const pad = (value) => String(value).padStart(2, '0');
const isoDate = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const parseIsoDate = (value) => {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
};

/**
 * Number formatter of an indicator: amounts in the shop currency, counts as plain numbers.
 */
export function numberFormatter(format, currency = 'EUR', fractionDigits = 2) {
    const options = format === 'currency'
        ? { style: 'currency', currency, minimumFractionDigits: fractionDigits, maximumFractionDigits: fractionDigits }
        : { maximumFractionDigits: fractionDigits };
    const formatter = new Intl.NumberFormat(LOCALE, options);

    return (value) => formatter.format(Number(value) || 0);
}

/**
 * Draws a chart with the look of the dashboard charts. Exported for the tabs other modules add to the statistics
 * (`statistic.tab`): import it from the URL of this file.
 *
 * @param {HTMLCanvasElement} canvas
 * @param {{type?: 'line'|'bar', labels: string[], datasets: {label?: string, data: number[]}[], format?: string, currency?: string}} definition
 */
export function createChart(canvas, { type = 'line', labels, datasets, format = 'number', currency = 'EUR' }) {
    const axis = numberFormatter(format, currency, 0);
    const value = numberFormatter(format, currency, 2);
    const several = datasets.length > 1;

    return new Chart(canvas.getContext('2d'), {
        type,
        data: {
            labels,
            datasets: datasets.map((dataset, index) => {
                const color = SERIES_COLORS[index % SERIES_COLORS.length];

                return {
                    label: dataset.label ?? '',
                    data: dataset.data,
                    borderColor: color,
                    backgroundColor: type === 'bar' ? color : (index === 0 ? BRAND_SOFT : 'transparent'),
                    borderWidth: 2,
                    fill: type === 'line' && index === 0,
                    tension: 0.3,
                    pointRadius: 2,
                    pointHoverRadius: 4,
                };
            }),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: several, position: 'top', align: 'end', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: { label: (context) => ` ${context.dataset.label || ''} ${value(context.parsed.y)}` },
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: {
                    grid: { color: GRID },
                    ticks: { font: { size: 11 }, callback: (tick) => axis(tick) },
                    beginAtZero: true,
                },
            },
        },
    });
}

// Label of the x axis at an index: the controllers send "x", [x] or [index, x].
const labelAt = (labels, index) => {
    const entry = labels?.[index];
    if (entry === undefined || entry === null) {
        return String(index + 1);
    }

    return String(Array.isArray(entry) ? entry[entry.length - 1] : entry);
};

const sumOf = (graph) => Math.round((graph ?? []).reduce((total, point) => total + (Number(point[1]) || 0), 0) * 100) / 100;

// "1 999,96 €", "12.5" or 4 as a number.
const parseAmount = (value) => {
    if (typeof value === 'number') {
        return value;
    }
    const number = Number(String(value).replace(/[\s  €$£]/g, '').replace(',', '.'));

    return Number.isFinite(number) ? number : 0;
};

const element = (tag, attributes = {}, text = null) => {
    const node = document.createElement(tag);
    Object.entries(attributes).forEach(([name, value]) => node.setAttribute(name, value));
    if (text !== null) {
        node.textContent = String(text);
    }

    return node;
};

/**
 * Start and end of the shortcuts of the period selector, as the Thelia 2 statistics computed them.
 */
const presetRange = (preset) => {
    const start = new Date();
    const end = new Date();

    switch (preset) {
        case 'day':
            break;
        case 'month':
            start.setMonth(start.getMonth() - 1);
            break;
        case 'year':
            start.setFullYear(start.getFullYear() - 1);
            break;
        case 'last-day':
            start.setDate(start.getDate() - 1);
            end.setTime(start.getTime());
            break;
        case 'last-month':
            start.setMonth(start.getMonth() - 2);
            end.setMonth(end.getMonth() - 1);
            break;
        case 'last-year':
            start.setFullYear(start.getFullYear() - 2);
            end.setFullYear(end.getFullYear() - 1);
            break;
        default:
            return null;
    }

    return [start, end];
};

const setActive = (button, active) => {
    button.classList.toggle('btn-primary', active);
    button.classList.toggle('btn-outline-secondary', !active);
    button.setAttribute('aria-pressed', active ? 'true' : 'false');
};

class StatisticPanel {
    constructor(root) {
        this.root = root;
        this.currency = root.dataset.currency || 'EUR';
        this.start = root.querySelector('[data-stat-start]');
        this.end = root.querySelector('[data-stat-end]');
        this.ghostButton = root.querySelector('[data-stat-ghost]');
        this.presets = [...root.querySelectorAll('[data-stat-preset]')];
        this.indicators = [...root.querySelectorAll('[data-stat-indicator]')];
        this.params = [...root.querySelectorAll('[data-stat-param]')];
        this.years = [...root.querySelectorAll('[data-stat-year]')];
        this.chartCard = root.querySelector('[data-stat-chart-card]');
        this.tableCard = root.querySelector('[data-stat-table-card]');
        this.chart = null;
        this.request = null;
        this.dirty = true;
        this.ghost = false;
        this.bestSales = null;

        this.initPeriod();
        this.bind();
    }

    initPeriod() {
        if (this.start && this.end) {
            const range = presetRange(this.root.dataset.defaultPreset || 'month');
            this.start.value = isoDate(range[0]);
            this.end.value = isoDate(range[1]);
            this.syncPresets();
        }

        // Years compared: the previous one and the current one (Thelia 2).
        const year = new Date().getFullYear();
        this.years.forEach((input) => {
            input.value = String(input.dataset.statYear === 'first' ? year - 1 : year);
        });
    }

    bind() {
        this.presets.forEach((button) => button.addEventListener('click', () => {
            const range = presetRange(button.dataset.statPreset);
            this.start.value = isoDate(range[0]);
            this.end.value = isoDate(range[1]);
            this.syncPresets();
            this.refresh();
        }));

        [this.start, this.end].filter(Boolean).forEach((input) => input.addEventListener('change', () => {
            this.syncPresets();
            this.refresh();
        }));

        this.ghostButton?.addEventListener('click', () => {
            this.ghost = !this.ghost;
            setActive(this.ghostButton, this.ghost);
            this.refresh();
        });

        this.indicators.forEach((button) => button.addEventListener('click', () => {
            if (button === this.activeIndicator()) {
                return;
            }
            this.indicators.forEach((other) => setActive(other, other === button));
            this.refresh();
        }));

        this.params.forEach((input) => input.addEventListener('change', () => this.refresh()));

        this.bindCategoryProducts();
        this.bindSearch();

        // A tab drawn while hidden has no size: it is drawn when it shows.
        const pane = this.root.closest('.tab-pane');
        if (pane?.id) {
            document.querySelectorAll(`[data-bs-toggle="tab"][href="#${pane.id}"], [data-bs-toggle="tab"][data-bs-target="#${pane.id}"]`)
                .forEach((tab) => tab.addEventListener('shown.bs.tab', () => {
                    if (this.dirty) {
                        this.refresh();
                    }
                }));
        }
    }

    // The product list of the product tab follows its category select.
    bindCategoryProducts() {
        const category = this.root.querySelector('[data-stat-category]');
        const products = this.root.querySelector('[data-stat-products]');
        if (!category || !products) {
            return;
        }

        const placeholder = products.options[0]?.cloneNode(true);
        const load = async () => {
            products.replaceChildren(placeholder.cloneNode(true));
            if (!category.value) {
                this.refresh();

                return;
            }
            try {
                const url = new URL(category.dataset.productsUrl, window.location.origin);
                url.searchParams.set('category', category.value);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error(String(response.status));
                }
                (await response.json()).forEach((product) => products.append(new Option(product.i18n_TITLE, product.Ref)));
            } catch {
                this.showError();
            }
            this.refresh();
        };

        category.addEventListener('change', load);
        if (category.value) {
            load();
        }
    }

    bindSearch() {
        const modalElement = this.root.querySelector('[data-stat-search-modal]');
        if (!modalElement) {
            return;
        }

        const title = modalElement.querySelector('[data-stat-search-title]');
        const input = modalElement.querySelector('[data-stat-search-input]');
        const loading = modalElement.querySelector('[data-stat-search-loading]');
        const empty = modalElement.querySelector('[data-stat-search-empty]');
        const results = modalElement.querySelector('[data-stat-search-results]');
        let source = null;
        let timer = null;

        const reset = () => {
            input.value = '';
            results.replaceChildren();
            [loading, empty, results].forEach((node) => { node.hidden = true; });
        };

        this.root.querySelectorAll('[data-stat-search]').forEach((button) => button.addEventListener('click', () => {
            source = button;
            reset();
            title.textContent = button.dataset.title;
            input.placeholder = button.dataset.placeholder || '';
            Modal.getOrCreateInstance(modalElement).show();
        }));

        modalElement.addEventListener('shown.bs.modal', () => input.focus());

        input.addEventListener('input', () => {
            clearTimeout(timer);
            empty.hidden = true;
            if (input.value.trim().length < 3) {
                results.hidden = true;
                loading.hidden = true;

                return;
            }
            loading.hidden = false;
            timer = setTimeout(async () => {
                const url = new URL(source.dataset.url, window.location.origin);
                url.searchParams.set('q', input.value.trim());
                const filter = source.dataset.filter ? this.root.querySelector(source.dataset.filter)?.value : '';
                if (filter) {
                    url.searchParams.set('category_id', filter);
                }
                let found = {};
                try {
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    found = response.ok ? await response.json() : {};
                } catch {
                    found = {};
                }
                loading.hidden = true;
                const options = Object.entries(found ?? {}).map(([value, label]) => new Option(label, value));
                results.replaceChildren(...options);
                results.hidden = options.length === 0;
                empty.hidden = options.length > 0;
            }, 350);
        });

        modalElement.querySelector('[data-stat-search-select]').addEventListener('click', () => {
            const option = results.selectedOptions[0];
            const target = source ? this.root.querySelector(source.dataset.target) : null;
            if (!option || !target) {
                return;
            }
            if (![...target.options].some((existing) => existing.value === option.value)) {
                target.append(new Option(option.textContent, option.value));
            }
            target.value = option.value;
            target.dispatchEvent(new Event('change', { bubbles: true }));
            Modal.getOrCreateInstance(modalElement).hide();
        });
    }

    syncPresets() {
        const start = this.start.value;
        const end = this.end.value;
        this.presets.forEach((button) => {
            const range = presetRange(button.dataset.statPreset);
            setActive(button, isoDate(range[0]) === start && isoDate(range[1]) === end);
        });
        this.root.dataset.periodStart = start;
        this.root.dataset.periodEnd = end;
    }

    activeIndicator() {
        return this.indicators.find((button) => button.getAttribute('aria-pressed') === 'true') ?? null;
    }

    isVisible() {
        return this.root.offsetParent !== null;
    }

    query() {
        const query = new URLSearchParams();
        if (this.start && this.end && this.start.value && this.end.value) {
            const start = parseIsoDate(this.start.value);
            const end = parseIsoDate(this.end.value);
            query.set('startDay', String(start.getDate()));
            query.set('startMonth', String(start.getMonth() + 1));
            query.set('startYear', String(start.getFullYear()));
            query.set('endDay', String(end.getDate()));
            query.set('endMonth', String(end.getMonth() + 1));
            query.set('endYear', String(end.getFullYear()));
        }
        this.params.forEach((input) => query.set(input.dataset.statParam, input.value));
        if (this.ghostButton) {
            query.set('ghost', this.ghost ? '1' : '0');
        }

        return query;
    }

    // Legend of the series: the compared years, or the year of the period and the year before.
    legend(count) {
        if (this.root.dataset.legend === 'years') {
            return this.years.map((input) => input.value).slice(0, count);
        }
        const year = this.start?.value ? parseIsoDate(this.start.value).getFullYear() : new Date().getFullYear();

        return [String(year), String(year - 1)].slice(0, count);
    }

    missingFilter() {
        return this.params.some((input) => input.hasAttribute('data-stat-required') && !input.value)
            || this.years.some((input) => !/^\d{4}$/.test(input.value))
            || (this.start && (!this.start.value || !this.end.value));
    }

    async refresh() {
        if (!this.isVisible()) {
            this.dirty = true;

            return;
        }
        this.dirty = false;

        const indicator = this.activeIndicator();
        if (!indicator) {
            return;
        }
        const kind = indicator.dataset.kind;
        if (this.chartCard) {
            this.chartCard.hidden = kind !== 'chart';
        }
        if (this.tableCard) {
            this.tableCard.hidden = kind === 'chart';
        }

        if (this.missingFilter()) {
            this.showPlaceholder();

            return;
        }

        this.request?.abort();
        this.request = new AbortController();
        const url = new URL(indicator.dataset.url, window.location.origin);
        url.search = this.query().toString();

        let json;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: this.request.signal });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            json = await response.json();
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.showError(kind);
            }

            return;
        }

        if (kind === 'chart') {
            this.drawChart(json, indicator);
        } else if (kind === 'best-sales') {
            this.drawBestSales(json, indicator);
        } else {
            this.drawTable(json, indicator);
        }
    }

    showPlaceholder() {
        const card = this.chartCard;
        if (!card) {
            return;
        }
        this.chart?.destroy();
        this.chart = null;
        card.querySelector('[data-stat-chart]').hidden = true;
        card.querySelector('[data-stat-error]').hidden = true;
        card.querySelector('[data-stat-placeholder]').hidden = false;
        card.querySelector('[data-stat-total-wrapper]').hidden = true;
        card.querySelector('[data-stat-title]').textContent = this.activeIndicator()?.textContent.trim() ?? '';
    }

    showError(kind = 'chart') {
        if (kind === 'chart' && this.chartCard) {
            this.chartCard.querySelector('[data-stat-chart]').hidden = true;
            this.chartCard.querySelector('[data-stat-placeholder]').hidden = true;
            this.chartCard.querySelector('[data-stat-error]').hidden = false;
        } else if (this.tableCard) {
            this.tableCard.querySelector('[data-stat-table-error]').hidden = false;
        }
    }

    drawChart(json, indicator) {
        const card = this.chartCard;
        const series = (json.series ?? []).filter((serie) => Array.isArray(serie.graph));
        const first = series[0] ?? { graph: [] };
        const legend = this.legend(series.length);
        const format = indicator.dataset.format;
        const total = numberFormatter(format, this.currency, 2);

        card.querySelector('[data-stat-error]').hidden = true;
        card.querySelector('[data-stat-placeholder]').hidden = true;
        card.querySelector('[data-stat-chart]').hidden = false;
        card.querySelector('[data-stat-title]').textContent = json.title || indicator.textContent.trim();

        // Total of the period, then of the compared series.
        const totals = series.map((serie) => total(sumOf(serie.graph)));
        card.querySelector('[data-stat-total]').textContent = series.length > 1
            ? totals.map((value, index) => `${legend[index] ?? ''} ${value}`.trim()).join(' / ')
            : (totals[0] ?? total(0));
        card.querySelector('[data-stat-total-wrapper]').hidden = indicator.hasAttribute('data-no-total');

        this.chart?.destroy();
        this.chart = createChart(card.querySelector('[data-stat-canvas]'), {
            labels: first.graph.map((point, index) => labelAt(first.graphLabel, index)),
            datasets: series.map((serie, index) => ({ label: legend[index] ?? '', data: serie.graph.map((point) => Number(point[1]) || 0) })),
            format,
            currency: this.currency,
        });
    }

    tableParts(indicator) {
        const card = this.tableCard;
        card.querySelector('[data-stat-table-error]').hidden = true;
        card.querySelector('[data-stat-table-title]').textContent = indicator.textContent.trim();
        card.querySelector('[data-best-sales-filters]').hidden = indicator.dataset.kind !== 'best-sales';
        card.querySelector('[data-stat-pager]').hidden = true;
        const table = card.querySelector('[data-stat-table]');
        table.replaceChildren();

        return { card, table };
    }

    drawTable(json, indicator) {
        this.bestSales = null;
        const { card, table } = this.tableParts(indicator);
        const serie = json.series?.[0] ?? {};
        const keys = Object.keys(serie.thead ?? {});
        const rows = Object.values(serie.table ?? {});

        const head = table.createTHead();
        head.className = 'table-light';
        const headRow = head.insertRow();
        keys.forEach((key) => headRow.append(element('th', { scope: 'col' }, serie.thead[key])));

        const body = table.createTBody();
        rows.forEach((line) => {
            const row = body.insertRow();
            keys.forEach((key) => row.append(element('td', {}, line[key] ?? '')));
        });
        card.querySelector('[data-stat-table-empty]').hidden = rows.length > 0;
    }

    drawBestSales(json, indicator) {
        const { card, table } = this.tableParts(indicator);
        const serie = json.series?.[0] ?? {};
        const keys = Object.keys(serie.thead ?? {});
        // Columns a module adds (StatisticEvents::BEST_SALES_TABLE) come before the title, written as HTML.
        const offset = Math.max(0, keys.indexOf('title'));
        const soldKeys = keys.slice(offset + 3, offset + 6);
        const amountKeys = keys.slice(offset + 6, offset + 9);
        const lines = Object.values(serie.table ?? {});
        card.querySelector('[data-stat-table-empty]').hidden = lines.length > 0;

        this.bestSales = {
            table, keys, offset, soldKeys, amountKeys, titles: serie.thead ?? {}, mhead: serie.mhead ?? [], totals: serie.totals ?? [], lines,
            sortKey: keys[offset + 3] ?? keys[0], sortDirection: 'desc', page: 0, open: new Set(),
            productUrl: table.dataset.productUrl, detailsUrl: table.dataset.detailsUrl,
            salesLabel: table.dataset.salesLabel, detailsLabel: table.dataset.detailsLabel,
            amount: numberFormatter('currency', this.currency, 2),
        };

        const brand = card.querySelector('[data-best-sales-brand]');
        const search = card.querySelector('[data-best-sales-search]');
        if (!brand.dataset.bound) {
            brand.dataset.bound = '1';
            brand.addEventListener('change', () => { if (this.bestSales) { this.bestSales.page = 0; this.renderBestSales(); } });
            search.addEventListener('input', () => { if (this.bestSales) { this.bestSales.page = 0; this.renderBestSales(); } });
        }
        this.renderBestSales();
    }

    renderBestSales() {
        const state = this.bestSales;
        const { table, keys, offset } = state;
        const card = this.tableCard;
        const brand = card.querySelector('[data-best-sales-brand]').value;
        const terms = card.querySelector('[data-best-sales-search]').value.toLowerCase().split(/\s+/).filter(Boolean);
        const isNumeric = (key) => state.soldKeys.includes(key) || state.amountKeys.includes(key);

        const matching = state.lines.filter((line) => {
            if (brand && line.brand_title !== brand) {
                return false;
            }
            const text = keys.map((key) => String(line[key] ?? '')).join(' ').toLowerCase();

            return terms.every((term) => text.includes(term));
        });
        const direction = state.sortDirection === 'asc' ? 1 : -1;
        matching.sort((first, second) => {
            const a = first[state.sortKey];
            const b = second[state.sortKey];

            return direction * (isNumeric(state.sortKey)
                ? parseAmount(a) - parseAmount(b)
                : String(a ?? '').localeCompare(String(b ?? ''), LOCALE, { sensitivity: 'base' }));
        });

        table.replaceChildren();
        const head = table.createTHead();
        head.className = 'table-light';
        const groups = head.insertRow();
        groups.append(element('th', { colspan: String(offset + 3), scope: 'colgroup' }));
        state.mhead.forEach((title) => groups.append(element('th', { colspan: '3', scope: 'colgroup', class: 'text-center' }, title)));
        groups.append(element('th'));

        const titles = head.insertRow();
        keys.forEach((key, index) => {
            const cell = element('th', { scope: 'col', 'data-key': key, class: isNumeric(key) ? 'text-end text-nowrap' : (key === 'title' ? 'w-25' : '') });
            if (index < offset) {
                cell.innerHTML = state.titles[key] ?? '';
                titles.append(cell);

                return;
            }
            const active = state.sortKey === key;
            const button = element('button', { type: 'button', class: 'btn btn-link btn-sm p-0 text-decoration-none text-reset fw-semibold text-start' });
            button.append(document.createTextNode(state.titles[key] ?? key));
            button.append(' ', element('i', { class: `bi ${active ? (state.sortDirection === 'asc' ? 'bi-sort-up' : 'bi-sort-down') : 'bi-arrow-down-up text-muted'}`, 'aria-hidden': 'true' }));
            button.addEventListener('click', () => {
                state.sortDirection = active && state.sortDirection === 'desc' ? 'asc' : 'desc';
                state.sortKey = key;
                this.renderBestSales();
            });
            if (active) {
                cell.setAttribute('aria-sort', state.sortDirection === 'asc' ? 'ascending' : 'descending');
            }
            cell.append(button);
            titles.append(cell);
        });
        const detailsHead = element('th', { scope: 'col' });
        detailsHead.append(element('span', { class: 'visually-hidden' }, state.detailsLabel));
        titles.append(detailsHead);

        const body = table.createTBody();
        // Totals of the rows left by the filters, all pages.
        const totals = body.insertRow();
        totals.className = 'fw-semibold';
        keys.forEach((key, index) => {
            let value = state.totals[index] ?? '';
            if (state.soldKeys.includes(key)) {
                value = matching.reduce((sum, line) => sum + parseAmount(line[key]), 0);
            } else if (state.amountKeys.includes(key)) {
                value = state.amount(matching.reduce((sum, line) => sum + parseAmount(line[key]), 0));
            }
            totals.append(element('td', isNumeric(key) ? { class: 'text-end text-nowrap' } : {}, value));
        });
        totals.append(element('td'));

        const pages = Math.max(1, Math.ceil(matching.length / PAGE_SIZE));
        state.page = Math.min(state.page, pages - 1);
        const first = state.page * PAGE_SIZE;
        const shown = new Set(matching.slice(first, first + PAGE_SIZE));

        // Every row is rendered: [data-match] marks the rows left by the filters, the other pages are hidden.
        // A toolbar action (statistic.best-sales.toolbar) reads tbody tr[data-ref][data-match].
        const matched = new Set(matching);
        [...matching, ...state.lines.filter((line) => !matched.has(line))].forEach((line) => {
            const row = body.insertRow();
            row.dataset.ref = line.product_ref ?? '';
            if (matched.has(line)) {
                row.dataset.match = '';
            }
            row.hidden = !shown.has(line);
            keys.forEach((key, index) => {
                const cell = row.insertCell();
                if (index < offset) {
                    cell.innerHTML = line[key] ?? '';
                } else if ((key === 'title' || key === 'product_ref') && line.product_id) {
                    const link = element('a', { href: state.productUrl.replace(/([?&]product_id=)0\b|\/0(?=$|[/?#])/, (match, query) => (query ? `${query}${line.product_id}` : `/${line.product_id}`)) }, line[key] ?? '');
                    cell.append(link);
                } else {
                    cell.textContent = line[key] ?? '';
                }
                if (isNumeric(key)) {
                    cell.classList.add('text-end', 'text-nowrap');
                } else if (key === 'product_ref') {
                    cell.classList.add('text-break');
                }
            });
            const action = row.insertCell();
            action.className = 'text-end';
            if (line.product_id) {
                const button = element('button', { type: 'button', class: 'btn btn-sm btn-link', 'aria-expanded': 'false', title: state.detailsLabel });
                button.append(element('i', { class: 'bi bi-chevron-down', 'aria-hidden': 'true' }), element('span', { class: 'visually-hidden' }, state.detailsLabel));
                button.addEventListener('click', () => this.toggleDetails(row, button, line));
                action.append(button);
            }
        });

        this.renderPager(matching.length, pages);
    }

    async toggleDetails(row, button, line) {
        const next = row.nextElementSibling;
        if (next?.dataset.details !== undefined) {
            next.remove();
            button.setAttribute('aria-expanded', 'false');
            button.querySelector('i').className = 'bi bi-chevron-down';

            return;
        }

        const url = new URL(this.bestSales.detailsUrl, window.location.origin);
        url.search = this.query().toString();
        url.searchParams.set('productId', String(line.product_id));
        let groups = {};
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            groups = response.ok ? await response.json() : {};
        } catch {
            groups = {};
        }

        const detail = element('tr', { 'data-details': '' });
        detail.hidden = row.hidden;
        const cell = element('td', { colspan: String(row.cells.length), class: 'bg-body-tertiary' });
        const list = element('table', { class: 'table table-sm w-auto mb-0' });
        const count = Object.values(groups).reduce((sum, dates) => sum + dates.length, 0);
        const listBody = list.createTBody();
        Object.entries(groups).forEach(([label, dates]) => {
            dates.forEach((date, index) => {
                const detailRow = listBody.insertRow();
                if (index === 0) {
                    const share = count ? Math.round((dates.length / count) * 10000) / 100 : 0;
                    const head = element('th', { scope: 'row', rowspan: String(dates.length), class: 'align-top fw-normal' },
                        `${label} (${numberFormatter('number')(share)} %, ${dates.length} ${this.bestSales.salesLabel})`);
                    detailRow.append(head);
                }
                detailRow.append(element('td', {}, date));
            });
        });
        cell.append(list);
        detail.append(cell);
        row.after(detail);
        button.setAttribute('aria-expanded', 'true');
        button.querySelector('i').className = 'bi bi-chevron-up';
    }

    renderPager(count, pages) {
        const pager = this.tableCard.querySelector('[data-stat-pager]');
        const list = pager.querySelector('[data-stat-pager-pages]');
        const state = this.bestSales;
        pager.hidden = count <= PAGE_SIZE;
        pager.querySelector('[data-stat-pager-info]').textContent = count
            ? `${state.page * PAGE_SIZE + 1}–${Math.min(count, (state.page + 1) * PAGE_SIZE)} / ${count}`
            : '';
        list.replaceChildren();

        const item = (label, page, { disabled = false, active = false } = {}) => {
            const li = element('li', { class: `page-item${disabled ? ' disabled' : ''}${active ? ' active' : ''}` });
            const button = element('button', { type: 'button', class: 'page-link' }, label);
            if (active) {
                li.setAttribute('aria-current', 'page');
            }
            button.disabled = disabled;
            button.addEventListener('click', () => {
                state.page = page;
                this.renderBestSales();
            });
            li.append(button);
            list.append(li);
        };

        item('‹', state.page - 1, { disabled: state.page === 0 });
        for (let page = 0; page < pages; page += 1) {
            if (page === 0 || page === pages - 1 || Math.abs(page - state.page) <= 2) {
                item(String(page + 1), page, { active: page === state.page });
            } else if (Math.abs(page - state.page) === 3) {
                const gap = element('li', { class: 'page-item disabled' });
                gap.append(element('span', { class: 'page-link' }, '…'));
                list.append(gap);
            }
        }
        item('›', state.page + 1, { disabled: state.page >= pages - 1 });
    }
}

const panels = [...document.querySelectorAll('[data-statistic-panel]')]
    .filter((root) => !root.dataset.statisticReady)
    .map((root) => {
        root.dataset.statisticReady = '1';

        return new StatisticPanel(root);
    });

panels.forEach((panel) => panel.refresh());
