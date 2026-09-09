jQuery(document).ready(function(){

    var custom_uploader;
    jQuery('.select-thumbs').click(function(e) {
    e.preventDefault();
    //If the uploader object has already been created, reopen the dialog
    if (custom_uploader) {
        custom_uploader.open();
        return;
    }
    //Extend the wp.media object
    custom_uploader = wp.media.frames.file_frame = wp.media({
        title: 'Add thumbnails',
        button: {
        text: 'Choose image(s)'
        },
        multiple: 'add'
    });
    custom_uploader.on('select', function() {
        var selection = custom_uploader.state().get('selection'); console.log(selection);
        selection.map( function( attachment ) {
            
            attachment = attachment.toJSON();
            
            jQuery.ajax({
                type       : "POST",
                url        : admin_ajax_var.url,
                data       : {
                                action      : 'xbox_ajax_insert_thumb',
                                nonce       : admin_ajax_var.nonce,
                                current_url : window.location.href,
                                thumb_url   : attachment.url
                },
                dataType   : "json",
                beforeSend : function(){

                },
                success    : function(data){
                    if( data.result === true ){
                    jQuery(".thumbs-list").append('<li><img class="xbox-image xbox-image-loading" src="' + attachment.url + '"><a class="xbox-btn xbox-btn-iconize xbox-btn-small xbox-btn-red xbox-remove-preview"><i class="xbox-icon xbox-icon-times-circle"></i></a></li>');
                    jQuery(".thumbs-list img").on('load', function(){
                            jQuery(this).addClass('xbox-image-loaded');
                    });
                    }
                },
                error     : function(jqXHR, textStatus, errorThrown) {
                    console.error(errorThrown);
                }
            });

        });
    });
    custom_uploader.open();
    });

    jQuery('.xbox-metabox').parent('.inside').attr('id', 'wp-script');

    jQuery('.thumbs-list').on('click', '.xbox-remove-preview', function(e){
        e.preventDefault();
        var thumb_url = jQuery(this).prev('img').attr('src');
        var self = this;

        jQuery.ajax({
            type       : "POST",
            url        : admin_ajax_var.url,
            data       : {
                            action      : 'xbox_ajax_remove_thumb',
                            nonce       : admin_ajax_var.nonce,
                            current_url : window.location.href,
                            thumb_url   : thumb_url
            },
            dataType   : "json",
            beforeSend : function(){
                jQuery(self).parent('li').children('i.fa-spinner').show();
            },
            success    : function(data){
                jQuery(self).parent('li').children('i.fa-spinner').hide();
                if( data.result === true ){
                    jQuery(self).parent('li').fadeOut(300);
                }
            },
            error     : function(jqXHR, textStatus, errorThrown) {
                jQuery(self).parent('li').children('i.fa-spinner').hide();
                console.error(errorThrown);
            }
        });
    });
    
});