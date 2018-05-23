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
        var date = new Date();
        var month = date.getMonth()+1;
        var year = date.getFullYear();

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
                markerOptions: {shadow: false, style: 'filledCircle', size: 12}
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
            }
        };

        $('.date-picker').datepicker( {
            format: 'mm/yyyy',
            minViewMode: 1,
            language: "fr"
        }).on('changeDate', function(e){
            date = e.date;
            month = date.getMonth()+1;
            year = date.getFullYear();

            updateContent();
        });

        $('.date-picker').datepicker('update', new Date());

        $('.general-graph-select').click(function (e) {
            type = this.dataset.type;
            targetId = this.dataset.toggle;
            url = this.dataset.url;

            updateContent();
        });

        // Get initial data Json
        retrieveJQPlotJson(jQplotDate.getMonth()+1, jQplotDate.getFullYear());

        $('.general-graph-select').click(function () {
            $(".general-graph-select").removeClass("active");
            $(this).toggleClass('active');
            jsonSuccessLoad();
        });

        function updateContent() {
            if (type.indexOf('jqplot')!==-1) {
                if( type.indexOf(',')!==-1){
                    id = targetId.split(',')[1];
                }else{
                    id = targetId;
                }
                $('.jqplot-content').show();
                $('#jqplot-general').css("width","100%");
                
                retrieveJQPlotJson(month, year);
            } else {
                $('.jqplot-content').hide();

            }
            
            if (type.indexOf('table')!==-1) {
                if( type.indexOf(',')!==-1){
                    id = targetId.split(',')[0];
                }else{
                    id = targetId;
                }
                $('.table-content').css("display","block");
                setDataTable(id)
            } else {
                $('.table-content').css("display","none");
            }
        }

        function retrieveJQPlotJson(month, year, callback) {

            $.getJSON(url, {month: month, year: year})
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
            series.push(json.series[0].graph);
            seriesColors.push(json.series[0].color);
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
                // Add days to xaxis
                for (var i = 0; i < days; ++i) {
                    ticks.push([i, val[i]]);
                }
            }
            jQPlotsOptions.axes.xaxis.ticks = ticks;

            // Graph title
            jQPlotsOptions.title = json.title;

            // Graph series colors
            jQPlotsOptions.seriesColors = seriesColors;

            return series;
        }

        function setDataTable(tableId) {
            $.ajax({
                url: url + '?month=' + month + '&year=' + year
            }).success(function (json) {
                var table = document.getElementById(tableId);
                table.innerHTML = "";

                var head = table.createTHead();

                var keys = [];
                // Ajout des header
                var row = head.insertRow(0);
                var titles = json.series[0].thead;
                for (var key in titles) {
                    keys.push(key);
                    var cell = row.insertCell(-1).outerHTML = '<th class="text-left">'+titles[key]+"</th>";
                }

                // Ajout des données
                var body = table.appendChild(document.createElement('tbody'));
                var data = json.series[0].table;
                for(var idx in data){
                    var line = data[idx];
                    var row = table.insertRow(-1);
                    for(var k in keys){
                        var key = keys[k];
                        var cell = row.insertCell(-1).outerHTML = '<td class="text-left">'+line[key]+"</td>";
                    }
                }
                
                if (data.length === 0) {
                    table.insertRow(-1).insertCell(-1).outerHTML = '<td class="text-left" colspan="99">Aucune donnée disponible.</td>';
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
