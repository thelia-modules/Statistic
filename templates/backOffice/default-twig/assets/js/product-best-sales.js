/**
 * Best-sales statistics for the product edit page (Statistics tab).
 *
 * Vanilla JS — no jQuery, no DataTables, no bootstrap-datepicker. The markup it
 * builds follows the default-twig back-office conventions (Bootstrap 5 tables and
 * buttons, Bootstrap Icons). Date selection uses native <input type="date">.
 */
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('table-best-sale-statistic');

    if (null === table) {
        return;
    }

    const startInput = document.getElementById('bestSalesStartDate');
    const endInput = document.getElementById('bestSalesEndDate');
    const productId = table.getAttribute('data-product_id');
    const baseAdminUrl = window.baseAdminUrl || '';

    const toInputValue = (date) => {
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');

        return `${date.getFullYear()}-${month}-${day}`;
    };

    const defaultStart = new Date();
    defaultStart.setMonth(defaultStart.getMonth() - 1);
    startInput.value = toInputValue(defaultStart);
    endInput.value = toInputValue(new Date());

    startInput.addEventListener('change', loadBestSales);
    endInput.addEventListener('change', loadBestSales);

    loadBestSales();

    function buildUrl(path, overrideProductId) {
        const [startYear, startMonth, startDay] = startInput.value.split('-');
        const [endYear, endMonth, endDay] = endInput.value.split('-');

        const params = new URLSearchParams({
            startDay: String(Number(startDay)),
            startMonth: String(Number(startMonth)),
            startYear: startYear,
            endDay: String(Number(endDay)),
            endMonth: String(Number(endMonth)),
            endYear: endYear,
            productId: overrideProductId ?? productId,
        });

        return `${baseAdminUrl}${path}?${params.toString()}`;
    }

    async function loadBestSales() {
        if ('' === startInput.value || '' === endInput.value) {
            return;
        }

        try {
            const response = await fetch(buildUrl('/module/statistic/bestSales'), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            renderTable(await response.json());
        } catch (error) {
            console.error('Unable to load best-sales statistics', error);
        }
    }

    function renderTable(json) {
        table.innerHTML = '';

        const serie = json.series[0];
        const keys = Object.keys(serie.thead);
        const columnCount = keys.length + 1;

        const head = table.createTHead();
        head.classList.add('table-light');

        const groupRow = head.insertRow();
        groupRow.appendChild(headerCell('', 3));
        serie.mhead.forEach((label) => groupRow.appendChild(headerCell(label, 3, 'text-center')));
        groupRow.appendChild(headerCell(''));

        const titleRow = head.insertRow();
        keys.forEach((key) => titleRow.appendChild(headerCell(serie.thead[key])));
        titleRow.appendChild(headerCell(''));

        const body = table.createTBody();
        serie.table.forEach((line) => {
            const row = body.insertRow();

            keys.forEach((key, index) => {
                const cell = row.insertCell();

                if (index <= 1) {
                    const link = document.createElement('a');
                    link.href = `${baseAdminUrl}/products/update?product_id=${line.product_id}`;
                    link.textContent = line[key];
                    cell.appendChild(link);

                    return;
                }

                cell.textContent = line[key];
            });

            const actionCell = row.insertCell();
            actionCell.className = 'text-end';
            actionCell.appendChild(detailsButton(row, line.product_id, columnCount));
        });
    }

    function headerCell(label, colSpan = 1, className = '') {
        const th = document.createElement('th');
        th.textContent = label;
        th.colSpan = colSpan;

        if ('' !== className) {
            th.className = className;
        }

        return th;
    }

    function detailsButton(row, rowProductId, columnCount) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary button-details';
        button.innerHTML = '<i class="bi bi-chevron-down"></i>';

        button.addEventListener('click', () => toggleDetails(button, row, rowProductId, columnCount));

        return button;
    }

    async function toggleDetails(button, row, rowProductId, columnCount) {
        const icon = button.querySelector('i');
        const next = row.nextElementSibling;

        if (null !== next && next.classList.contains('best-sales-detail-row')) {
            next.remove();
            icon.classList.replace('bi-chevron-up', 'bi-chevron-down');

            return;
        }

        try {
            const response = await fetch(buildUrl('/module/statistic/getProductDetails', rowProductId), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await response.json();

            const detailRow = document.createElement('tr');
            detailRow.className = 'best-sales-detail-row';
            const cell = detailRow.insertCell();
            cell.colSpan = columnCount;
            cell.appendChild(buildDetailTable(json));

            row.parentNode.insertBefore(detailRow, row.nextSibling);
            icon.classList.replace('bi-chevron-down', 'bi-chevron-up');
        } catch (error) {
            console.error('Unable to load product details', error);
        }
    }

    function buildDetailTable(json) {
        const detailTable = document.createElement('table');
        detailTable.className = 'table table-sm mb-0';
        const body = detailTable.createTBody();

        Object.keys(json).forEach((size) => {
            const values = json[size];

            values.forEach((value, index) => {
                const tr = body.insertRow();

                if (0 === index) {
                    const sizeCell = tr.insertCell();
                    sizeCell.rowSpan = values.length;
                    sizeCell.className = 'align-top fw-medium';
                    sizeCell.textContent = size;
                }

                tr.insertCell().textContent = value;
            });
        });

        return detailTable;
    }
});
