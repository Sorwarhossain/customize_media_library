( function( $ ) {

    var orderValue;



    $( document ).ready( function() {

        orderValue = $('#wpesq3_ml_lib_options_media_order').val();
        $('#wpesq3_ml_lib_options_media_orderby').change();
        $('#wpesq3_ml_lib_options_grid_show_caption').change();
    });



    $( document ).on( 'change', '#wpesq3_ml_lib_options_media_orderby', function( event ) {

        var isMenuOrder = 'menuOrder' === $( event.target ).val(),
            isTitleOrder = 'title' === $( event.target ).val(),
            value;

        orderValue = isMenuOrder ? $('#wpesq3_ml_lib_options_media_order').val() : orderValue;
        value = isMenuOrder ? 'ASC' : orderValue;

        $('#wpesq3_ml_lib_options_media_order').prop( 'disabled', isMenuOrder ).val( value );
        $('#wpesq3_ml_lib_options_natural_sort').prop( 'hidden', ! isTitleOrder );
    });



    $( document ).on( 'change', '#wpesq3_ml_lib_options_grid_show_caption', function( event ) {

        var isChecked = $(this).prop( 'checked' );

        $('#wpesq3_ml_lib_options_grid_caption_type').prop( 'hidden', ! isChecked );
    });

})( jQuery );
