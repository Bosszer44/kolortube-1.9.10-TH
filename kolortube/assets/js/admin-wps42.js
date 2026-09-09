(function($){
    'use strict';
    $(document).on('submit','.avsora-confirm-form',function(e){
        var message=$(this).data('confirm')||'ยืนยันดำเนินการ?';
        if(!window.confirm(message)){e.preventDefault();}
    });
    $(document).on('click','.avsora-confirm-button',function(e){
        var message=$(this).data('confirm')||'ยืนยันดำเนินการ?';
        if(!window.confirm(message)){e.preventDefault();}
    });
    $(document).on('click','.avsora-preset',function(){
        var form=$(this).closest('.avsora-card').nextAll().find('form').addBack('form');
        var preset=$(this).data('preset');
        $('.avsora-group-checkboxes input[type=checkbox]').prop('checked',false);
        if(preset==='css'){$('.avsora-group-checkboxes input[value=css]').prop('checked',true);}
        if(preset==='seo'){$('.avsora-group-checkboxes input[value=seo]').prop('checked',true);}
        if(preset==='all'){$('.avsora-group-checkboxes input[type=checkbox]').prop('checked',true);}
        if(preset==='safe'){$('.avsora-group-checkboxes input[type=checkbox]').not('[value=appearance],[value=theme_all]').prop('checked',true);}
    });
})(jQuery);
