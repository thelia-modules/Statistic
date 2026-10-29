/**
 * Best-sales statistics for the product edit page (Statistics tab).
 *
 * Vanilla JS — no jQuery, no DataTables, no bootstrap-datepicker. The markup it
 * builds follows the lists of the default-twig dashboard (Bootstrap 5 list groups,
 * Bootstrap Icons). Date selection uses native <input type="date">.
 */
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('product-best-sales');

    if (null === root) {
        return;
    }

    const startInput = document.getElementById('bestSalesStartDate');
    const endInput = document.getElementById('bestSalesEndDate');
    const salesList = root.querySelector('[data-product-best-sales="sales"]');
    const combinationsList = root.querySelector('[data-product-best-sales="combinations"]');
    const productId = root.dataset.product_id;
    const labels = {
        soldOne: root.dataset.labelSoldOne,
        soldOther: root.dataset.labelSoldOther,
        empty: root.dataset.labelEmpty,
        error: root.dataset.labelError,
        noCombination: root.dataset.labelNoCombination,
        periods: JSON.parse(root.dataset.labelPeriods),
    };
    const locale = document.documentElement.lang || undefined;
    const pluralRules = new Intl.PluralRules(locale);
    const baseAdminUrl = window.baseAdminUrl || '';
    let lastRequest = 0;

    // The keys the module fills itself; any other key of the best sales table was added
    // through StatisticEvents::BEST_SALES_TABLE and is shown as one more line.
    const ownKeys = ['title', 'product_ref', 'brand_title', 'total_sold', 'total_sold2', 'total_sold3', 'total_ttc', 'total_ttc2', 'total_ttc3'];
    const periods = [
        ['total_sold', 'total_ttc'],
        ['total_sold2', 'total_ttc2'],
        ['total_sold3', 'total_ttc3'],
    ];

    const toInputValue = (date) => {
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${date.getFullYear()}-${month}-${day}`;
    };

    const defaultStart = new Date();
    defaultStart.setMonth(defaultStart.getMonth() - 1);
    startInput.value = toInputValue(defaultStart);
    endInput.value = toInputValue(new Date());

    startInput.addEventListener('change', load);
    endInput.addEventListener('change', load);

    // The statistics are read when the tab is first shown, not on every product page: right away when
    // the tab is already the active one (?current_tab=statistic), otherwise on its first opening.
    let loadedOnce = false;
    const loadOnce = () => {
        if (loadedOnce) {
            return;
        }

        loadedOnce = true;
        load();
    };
    const pane = root.closest('.tab-pane');
    const trigger = null === pane ? null : document.querySelector(`[data-bs-toggle="tab"][data-bs-target="#${pane.id}"]`);

    if (null === pane || null === trigger || pane.classList.contains('active')) {
        loadOnce();
    } else {
        trigger.addEventListener('shown.bs.tab', loadOnce, { once: true });
    }

    function buildUrl(path) {
        const [startYear, startMonth, startDay] = startInput.value.split('-');
        const [endYear, endMonth, endDay] = endInput.value.split('-');

        const params = new URLSearchParams({
            startDay: String(Number(startDay)),
            startMonth: String(Number(startMonth)),
            startYear: startYear,
            endDay: String(Number(endDay)),
            endMonth: String(Number(endMonth)),
            endYear: endYear,
            productId: productId,
        });

        return `${baseAdminUrl}${path}?${params.toString()}`;
    }

    async function fetchJson(path) {
        const response = await fetch(buildUrl(path), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        return response.json();
    }

    async function load() {
        if ('' === startInput.value || '' === endInput.value) {
            return;
        }

        // Only the answer to the last change of dates is drawn: an earlier one may arrive later.
        const request = ++lastRequest;

        try {
            const [bestSales, details] = await Promise.all([
                fetchJson('/module/statistic/bestSales'),
                fetchJson('/module/statistic/getProductDetails'),
            ]);

            if (request !== lastRequest) {
                return;
            }

            renderSales(bestSales.series[0], bestSales.series[0].periods ?? []);
            renderCombinations(details);
        } catch (error) {
            console.error('Unable to load best-sales statistics', error);

            if (request === lastRequest) {
                [salesList, combinationsList].forEach((list) => list.replaceChildren(messageItem(labels.error)));
            }
        }
    }

    function renderSales(serie, ranges) {
        salesList.innerHTML = '';

        const line = serie.table[0];

        if (undefined === line) {
            salesList.appendChild(emptyItem());

            return;
        }

        periods.forEach(([soldKey, amountKey], index) => {
            const sold = Number(line[soldKey]);
            // No amount for a period without sales: the server sends it as a bare 0.
            const row = item(labels.periods[index], soldLabel(sold), sold > 0 ? String(line[amountKey]) : null);

            if (undefined !== ranges[index]) {
                const range = document.createElement('div');
                range.className = 'text-muted fw-normal';
                range.textContent = `${formatDate(ranges[index].start)} – ${formatDate(ranges[index].end)}`;
                row.firstElementChild.appendChild(range);
            }

            salesList.appendChild(row);
        });

        Object.keys(serie.thead)
            .filter((key) => !ownKeys.includes(key))
            .forEach((key) => {
                // A column with no value for this product is not shown: its title alone says nothing.
                if ('' === String(line[key] ?? '').trim()) {
                    return;
                }

                // Titles and values of an added column are HTML (see README).
                const added = item('', '');
                added.firstElementChild.innerHTML = serie.thead[key];
                added.lastElementChild.innerHTML = line[key];
                salesList.appendChild(added);
            });
    }

    function renderCombinations(details) {
        combinationsList.innerHTML = '';

        const groups = Object.keys(details);

        if (0 === groups.length) {
            combinationsList.appendChild(emptyItem());

            return;
        }

        const combinations = groups
            .map((group) => {
                const sales = details[group].map(parseSale);

                return {
                    title: group.replace(/\s*:\s*$/, '') || labels.noCombination,
                    sales: sales.sort((a, b) => (b.date?.getTime() ?? -Infinity) - (a.date?.getTime() ?? -Infinity)),
                    quantity: sales.reduce((total, sale) => total + sale.quantity, 0),
                };
            })
            .sort((a, b) => b.quantity - a.quantity);

        combinations.forEach((combination) => {
            const row = item(combination.title, soldLabel(combination.quantity));
            row.classList.replace('align-items-center', 'align-items-start');

            const dates = document.createElement('div');
            dates.className = 'text-muted fw-normal';
            combination.sales.forEach((sale, index) => {
                const day = null === sale.date ? sale.text : sale.date.toLocaleDateString(locale);
                dates.append(0 === index ? day : `, ${day}`);

                if (sale.quantity > 1) {
                    const units = document.createElement('small');
                    units.textContent = `\u00a0×${sale.quantity}`;
                    dates.appendChild(units);
                }
            });
            row.firstElementChild.appendChild(dates);

            combinationsList.appendChild(row);
        });
    }

    // "yyyy-mm-dd" from the server, shown in the language of the back office.
    function formatDate(isoDate) {
        const [year, month, day] = isoDate.split('-').map(Number);

        return new Date(year, month - 1, day).toLocaleDateString(locale);
    }

    // The module writes a sale as "dd/mm/yyyy", followed by " xN" when the line held more than
    // one unit. A line rewritten in another shape through StatisticEvents::PRODUCT_DETAILS is
    // shown as it is and counts for one unit.
    function parseSale(sale) {
        const match = /^(\d{2})\/(\d{2})\/(\d{4})(?: x(\d+))?$/.exec(sale);

        if (null === match) {
            return { date: null, text: sale, quantity: 1 };
        }

        const [, day, month, year, quantity] = match;

        return { date: new Date(Number(year), Number(month) - 1, Number(day)), text: sale, quantity: undefined === quantity ? 1 : Number(quantity) };
    }

    function item(title, value, secondary = null) {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center justify-content-between gap-2';

        const label = document.createElement('div');
        label.className = 'fw-semibold';
        label.textContent = title;

        const figures = document.createElement('div');
        figures.className = 'text-end text-nowrap flex-shrink-0';

        const main = document.createElement('div');
        main.className = 'fw-semibold';
        main.textContent = value;
        figures.appendChild(main);

        if (null !== secondary) {
            const sub = document.createElement('div');
            sub.className = 'text-muted';
            sub.textContent = secondary;
            figures.appendChild(sub);
        }

        li.append(label, figures);

        return li;
    }

    function soldLabel(count) {
        return `${count} ${'one' === pluralRules.select(count) ? labels.soldOne : labels.soldOther}`;
    }

    function emptyItem() {
        return messageItem(labels.empty);
    }

    function messageItem(message) {
        const li = document.createElement('li');
        li.className = 'list-group-item text-muted text-center';
        li.textContent = message;

        return li;
    }
});
