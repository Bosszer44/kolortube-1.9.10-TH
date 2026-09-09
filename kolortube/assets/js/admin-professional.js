(function($){
  'use strict';
  $(document).on('click','.wps-choose-image',function(e){
    e.preventDefault();
    var button=$(this), input=button.closest('.wps-media-row').find('.wps-image-url');
    var frame=wp.media({title:WPSProfessionalAdmin.chooseImage,button:{text:WPSProfessionalAdmin.useImage},multiple:false,library:{type:'image'}});
    frame.on('select',function(){
      var item=frame.state().get('selection').first().toJSON();
      input.val(item.url).trigger('change');
      button.closest('.wps-banner-card').find('.wps-banner-preview').html($('<img>',{src:item.url,alt:''}));
    });
    frame.open();
  });

  $(document).on('click','.wps-choose-image-id',function(e){
    e.preventDefault();
    var button=$(this), row=button.closest('.wps-media-id-row'), input=row.find('.wps-image-id'), preview=row.find('.wps-media-preview');
    var frame=wp.media({title:WPSProfessionalAdmin.chooseImage,button:{text:WPSProfessionalAdmin.useImage},multiple:false,library:{type:'image'}});
    frame.on('select',function(){
      var item=frame.state().get('selection').first().toJSON();
      var src=(item.sizes&&item.sizes.thumbnail?item.sizes.thumbnail.url:item.url);
      input.val(item.id).trigger('change');
      preview.html($('<img>',{src:src,alt:'',css:{maxWidth:'160px',maxHeight:'80px'}}));
    });
    frame.open();
  });
  $(document).on('click','.wps-remove-image-id',function(e){
    e.preventDefault();
    var row=$(this).closest('.wps-media-id-row');
    row.find('.wps-image-id').val('').trigger('change');
    row.find('.wps-media-preview').empty();
  });

  $(document).on('input change','.wps-image-url',function(){
    var url=$(this).val();
    var preview=$(this).closest('.wps-banner-card').find('.wps-banner-preview');
    if(url){ preview.html($('<img>',{src:url,alt:''})); }
  });
})(jQuery);
