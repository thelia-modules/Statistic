
(function ($) {
    $(document).ready(function () {
        var url = baseAdminUrl + '/module/statistic/turnover';
        var chartId = 'jqplot-annual';

        var jQplotDate = new Date();
        jQplotDate.setDate(1); // Set day to 1 so we can add month without 30/31 days of month troubles.
        var jQplotData; // json data
        var jQPlotInstance; // global instance


        var type = "jqplot-annual";
        var targetId = "turnover";
        var dataTarget = "";
        var startDate = new Date();
        startDate.setFullYear(startDate.getFullYear() - 1);
        var endDate = new Date();


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
                    return Math.round(plot.data[seriesIndex][pointIndex][1]*100)/100;
                }
            },
            legend: {
                show: false
            }
        };

        $('.annual-date-picker').datepicker({
            format: 'yyyy',
            minViewMode: 2,
            language: "fr"
        }).on('changeDate', function (e) {
            startDate = e.date;

            updateContent();
        });

        $('.annual-date-picker2').datepicker({
            format: 'yyyy',
            minViewMode: 2,
            language: "fr"
        }).on('changeDate', function (e) {
            endDate = e.date;

            updateContent();
        });

        $('.annual-date-picker').datepicker('update', startDate);
        $('.annual-date-picker2').datepicker('update', endDate);

        // Get initial data Json
        retrieveJQPlotJson(startDate, endDate);

        $('.annual-graph-select').click(function () {
            type = this.dataset.type;
            dataTarget = this.dataset.target;
            targetId = this.dataset.toggle;
            url = this.dataset.url;
            $(".anual-graph-select").removeClass("active");
            $(this).toggleClass('active');
            updateContent();
        });

        function updateContent() {
            if (type.indexOf('jqplot')!==-1) {
                $('.jqplot-content').show();
                $('#jqplot-annual').css("width","100%");
                retrieveJQPlotJson(startDate, endDate);
            } else {
                $('.jqplot-content').hide();
            }
        }

        function totalCalcul(jQplotData) {
            let total = 0;

            jQplotData.series.forEach(entry => {
                for (let i = 0; i < entry.graph.length; i++)
                    total += entry.graph[i][1];
            });

            total = Math.round(total * 100) / 100;

            let s = document.getElementById('total-annual');
            $(s.parentElement).removeClass("hide");
            s.innerHTML = total.toString();
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
