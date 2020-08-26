$(document).ready(function(){
    var tableId = "table-best-sale-statistic";
    var startDatePicker = $('.date-picker');
    var endDatePicker = $('.end-date-picker');
    var startDate = new Date();
    startDate.setMonth(startDate.getMonth() - 1);
    var endDate = new Date();
    var dt = null;


    startDatePicker.datepicker( {
        format: 'dd/mm/yyyy',
        minViewMode: 0,
        language: "fr"
    }).on('changeDate', function(e){
        startDate = e.date;

        setDataTable();
    });

    endDatePicker.datepicker( {
        format: 'dd/mm/yyyy',
        minViewMode: 0,
        language: "fr"
    }).on('changeDate', function(e){
        endDate = e.date;

        setDataTable();
    });

    startDatePicker.datepicker('update', startDate);
    endDatePicker.datepicker('update', endDate);

    setDataTable();

    function setDataTable() {
        $.ajax({
            url:
            baseAdminUrl + '/module/statistic/bestSales' +
            '?startDay=' + startDate.getDate() +
            '&startMonth=' + (startDate.getMonth()+1) +
            '&startYear=' + startDate.getFullYear() +
            '&endDay=' + endDate.getDate() +
            '&endMonth=' + (endDate.getMonth()+1) +
            '&endYear=' + endDate.getFullYear() +
            '&productId=' + $('#' + tableId).attr('data-product_id')
        }).success(function (json) {

            var tableJQ = $('#' + tableId);
            var table = document.getElementById(tableId);

            if (dt) {
                dt.destroy();
                tableJQ.empty();
            }

            table.innerHTML = "";

            setBestSalesTable(json, table, tableJQ);

        });
    }

    function setBestSalesTable(json, table, tableJQ) {
        var head = table.createTHead();
        keys = [];
        var mhead = json.series[0].mhead;
        var row = head.insertRow(0);
        var cell = row.insertCell(-1);
        cell.innerHTML = '';
        cell.colSpan = 3;
        for (var index in mhead) {
            cell = row.insertCell(-1);
            cell.innerHTML = mhead[index];
            cell.colSpan = 3;
            cell.classList.add('text-center');
        }
        row = head.insertRow(1);
        var titles = json.series[0].thead;
        for (var key in titles) {
            keys.push(key);
            cell = row.insertCell(-1);
            cell.innerHTML = titles[key];
        }
        cell = row.insertCell(-1);
        cell.innerHTML = "";

        var body = table.appendChild(document.createElement('tbody'));
        var data = json.series[0].table;
        for (var idx in data) {
            var line = data[idx];
            row = body.insertRow(-1);
            for (var k in keys) {
                key = keys[k];
                cell = row.insertCell(-1);
                cell.innerHTML = line[key];
                if (k <= 1) {
                    var productUrl = baseAdminUrl + "/products/update?product_id=" + line['product_id'];
                    cell.innerHTML = "<a href='" + productUrl + "'>" + line[key] + "</a>";
                }
                if (k >= 6 && k <= 8) {
                    var dataSort = line[key].toString().replace(",", ".");
                    dataSort = Number(dataSort.replace(/[^0-9.-]+/g, ""));
                    cell.setAttribute("data-sort", dataSort.toString());
                }
            }
            cell = row.insertCell(-1);
            cell.innerHTML = "<button class='btn btn-default glyphicon glyphicon-arrow-down button-details' data-product='" + line['product_id'] + "'/>"
        }
        dt = tableJQ.DataTable({
            "paging": false,
            "ordering": false,
            "info": false,
            "searching": false,
            "columnDefs": [
                {"width": "40%", "targets": 0},
                {
                    "targets": 9,
                    "className": 'best-sales-details',
                    "orderable": false
                }
            ]
        });

        $('#' + tableId + ' tbody').on('click', 'td.best-sales-details', function () {
            var tr = $(this).closest('tr');
            var row = dt.row( tr );
            var button = tr.find(".button-details");
            if (row.child.isShown()) {
                row.child.hide();
                tr.removeClass('shown');
                button.removeClass("glyphicon-arrow-up").addClass("glyphicon-arrow-down");
            }
            else {
                getDetail(button.attr('data-product'), row, tr, button);
            }
        });
    }

    function getDetail(productId, row, tr, button) {
        console.log(productId);
        $.ajax({
            url:
            baseAdminUrl + '/module/statistic/getProductDetails' +
            '?startDay=' + startDate.getDate() +
            '&startMonth=' + (startDate.getMonth() + 1) +
            '&startYear=' + startDate.getFullYear() +
            '&endDay=' + endDate.getDate() +
            '&endMonth=' + (endDate.getMonth() + 1) +
            '&endYear=' + endDate.getFullYear() +
            '&productId=' + productId
        }).success(function (json) {
            var result = '<table>';
            for (var size in json){
                result += '<tr>' +
                    '<td rowspan="'+ json[size].length +'" style="vertical-align: top;" >' +
                    size +
                    '</td>' +
                    '<td>' +
                    json[size][0] +
                    '</td>' +
                    '</tr>';
                for (var i = 1; i < json[size].length; i++){
                    result += '<tr><td>' + json[size][i] + '</td></tr>'
                }
            }
            result += '</table>';

            row.child(result).show();
            tr.addClass('shown');
            button.removeClass("glyphicon-arrow-down").addClass("glyphicon-arrow-up");
        })
    }
});