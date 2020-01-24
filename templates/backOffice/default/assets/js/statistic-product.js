/**
 * Created by doud on 08/06/15.
 */

(function ($) {
    $(document).ready(function(){
        var productDatedate = new Date();
        var productUrl = baseAdminUrl + '/module/statistic/product/turnover';
        var id = "jqplot-product";
        var jQPlotInstanceProduct; // global instance
        var productDate = new Date();
        productDate.setFullYear(productDate.getFullYear()-1);
        var productDate2 = new Date();
        var productyear = productDate.getFullYear();
        var productyear2 = productDate2.getFullYear();
        var type ="";

        $('.product-date-picker').datepicker( {
            format: 'yyyy',
            minViewMode: "years",
            language: "fr"
        }).on('changeDate', function(e){
            productDate = e.date;
            productyear = productDate.getFullYear();

            setDataPlot(productUrl, id);
        });

        $('.product-date-picker2').datepicker( {
            format: 'yyyy',
            minViewMode: "years",
            language: "fr"
        }).on('changeDate', function(e){
            productDate2 = e.date;
            productyear2 = productDate2.getFullYear();

            setDataPlot(productUrl, id);
        });

        $('.product-date-picker').datepicker('update', productDate);
        $('.product-date-picker2').datepicker('update', productDate2);


        function setDataPlot(url, chartId) {
            var jQplotDate = productDate;
            jQplotDate.setDate(1); // Set day to 1 so we can add month without 30/31 days of month troubles.
            var jQplotData; // json data

            //{literal}

            var jQPlotsOptions = {
                animate: true,
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
                legend:{
                    show: true
                }
            };

            // Get initial data Json
            var ref = $('#product-select').val();
            retrieveJQPlotJson(ref, productyear, productyear2);


            function retrieveJQPlotJson(productRef, productyear, productyear2, callback) {

                $.getJSON(url, {ref: productRef, year: productyear, year2: productyear2})
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

                jQplotData.series.forEach(serie => {
                    for (let i = 0; i < serie.graph.length; i++)
                        total += serie.graph[i][1];
                });

                total = Math.round(total * 100) / 100;

                let s = document.getElementById('total-prod');
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

                jQPlotsOptions.legend.labels = [productyear, productyear2];

                return series;
            }

            function jsonFailLoad(data) {
                $('#' + chartId + '').html('<div class="alert alert-danger">An error occurred while reading from JSON file</div>');
            }

            function jsonSuccessLoad() {

                // Init jQPlot
                var series = initJqplotData(jQplotData);

                // Start jQPlot
                if (jQPlotInstanceProduct) {
                    jQPlotInstanceProduct.destroy();
                }
                jQPlotInstanceProduct = $.jqplot(chartId, series, jQPlotsOptions);

                $(window).bind('resize', function (event, ui) {
                    jQPlotInstanceProduct.replot({resetAxes: true});
                });

            }
        }

        $('.product-graph-select').click(function (e) {
            if (!$(this).hasClass('active')){
                type = this.dataset.type;
                $('.product-graph-select').removeClass('active');
                $(this).toggleClass('active');
                productUrl = this.dataset.url;
                id = this.dataset.toggle;
                $('.jqplot-content').show();
                setDataPlot(productUrl, id);
            }
        });

        // Modification de la liste des produits par catégories
        $('#category-select').change(function(e){
            var url = $('#product-select').data('url');
            $.ajax({
                url: url + '?category='+this.value
            }).success(function(data){
                var i = 0;
                $select = $('#product-select');
                $select.html('<option value="">Produit...</option>');
                for(var k in data){
                    var prod = data[k];
                    $select.append('<option value='+prod["Ref"]+'>'+prod["i18n_TITLE"]+'</option>')
                }
            });
        });

        $("#product-select").change(function(e){
            setDataPlot(productUrl, id);
        });

        // If there's an already loaded category, it loads all products.
        let current_val = $('#category-select').val()
        if (current_val)
            $("#category-select").val(current_val).trigger("change");

        $('.js-btn-search-product').on('click', function(event){
            event.preventDefault();
            $modalSearch.initAndShow(
                this.dataset.title,
                baseAdminUrl+"/module/statictic/products/search",
                '#product-select',
                this.dataset.placeholder,
                $('#category-select').val()
            );
        });

        $('.js-btn-search-category').on('click', function(event){
            event.preventDefault();
            $modalSearch.initAndShow(
                this.dataset.title,
                baseAdminUrl+"/module/statictic/category/search",
                '#category-select',
                this.dataset.placeholder
            );
        });
    });
})(jQuery);
