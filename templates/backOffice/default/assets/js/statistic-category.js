/**
 * Created by doud on 08/06/15.
 */

(function ($) {
    $(document).ready(function(){
        var categoryUrl = baseAdminUrl + '/module/statistic/category/turnover';
        var id = "jqplot-category";
        var jQPlotInstanceCategory; // global instance

        const categoryDatePicker = $('.category-date-picker');
        const categoryDatePicker2 = $('.category-date-picker2');
        const categorySelect = $('#category-select-tab');

        var categoryDate = new Date();
        categoryDate.setMonth(categoryDate.getMonth() - 1);
        var categoryDate2 = new Date();
        var ref = categorySelect.val();
        var type ="";
        var ghost = 0;



        categoryDatePicker.datepicker( {
            format: 'dd/mm/yyyy',
            minViewMode: 0,
            language: "fr"
        }).on('changeDate', function(e){
            categoryDate = e.date;

            updateCategoryContent();
        });

        categoryDatePicker2.datepicker( {
            format: 'dd/mm/yyyy',
            minViewMode: 0,
            language: "fr"
        }).on('changeDate', function(e){
            categoryDate2 = e.date;

            updateCategoryContent();
        });

        categoryDatePicker.datepicker('update', categoryDate);
        categoryDatePicker2.datepicker('update', categoryDate2);

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

        $('#add-ghost-curve-category').click(function () {
            ghost = ghost === 0 ? 1 : 0;

            if ($(this).hasClass("active")){
                $(this).removeClass("active");
            }
            else {
                $(this).toggleClass('active');
            }
            updateCategoryContent();
        });

        function updateCategoryContent() {
            if (type.indexOf('jqplot')!==-1) {
                $('.jqplot-content').show();
                $('#jqplot-category').show();
                $('.total').css("display","block");
                retrieveJQPlotJson(ref, categoryDate, categoryDate2, ghost);
            }else {
                $('.jqplot-content').hide();
                $('.total').hide();
            }
        }


        function retrieveJQPlotJson(categoryId, categoryDate, categoryDate2, ghost, callback) {

            $.getJSON(categoryUrl, {
                categoryId: categoryId,
                startDay: categoryDate.getDate(),
                startMonth: categoryDate.getMonth()+1,
                startYear: categoryDate.getFullYear(),
                endDay: categoryDate2.getDate(),
                endMonth: categoryDate2.getMonth()+1,
                endYear: categoryDate2.getFullYear(),
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

            let s = document.getElementById('total-category');
            $(s.parentElement).removeClass("hide");
            s.innerHTML = total.toString();
        }

        function initJqplotData(json) {
            console.log(json)
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
                jQPlotsOptions.legend.labels = [categoryDate.getFullYear(), categoryDate.getFullYear()-1];
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
            if (jQPlotInstanceCategory) {
                jQPlotInstanceCategory.destroy();
            }
            jQPlotInstanceCategory = $.jqplot(id, series, jQPlotsOptions);

            $(window).bind('resize', function (event, ui) {
                jQPlotInstanceCategory.replot({resetAxes: true});
            });
        }

        $('.category-graph-select').click(function (e) {
            if (!$(this).hasClass('active')){
                type = this.dataset.type;
                $('.category-graph-select').removeClass('active');
                $(this).toggleClass('active');
                categoryUrl = this.dataset.url;
                id = this.dataset.toggle;
                $('.jqplot-content').show();
                updateCategoryContent();
            }
        });

        categorySelect.change(function(e){
            ref = $(this).val();
            updateCategoryContent()
        });

        $('.js-btn-search-category-tab').on('click', function(event) {
            event.preventDefault();
            setModalSearch('js-modal-search-category');
            $modalSearch.initAndShow(
                this.dataset.title,
                baseAdminUrl + "/module/statistic/category/search",
                '#category-select-tab',
                this.dataset.placeholder,
                null
            );
        });
    });
})(jQuery);
