jQuery(document).ready(function($) {
    jQuery('.yelp-form').on('change', function() {
        var term = this.value;
        var location = jQuery('#location').html();
        var radius = jQuery('#radius').html();
        var limit = jQuery('#limit').html();
        jQuery.ajax({
            type: 'POST',
            url : ajax_object.ajax_url,
            cache: false,
            data : { 'action': 'yc_my_community', 'search': term, 'location': location, 'radius': radius, 'limit':limit },
            complete : function() {  },
            success: function(data) {
                jQuery('.community-info').html(data).fadeIn('fast');
            }
        });
    });
});