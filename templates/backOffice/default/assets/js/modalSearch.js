var $modalSearch;
$(document).ready(function(){

    // Search categories and products

    $modalSearch = $('.js-modal-search-product');


    $modalSearch.initAndShow = function(title, searchUrl, selectSelector, searchPlaceholder, categoryIdToFilter){
        categoryIdToFilter = (typeof categoryIdToFilter !== 'undefined') ?  categoryIdToFilter : null;

        $modalSearch.find('.modal-header h3').text(title);
        $modalSearch.data('url', searchUrl);
        $modalSearch.data('selectselector', selectSelector);
        if(categoryIdToFilter != null){
            $modalSearch.data('categoryid', categoryIdToFilter);
            $modalSearch.data('filterbycategory', '1');
        }else{
            $modalSearch.data('filterbycategory', '0');
        }

        $modalSearch.find('.js-input-search').attr('placeholder',searchPlaceholder);

        $modalSearch.modal('show');
    };

    $modalSearch.dismiss = function(){
        $modalSearch.find('.js-block-search-result').addClass('hide');
        $modalSearch.find('.js-alert-no-result').addClass('hide');
        $modalSearch.find('.js-input-search').val('');
        $modalSearch.modal('hide');
    };

    $modalSearch.on('click', '.js-modal-btn-select', function(e){
        e.preventDefault();
        var selectedVal = $modalSearch.find('.js-block-search-result select').val();
        console.log(selectedVal)
        if(selectedVal != null) {
            $($modalSearch.data('selectselector')).val(selectedVal).trigger('change');
            $modalSearch.dismiss();
        }
    });

    $modalSearch.on('click', '.js-btn-dismiss-modal', function(e) {
        e.preventDefault();
        $modalSearch.dismiss();
    });

    var searchTimer = null;
    $modalSearch.on('keyup' ,'.js-input-search', function(){
        var val = $(this).val();

        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }

        $modalSearch.find('.js-alert-no-result').addClass('hide');

        var ajaxData = { q: val };
        if($modalSearch.data('filterbycategory') == '1'){
            ajaxData['category_id'] = $modalSearch.data('categoryid');
        }

        if (val.length > 2) {
            $modalSearch.find('.js-loader').removeClass('hide');

            searchTimer = setTimeout(function() {
                $.ajax({
                    url: $modalSearch.data('url'),
                    data: ajaxData,
                    dataType: 'json',
                    method: 'GET',
                    success: function(data) {
                        $modalSearch.find('.js-loader').addClass('hide');
                        var options = [];
                        for (var id in data) {
                            options.push('<option value="' + id + '">' + data[id] + '</option>');
                        }

                        if (options.length > 0) {
                            $modalSearch.find(".js-block-search-result select").html(options.join(''));
                            $modalSearch.find('.js-block-search-result').removeClass('hide');
                        } else {
                            this.error();
                        }
                    },
                    error: function() {
                        $modalSearch.find('.js-loader').addClass('hide');

                        $modalSearch.find('.js-block-search-result').addClass('hide');
                        $modalSearch.find('.js-alert-no-result').removeClass('hide');
                    }
                });
            }, 350, this);
        } else {
            $modalSearch.find('.js-loader').addClass('hide');
            $modalSearch.find('.js-block-search-result').addClass('hide');
        }
    });
});