/**
 * Created by doud on 08/06/15.
 */

(function ($) {
    $(document).ready(function () {
        let productUrl = baseAdminUrl + '/module/statistic/product/turnover';
        let id = "jqplot-product";
        let jQPlotInstanceProduct; // global instance
        let productDate = new Date();
        let productyear = new Date();

        $('.product-date-picker').datepicker({
            format: 'yyyy',
            ViewMode: "years",
            minViewMode: "years",
            language: "fr"
        }).on('changeDate', function (e) {
            productDate = e.date;
            productyear = productDate.getFullYear();
            setDataPlot(productUrl, id);
        });

        $('.product-date-picker').datepicker('update', new Date());

        function setDataPlot(url, chartId) {

            let jQplotDate = productDate;
            jQplotDate.setDate(1); // Set day to 1 so we can add month without 30/31 days of month troubles.
            let jQplotData; // json data

            //{literal}

            let jQPlotsOptions = {
                animate: true,
                axesDefaults: {
                    tickOptions: { showMark: true, showGridline: true }
                },
                axes: {
                    xaxis: {
                        borderColor: '#ccc',
                        ticks: [],
                        tickOptions: { showGridline: false, showLabel: true }
                    },
                    yaxis: {
                        min: 0,
                        tickOptions: { showGridline: true, showMark: false, showLabel: true, shadow: false }
                    }
                },
                seriesDefaults: {
                    lineWidth: 3,
                    shadow: false,
                    markerOptions: { shadow: false, style: 'filledCircle', size: 12 }
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

            // Get initial data Json
            let ref = $('#product-select').val();
            retrieveJQPlotJson(ref, jQplotDate.getMonth() + 1, jQplotDate.getFullYear());

            $('[data-toggle="' + chartId + '"]').click(function () {

                $('#jqplot-product').toggleClass('active');
                jsonSuccessLoad();

            });

            $('.js-stats-change-month').click(function (e) {
                $('.js-stats-change-month').attr('disabled', true);
                jQplotDate.setMonth(parseInt(jQplotDate.getMonth()) + parseInt($(this).data('month-offset')));
                retrieveJQPlotJson(jQplotDate.getMonth() + 1, jQplotDate.getFullYear(), function () {
                    $('.js-stats-change-month').attr('disabled', false);
                });

            });

            function retrieveJQPlotJson(productRef, month, productyear, callback) {

                $.getJSON(url, { ref: productRef, month: month, year: productyear })
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
                let series = [];
                let ticks = [];
                let seriesColors = [];
                series.push(json.series[0].graph);
                seriesColors.push(json.series[0].color);

                // Number of days to display ( = graph.length in one serie)
                if (typeof json.series[0].graphLabel === 'undefined') {
                    let months = json.series[0].graph.length;
                    // Add days to xaxis
                    for (let i = 0; i < months; ++i) {
                        ticks.push([i - 1, i]);
                    }

                } else {
                    let months = json.series[0].graphLabel.length;
                    let val = json.series[0].graphLabel;
                    // Add days to xaxis
                    for (let i = 0; i < months; ++i) {
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

            function jsonFailLoad(data) {
                $('#' + chartId + '').html('<div class="alert alert-danger">An error occurred while reading from JSON file</div>');
            }

            function jsonSuccessLoad() {

                // Init jQPlot
                let series = initJqplotData(jQplotData);

                // Start jQPlot
                if (jQPlotInstanceProduct) {
                    jQPlotInstanceProduct.destroy();
                }
                jQPlotInstanceProduct = $.jqplot(chartId, series, jQPlotsOptions);

                $(window).bind('resize', function (event, ui) {
                    jQPlotInstanceProduct.replot({ resetAxes: true });
                });

            }
        }

        $('.product-graph-select').click(function (e) {
            let type = this.dataset.type;
            $('.product-graph-select').removeClass('active');
            $(this).toggleClass('active');
            productUrl = this.dataset.url;
            if (type.indexOf('jqplot') !== -1) {
                if (type.indexOf(',') !== -1) {
                    id = this.dataset.toggle.split(',')[1];
                } else {
                    id = this.dataset.toggle;
                }
                $('.jqplot-content').show();
                setDataPlot(productUrl, id);
            }
        });

        // Modification de la liste des produits par catégories
        $('#category-select').change(function (e) {
            let url = $('#product-select').data('url');
            $.ajax({
                url: url + '?category=' + this.value
            }).success(function (data) {

                let $select = $('#product-select');
                $select.html('<option value="">Produit...</option>');
                for (let k in data) {
                    let prod = data[k];
                    $select.append('<option value=' + prod["Ref"] + '>' + prod["i18n_TITLE"] + '</option>')
                }
            });
        });

        $("#product-select").change(function (e) {
            setDataPlot(productUrl, id);
        });


    });
})(jQuery);
