/**
 * Created by doud on 05/06/15.
 */
var bestSales;
(function ($) {
    $(document).ready(function () {
        var url = baseAdminUrl + '/module/statistic/revenue';
        var chartId = 'jqplot-general';

        var jQplotDate = new Date();
        jQplotDate.setDate(1); // Set day to 1 so we can add month without 30/31 days of month troubles.
        var jQplotData; // json data
        var jQPlotInstance; // global instance


        var type = "jqplot-general";
        var targetId = "registration";
        var dataTarget = "";
        var startDate = new Date();
        startDate.setMonth(startDate.getMonth() - 1);
        var endDate = new Date();
        var ghost = 0;
        var dt = null;


        var jQPlotsOptions = {
            animate: false,
            axesDefaults: {
                tickRenderer: $.jqplot.CanvasAxisTickRenderer,
                tickOptions: {showMark: true, showGridline: true}
            },
            axes: {
                xaxis: {
                    borderColor: '#ccc',
                    ticks: [],
                    tickOptions: {
                        angle: -30,
                        showGridline: false
                    }
                },
                yaxis: {
                    min: 0,
                    tickOptions: {showGridline: true, showMark: false, showLabel: true, shadow: false}
                }
            },
            seriesDefaults: {
                lineWidth: 3,
                shadow: false,
                markerOptions: {shadow: false, style: 'filledCircle', size: 6}
            },
            grid: {
                background: '#FFF',
                shadow: false,
                borderColor: '#FFF'
            },
            highlighter: {
                show: true,
                sizeAdjust: 7,
                tooltipLocation: 'n',
                tooltipContentEditor: function (str, seriesIndex, pointIndex, plot) {
                    // Return axis value : data value
                    //return jQPlotsOptions.axes.xaxis.ticks[pointIndex][1] + ': ' + plot.data[seriesIndex][pointIndex][1];
                    return Math.round(plot.data[seriesIndex][pointIndex][1]*100)/100;
                }
            },
            legend: {
                show: false
            }
        };

        $('.date-picker').datepicker( {
            format: 'dd/mm/yyyy',
            minViewMode: 0,
            language: "fr"
        }).on('changeDate', function(e){
            startDate = e.date;

            updateContent();
        });

        $('.end-date-picker').datepicker( {
            format: 'dd/mm/yyyy',
            minViewMode: 0,
            language: "fr"
        }).on('changeDate', function(e){
            endDate = e.date;

            updateContent();
        });

        $('.date-picker').datepicker('update', startDate);
        $('.end-date-picker').datepicker('update', endDate);

        $('.general-graph-select').click(function (e) {
            if (!$(this).hasClass("active")){
                type = this.dataset.type;
                dataTarget = this.dataset.target;
                targetId = this.dataset.toggle;
                url = this.dataset.url;
                $(".general-graph-select").removeClass("active");
                $(this).toggleClass('active');
                updateContent();
            }
        });

        // Get initial data Json
        retrieveJQPlotJson(startDate, endDate);

        $('#select-day-scale').click(function () {
            startDate = new Date();
            endDate = new Date();
            setScale();

        });
        $('#select-month-scale').click(function () {
            startDate = new Date();
            startDate.setMonth(startDate.getMonth() - 1);
            endDate = new Date();
            setScale();
        });
        $('#select-year-scale').click(function () {
            startDate = new Date();
            startDate.setFullYear(startDate.getFullYear() - 1);
            endDate = new Date();
            setScale();
        });
        $('#select-last-day-scale').click(function () {
            startDate = new Date();
            startDate.setDate(startDate.getDate() - 1);
            endDate = new Date(startDate.getTime());
            setScale();
        });
        $('#select-last-month-scale').click(function () {
            startDate = new Date();
            startDate.setMonth(startDate.getMonth() - 2);
            endDate = new Date();
            endDate.setMonth(endDate.getMonth() - 1);
            setScale();
        });
        $('#select-last-year-scale').click(function () {
            startDate = new Date();
            startDate.setFullYear(startDate.getFullYear() - 2);
            endDate = new Date();
            endDate.setFullYear(endDate.getFullYear() - 1);
            setScale();
        });

        $('#add-ghost-curve').click(function () {
            ghost = ghost === 0 ? 1: 0;

            if ($(this).hasClass("active")){
                $(this).removeClass("active");
            }
            else {
                $(this).toggleClass('active');
            }
            updateContent();
        });

        function setScale() {
            $('.date-picker').datepicker('update', startDate);
            $('.end-date-picker').datepicker('update', endDate);

            updateContent();
        }

        function updateContent() {
            $('#best-sales-tool-div').hide();
            if (type.indexOf('jqplot')!==-1) {
                $('.jqplot-content').show();
                $('#jqplot-general').show();
                $('.total').css("display","block");
                retrieveJQPlotJson(startDate, endDate, ghost);
            } else {
                $('.jqplot-content').hide();
                $('.total').hide();

            }
            
            if (type.indexOf('table')!==-1) {
                var id = targetId.split(',');
                $('.table-content').css("display","block");
                setDataTable(id);
            } else {
                $('.table-content').css("display","none");
            }
        }

        function totalCalcul(jQplotData) {
            let total = 0;

            jQplotData.series.forEach(entry => {
                entry.graph.forEach(graph => {
                    total += graph[1];
                });
            });

            total = Math.round(total * 100) / 100;

            let s = document.getElementById('total');
            $(s.parentElement).removeClass("hide");
            s.innerHTML = total.toString();
        }

        function retrieveJQPlotJson(startDate, endDate, ghost, callback) {
            if (typeof ghost === 'undefined') {
                ghost = 0;
            }
            $.getJSON(url, {
                startDay: startDate.getDate(),
                startMonth: startDate.getMonth()+1,
                startYear: startDate.getFullYear(),
                endDay: endDate.getDate(),
                endMonth: endDate.getMonth()+1,
                endYear: endDate.getFullYear(),
                ghost: ghost
            })
                .done(function (data) {
                    jQplotData = data;
                    totalCalcul(jQplotData);
                    jsonSuccessLoad();
                    if (callback) {
                        callback();
                    }
                })
                .fail(jsonFailLoad);
        }

        function initJqplotData(json) {
            var series = [];
            var seriesColors = [];
            for(i = 0; i<json.series.length; i++){
                series.push(json.series[i].graph);
                seriesColors.push(json.series[i].color);
            }
            var ticks = [];

            // Number of days to display ( = graph.length in one serie)
            if( typeof json.series[0].graphLabel === 'undefined' ){
                var days = json.series[0].graph.length;
                // Add days to xaxis
                for (var i = 1; i < days+1; ++i) {
                    ticks.push([i-1, i]);
                }

            }else {
                var days = json.series[0].graphLabel.length;
                var val = json.series[0].graphLabel;
                var mod = Math.floor(days/33 +1);
                // Add days to xaxis
                for (var i = 0; i < days; ++i) {
                    if (i % mod === 0){
                        ticks.push([val[i][0], val[i][1]]);
                    }
                }
                ticks.push([days-1," "])
            }
            jQPlotsOptions.axes.xaxis.ticks = ticks;

            // Graph title
            jQPlotsOptions.title = json.title;

            // Graph series colors
            jQPlotsOptions.seriesColors = seriesColors;

            if (series.length > 1){
                jQPlotsOptions.legend.show = true;
                jQPlotsOptions.legend.labels = [startDate.getFullYear(), startDate.getFullYear()-1];
            }
            else {
                jQPlotsOptions.legend.show = false;
            }

            return series;
        }

        function setDataTable(tableId) {
            $.ajax({
                url: url +
                '?startDay=' + startDate.getDate() +
                '&startMonth=' + (startDate.getMonth()+1) +
                '&startYear=' + startDate.getFullYear() +
                '&endDay=' + endDate.getDate() +
                '&endMonth=' + (endDate.getMonth()+1) +
                '&endYear=' + endDate.getFullYear()
            }).success(function (json) {

                var tableJQ = $('#' + tableId);
                var table = document.getElementById(tableId);

                if (dt) {
                    dt.destroy();
                    tableJQ.empty();
                }

                table.innerHTML = "";

                // Ajout des header
                var action = url.split('/');
                if (action[action.length - 1] === "bestSales") {
                    setBestSalesTable(json, table, tableJQ);
                }
                else {
                    var head = table.createTHead();
                    var keys = [];

                    var row = head.insertRow(0);
                    var titles = json.series[0].thead;
                    for (var key in titles) {
                        keys.push(key);
                        var cell = row.insertCell(-1);
                        cell.innerHTML = titles[key];
                    }

                    // Ajout des données
                    var body = table.appendChild(document.createElement('tbody'));
                    var data = json.series[0].table;
                    for (var idx in data) {
                        var line = data[idx];
                        row = body.insertRow(-1);
                        for (var k in keys) {
                            key = keys[k];
                            cell = row.insertCell(-1);
                            cell.innerHTML = line[key];
                        }
                    }
                    dt = tableJQ.DataTable({
                        "paging": false,
                        "ordering": false,
                        "info": false,
                        "searching": false
                    })
                }
            });
        }

        function setBestSalesTable(json, table, tableJQ){
            $('#best-sales-tool-div').show();
            var head = table.createTHead();
            keys = [];
            var mhead = json.series[0].mhead;
            var row = head.insertRow(0);
            var cell = row.insertCell(-1);
            cell.innerHTML = '';
            cell.colSpan = 3;
            for (var index in mhead){
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
            for(var idx in data){
                var line = data[idx];
                row = body.insertRow(-1);
                for(var k in keys){
                    key = keys[k];
                    cell = row.insertCell(-1);
                    cell.innerHTML = line[key];
                    if(k <= 1){
                        var productUrl = baseAdminUrl + "/products/update?product_id=" + line['product_id'];
                        cell.innerHTML = "<a href='" + productUrl + "'>" + line[key] + "</a>";
                        cell.setAttribute("data-sort", line[key].normalize("NFD").replace(/[\u0300-\u036f]/g, ""));
                    }
                    if(k >= 6 && k <= 8){
                        var dataSort = line[key].toString().replace(",", ".");
                        dataSort = Number(dataSort.replace(/[^0-9.-]+/g, ""));
                        cell.setAttribute("data-sort", dataSort.toString());
                    }
                }
                cell = row.insertCell(-1);
                cell.innerHTML = "<button class='btn btn-default glyphicon glyphicon-chevron-down button-details' data-product='" + line['product_id'] + "'/>"
            }
            var footer = table.appendChild(document.createElement('tfoot')).insertRow(-1);
            var footerData = json.series[0].footer;
            for(var id in footerData){
                cell = footer.insertCell(-1);
                cell.innerHTML = footerData[id];
            }
            dt = tableJQ.DataTable({
                "lengthChange": false,
                "pageLength": 30,
                "order":[[3, "desc"]],
                "columnDefs": [
                    {"width": "40%", "targets":0},
                    {
                        "targets": 9,
                        "className": 'best-sales-details',
                        "orderable": false
                    }
                ]
            });

            $('.dataTables_filter').hide();

            $('#table-general tbody').on('click', 'td.best-sales-details', function () {
                var tr = $(this).closest('tr');
                var row = dt.row( tr );
                var button = tr.find(".button-details");
                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                    button.removeClass("glyphicon-chevron-up").addClass("glyphicon-chevron-down");
                }
                else {
                    getDetail(button.attr('data-product'), row, tr, button);
                }
            });
        }

        $("#best-sale-brand").on('change', function() {
            var brand = '^' + this.options[this.selectedIndex].text + '$';
            if(this.value === ""){
                brand = this.value
            }
            dt.columns(2).search(brand, true, false, true).draw();
        });

        $("#best-sale-search").keyup(function() {
            console.log($(this).val());
            dt.search($(this).val()).draw();
        });

        function jsonFailLoad(data) {
            $('#' + chartId + '').html('<div class="alert alert-danger">An error occurred while reading from JSON file</div>');
        }

        function jsonSuccessLoad() {
            // Init jQPlot
            var series = initJqplotData(jQplotData);

            // Start jQPlot
            if (jQPlotInstance) {
                jQPlotInstance.destroy();
            }
            jQPlotInstance = $.jqplot(chartId, series, jQPlotsOptions);

            $(window).bind('resize', function (event, ui) {
                jQPlotInstance.replot({resetAxes: true});
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
                button.removeClass("glyphicon-chevron-down").addClass("glyphicon-chevron-up");
            })
        }

    });
})(jQuery);
