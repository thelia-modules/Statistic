/**
 * Created by doud on 05/06/15.
 */

(function ($) {
    $(document).ready(function () {
        var url = baseAdminUrl + '/module/statistic/customer/stats';
        var chartId = 'jqplot-general';

        var jQplotDate = new Date();
        jQplotDate.setDate(1); // Set day to 1 so we can add month without 30/31 days of month troubles.
        var jQplotData; // json data
        var jQPlotInstance; // global instance


        var type = "jqplot-general";
        var targetId = "registration";
        var dataTarget = "";
        var startDate = new Date();
        startDate.setMonth(startDate.getMonth() - 1)
        var endDate = new Date();

        //{literal}

        var jQPlotsOptions = {
            animate: false,
            axesDefaults: {
                tickOptions: {showMark: true, showGridline: true}
            },
            axes: {
                xaxis: {
                    borderColor: '#ccc',
                    ticks: [],
                    tickOptions: {showGridline: false}
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
                    return Math.round(plot.data[seriesIndex][pointIndex][1]);
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
            type = this.dataset.type;
            dataTarget = this.dataset.target;
            targetId = this.dataset.toggle;
            url = this.dataset.url;

            updateContent();
        });

        // Get initial data Json
        retrieveJQPlotJson(startDate, endDate);

        $('.general-graph-select').click(function () {
            $(".general-graph-select").removeClass("active");
            $(this).toggleClass('active');
            document.getElementById('table-title').innerHTML = "";
            document.getElementById('table-title2').innerHTML = "";
            document.getElementById('table-general').innerHTML = "";
            document.getElementById('table-general2').innerHTML = "";
            jsonSuccessLoad();
        });

        function updateContent() {
            if (type.indexOf('jqplot')!==-1) {
                $('.jqplot-content').show();
                $('#jqplot-general').css("width","100%");
                retrieveJQPlotJson(startDate, endDate);
            } else {
                $('.jqplot-content').hide();

            }
            
            if (type.indexOf('table')!==-1) {
                var id = targetId.split(',');
                $('.table-content').css("display","block");
                setDataTable(id)
            } else {
                $('.table-content').css("display","none");
            }
        }

        function retrieveJQPlotJson(startDate, endDate, callback) {

            $.getJSON(url, {
                startDay: startDate.getDate(),
                startMonth: startDate.getMonth()+1,
                startYear: startDate.getFullYear(),
                endDay: endDate.getDate(),
                endMonth: endDate.getMonth()+1,
                endYear: endDate.getFullYear()
            })
                .done(function (data) {
                    jQplotData = data;
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
                ticks.push([days-1,''])
            }
            jQPlotsOptions.axes.xaxis.ticks = ticks;

            // Graph title
            jQPlotsOptions.title = json.title;

            // Graph series colors
            jQPlotsOptions.seriesColors = seriesColors;

            if (series.length > 1){
                jQPlotsOptions.legend.show = true;
                jQPlotsOptions.legend.labels = [startDate.getFullYear(), endDate.getFullYear()];
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
                for (i= 0; i < tableId.length; i++){
                    if (tableId.length > 1){
                        document.getElementById('table-title').innerHTML = startDate.getFullYear();
                        document.getElementById('table-title2').innerHTML = endDate.getFullYear();
                    }
                    var table = document.getElementById(tableId[i]);
                    table.innerHTML = "";

                    var head = table.createTHead();
                    var keys = [];
                    // Ajout des header
                    var row = head.insertRow(0);
                    var titles = json.series[i].thead;
                    for (var key in titles) {
                        keys.push(key);
                        var cell = row.insertCell(-1);
                        cell.innerHTML = titles[key];
                    }

                    // Ajout des données
                    var body = table.appendChild(document.createElement('tbody'));
                    var data = json.series[i].table;
                    for(var idx in data){
                        var line = data[idx];
                        var row = table.insertRow(-1);
                        for(var k in keys){
                            var key = keys[k];
                            var cell = row.insertCell(-1);
                            cell.innerHTML = line[key];
                        }
                    }

                    if (data.length === 0) {
                        table.insertRow(-1).insertCell(-1).outerHTML = '<td class="text-left" colspan="99">Aucune donnée disponible.</td>';
                    }
                }
            })
        }

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
    });
})(jQuery);
