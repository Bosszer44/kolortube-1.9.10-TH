( function( $ ) {
    wp.customizerCtrlEditor = {

        init: function() {

            $(window).load(function(){

                $('textarea.wp-editor-area').each(function(){
                    var tArea = $(this),
                        id = tArea.attr('id'),
                        editor = tinyMCE.get(id),
                        setChange,
                        content;

                    if(editor){
                        editor.onChange.add(function (ed, e) {
                            ed.save();
                            content = editor.getContent();
                            clearTimeout(setChange);
                            setChange = setTimeout(function(){
                                tArea.val(content).trigger('change');
                            },500);
                        });
                    }

                    tArea.css({
                        visibility: 'visible'
                    }).on('keyup', function(){
                        content = tArea.val();
                        clearTimeout(setChange);
                        setChange = setTimeout(function(){
                            content.trigger('change');
                        },500);
                    });
                });
            });
        }

    };

    wp.customizerCtrlEditor.init();

} )( jQuery );

/* Unified color system: changing any manual color makes Custom the active preset. */
(function () {
    function bindUnifiedColorControls() {
        if (!window.wp || !wp.customize) return;
        var preset = wp.customize('wps_color_preset');
        if (!preset) return;
        [
            'main_color', 'link_color', 'wps_site_background_color',
            'wps_menu_background_color', 'wps_button_start_color',
            'wps_button_middle_color', 'wps_button_end_color'
        ].forEach(function (id) {
            var setting = wp.customize(id);
            if (!setting) return;
            setting.bind(function () {
                if (window.__wpsApplyingPreset) return;
                if (preset.get() !== 'custom') preset.set('custom');
            });
        });
    }
    if (window.wp && wp.customize) {
        wp.customize.bind('ready', bindUnifiedColorControls);
    } else {
        jQuery(bindUnifiedColorControls);
    }
})();
