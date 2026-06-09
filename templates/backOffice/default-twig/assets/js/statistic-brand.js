/**
 * Created by doud on 08/06/15.
 */

(function ($) {
    $(document).ready(function(){
        var brandUrl = baseAdminUrl + '/module/statistic/brand/turnover';
        var id = "jqplot-brand";
        var jQPlotInstance; // global instance
        var startDate = new Date();
        startDate.setMonth(startDate.getMonth() - 1);
        var endDate = new Date();
        var ghost = 0;
        var type ="";

        $('#brand-date-picker').datepicker( {
            format: 'dd/mm/yyyy',
            minViewMode: 0,
            language: "fr"
        }).on('changeDate', function(e){
            startDate = e.date;

            updateBrandContent();
        });

        $('#brand-date-picker2').datepicker( {
            format: 'dd/mm/yyyy',
            minViewMode: 0,
            language: "fr"
        }).on('changeDate', function(e){
            endDate = e.date;

            updateBrandContent();
        });

        $('#brand-date-picker').datepicker('update', startDate);
        $('#brand-date-picker2').datepicker('update', endDate);



        var jQplotData; // json data

        //{literal}

        var jQPlotsOptions = {
            animate: true,
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
                    return Math.round(plot.data[seriesIndex][pointIndex][1]*100)/100;
                }
            },
            legend: {
                show: false
            }
        };

        // Get initial data Json
        var brandId = null;

        $('#add-ghost-curve-brand').click(function () {
            ghost = ghost === 0 ? 1: 0;

            if ($(this).hasClass("active")){
                $(this).removeClass("active");
            }
            else {
                $(this).toggleClass('active');
            }
            updateBrandContent();
        });

        function updateBrandContent() {
            if (type.indexOf('jqplot')!==-1) {
                $('.jqplot-content').show();
                $('#jqplot-brand').show();
                $('.total').css("display","block");
                retrieveJQPlotJson(brandId, startDate, endDate, ghost);
            }else {
                $('.jqplot-content').hide();
                $('.total').hide();
            }
        }

        function retrieveJQPlotJson(brandId, startDate, endDate, ghost, callback) {

            $.getJSON(brandUrl, {
                brandId: brandId,
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

        function totalCalcul(jQplotData) {
            let total = 0;

            jQplotData.series.forEach(entry => {
                entry.graph.forEach(graph => {
                    total += graph[1];
                });
            });

            total = Math.round(total * 100) / 100;

            let s = document.getElementById('total-brand');
            $(s.parentElement).removeClass("hide");
            s.innerHTML = total.toString();
        }

        function initJqplotData(json) {
            var series = [];
            var ticks = [];
            var seriesColors = [];
            for (var i = 0; i<json.series.length ;i++ ){
                series.push(json.series[i].graph);
                seriesColors.push(json.series[i].color);
            }

            // Number of days to display ( = graph.length in one serie)
            if( typeof json.series[0].graphLabel === 'undefined' ){
                var days = json.series[0].graph.length;
                // Add days to xaxis
                for (var i = 0; i < days; ++i) {
                    ticks.push([i, i]);
                }

            }else {
                var days = json.series[0].graphLabel.length;
                var val = json.series[0].graphLabel;
                var mod = Math.floor(days/33 +1);
                // Add days to xaxis
                for (var i = 0; i < days; ++i) {
                    if (i % mod === 0){
                        ticks.push([i, val[i]]);
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

            jQPlotInstance = $.jqplot(id, series, jQPlotsOptions);

            $(window).bind('resize', function (event, ui) {
                jQPlotInstance.replot({resetAxes: true});
            });

        }

        $('.brand-graph-select').click(function (e) {
            if (!$(this).hasClass('active')){
                type = this.dataset.type;
                $('.brand-graph-select').removeClass('active');
                $(this).toggleClass('active');
                brandUrl = this.dataset.url;
                id = this.dataset.toggle;
                $('.jqplot-content').show();
                updateBrandContent();
            }
        });

        $("#brand-select").on('change', function() {
            brandId = $('#brand-select').val();
            updateBrandContent();
        });


        $('.js-btn-search-brand').on('click', function(event){
            event.preventDefault();
            setModalSearch('js-modal-search-brand');
            $modalSearch.initAndShow(
                this.dataset.title,
                baseAdminUrl+"/module/statistic/brand/search",
                '#brand-select',
                this.dataset.placeholder,
                null
            );
        });

    });
})(jQuery);
