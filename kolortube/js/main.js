jQuery( document ).ready( function() {

  // Video Previews Manager
  (function videosPreviewsManager() {
    var videosPreviewsXhrs = {};
    var videosPreviewsBuffer = {};

    jQuery( '.video-with-trailer' ).each( function( index, value ) {
      var $videoPreview = jQuery( this ).find( '.video-preview' );
      var postId = jQuery( this ).data( 'post-id' );
      var $videoDebounceBar = jQuery( this ).find( '.video-debounce-bar' );
      var $videoImg = jQuery( this ).find( '.video-img' );
      var $videoName = jQuery( this ).find( '.video-name' );
      var $videoDuration = jQuery( this ).find( '.video-duration' );

      videosPreviewsBuffer[postId] = false;

      // On mouseenter event.
      jQuery( value ).on( 'mouseenter', function( event ) {
        if ( ! $videoImg.attr( 'src' ) && ! $videoImg.hasClass( 'loaded' ) ) {
          return;
        }

        $videoDebounceBar.addClass( 'video-debounce-bar--wait' );
        // $videoName.addClass( 'video-name--hidden' );
        // $videoDuration.addClass( 'video-duration--hidden' );
        videosPreviewsBuffer[postId] = true;
        setTimeout( function() {
          if ( ! videosPreviewsBuffer[postId] ) {
            return;
          }
          jQuery.ajax({
            beforeSend: function( xhr ) {
              videosPreviewsXhrs[postId] = xhr;
            },
            method: 'POST',
            url: wpst_ajax_var.url,
            dataType: 'json',
            data: {
              action: 'wpst_load_video_preview',
              nonce: wpst_ajax_var.nonce,
              post_id: postId
            }
          })
          .done( function( response ) {
            var $canvas, canvasId;
            if ( ! ( videosPreviewsBuffer[postId] && response.success && '' !== response.data ) ) {
              return;
            }
            // Add the Model Preview in the DOM directly.
            // The Model Preview wrapper is z-indexed 50.
            $videoPreview.html( response.data ).show( function() {
              // Hide the Model Image to reveal the preview.
              // Model Image is z-indexed 100.
              if ( videosPreviewsBuffer[postId] ) {
                $videoImg.addClass( 'video-img--hidden' );
              }
            });
          }); // End of Ajax call.
        }, 250 ); // End of settimeout
      }); // End of mouseenter event.

      // On mouseleave event.
      jQuery( value ).on( 'mouseleave', function( event ) {
        videosPreviewsBuffer[postId] = false;
        $videoImg.removeClass( 'video-img--hidden' );
        // $videoName.removeClass( 'video-name--hidden' );
        // $videoDuration.removeClass( 'video-duration--hidden' );
        $videoDebounceBar.removeClass( 'video-debounce-bar--wait' );

        setTimeout( function() {
          $videoPreview.html( '' );
        }, 200 );

        // Abort current postId xhr if exists.
        if ( videosPreviewsXhrs[postId] ) {
          videosPreviewsXhrs[postId].abort();
          delete( videosPreviewsXhrs[postId] );
        }
      }); // End of mouseleave event.

    }); // End of each.
  })(); // End of Models Previews Manager IFEE.

  //Multithumbs - WPS 4.3.27: rotate real gallery/under-post images using original KolorTube data-thumbs.
  var changeThumb = null;
  var stopped = false;
  jQuery('body').on('mouseenter', '.thumbs-rotation', function(e){
      var $this = jQuery(this);
      stopped = false;
      var dataThumbs = $this.attr('data-thumbs') || $this.data('thumbs');
      if( ! dataThumbs ){
          return;
      }
      var thumbs = String(dataThumbs).split(',').filter(function(item){ return item && item.length > 4; });
      var nbThumbs = thumbs.length;
      if( nbThumbs < 1 ){
          return;
      }
      var $img = $this.find('img.video-img').first();
      if( ! $img.attr('data-default-thumb') ){
          $img.attr('data-default-thumb', $img.attr('src') || $img.attr('data-src') || '');
      }
      var i = 0;
      changeThumb = null;
      clearTimeout(changeThumb);
      changeThumb = function() {
          if( stopped === false ){
              var nextThumb = thumbs[i];
              $img.attr('src', nextThumb).attr('srcset', nextThumb).addClass('loaded');
              i = ( i + 1 ) % nbThumbs;
              setTimeout(changeThumb, 700);
          }
      };
      changeThumb();
  }).on('mouseleave', '.thumbs-rotation', function(e){
      stopped = true;
      changeThumb = null;
      var highestTimeoutId = setTimeout(';');
      for (var i = 0 ; i < highestTimeoutId ; i++) {
          clearTimeout(i);
      }
      var $blockImg = jQuery(this).find('img.video-img').first();
      var defaultThumb = $blockImg.attr('data-default-thumb') || $blockImg.attr('data-src') || $blockImg.attr('src');
      if( defaultThumb ){
          $blockImg.attr('src', defaultThumb).attr('srcset', defaultThumb).addClass('loaded');
      }
  });

  // Open search form
  jQuery( '.header-search-toggle' ).click( function() {
    // if ( jQuery( window ).width() <= 767.98 ) {
      jQuery( '.header-search-form' ).slideToggle( 200 );
    /*}
    if ( jQuery( window ).width() >= 768 ) {
      jQuery( '.header-search-form' ).animate({ width: 'toggle' }, 200 );
      jQuery( '.search-field' ).focus();
    }*/
  });
  // Move search form
  // if ( jQuery( window ).width() <= 767.98 ) {
  //   jQuery( '.header-search-form' ).appendTo( '#wrapper-navbar' );
  // }
  // if ( jQuery( window ).width() >= 768 ) {
  //   jQuery( '.header-search-form' ).prependTo( '.search-nav' );
  // }
  // jQuery( window ).resize( function() {
  //   if ( jQuery( window ).width() <= 767.98 ) {
  //     jQuery( '.header-search-form' ).appendTo( '#wrapper-navbar' );
  //   }
  //   if ( jQuery( window ).width() >= 768 ) {
  //     jQuery( '.header-search-form' ).prependTo( '.search-nav' );
  //   }
  // });
  // jQuery('.header-search-form').insertAfter('.navbar');

  // Load videojs
  if(jQuery('#wpst-video').length > 0 && !wpst_ajax_var.ctpl_installed){
    var playerOptions = {
      controlBar: {
        children: [
              'playToggle',
              'progressControl',
              'durationDisplay',
              'volumePanel',
              'qualitySelector',
              'fullscreenToggle',
        ],
      },
    };
    videojs('wpst-video', playerOptions);
  }

  // Close inplayer advertising
  jQuery('body').on('click', '.happy-inside-player .close-text', function(e) {
    jQuery(this).parent('.happy-inside-player').hide();
  });

  // Related videos beside player
  var playerHeight = jQuery('.responsive-player').height();
  jQuery('.side-related').css('height', playerHeight);
  jQuery('.slick-list').css('height', playerHeight);

  jQuery(window).resize(function() {
    var playerHeight = jQuery('.responsive-player').height();
    jQuery('.side-related').css('height', playerHeight);
    jQuery('.slick-list').css('height', playerHeight);
  });

  // Slick carousel loading
  jQuery('.side-related').slick({
    vertical: true,
    infinite: true,
    slidesToShow: 3,
    slidesToScroll: 3,
    prevArrow: '<button type="button" class="slick-prev"><i class="fa fa-chevron-up"></i></button>',
    nextArrow: '<button type="button" class="slick-next"><i class="fa fa-chevron-down"></i></button>',
  });

  // Replace all SVG images with inline SVG
  jQuery( 'img[src$=".svg"]' ).each( function() {
    var $img = jQuery( this );
    var imgURL = $img.attr( 'src' );
    var attributes = $img.prop( 'attributes' );
    var id = $img.parent( 'a' ).attr( 'id' );

    jQuery.get( imgURL, function( data ) {

      // Get the SVG tag, ignore the rest
      var $svg = jQuery( data ).find( 'svg' );

      // Remove any invalid XML tags
      $svg = $svg.removeAttr( 'xmlns:a' );

      // Loop through IMG attributes and apply on SVG
      jQuery.each( attributes, function() {
        $svg.attr( this.name, this.value );
      });

      // Replace IMG with SVG
      $img.replaceWith( $svg );

      if ( 'wps-logo-link' === id ) {
        jQuery( '#' + id ).addClass( 'show-logo' );
      }
    }, 'xml' );
  });

  /** IIFE Set Post views with ajax request for cache compatibility */
  (function(){
    var is_post = jQuery('body.single-post').length > 0;
    if( !is_post ) return;
    var post_id = jQuery('article.post').attr('id').replace('post-', '');
    jQuery.ajax({
      type: 'post',
      url: wpst_ajax_var.url,
      dataType: 'json',
      data: {
        action: 'post-views',
        nonce: wpst_ajax_var.nonce,
        post_id: post_id
      }
    })
    .done(function(doneData){
      // console.log(doneData);
    })
    .fail(function(errorData){
      console.error(errorData);
    })
    .always(function(alwaysData){
      //get post views & rating data
      jQuery.ajax({
        type: 'post',
        url: wpst_ajax_var.url,
        dataType: 'json',
        data: {
          action: 'get-post-data',
          nonce: wpst_ajax_var.nonce,
          post_id: post_id
        }
      })
      .done(function(doneData){
        if(doneData.views) {
          jQuery("#video-views span.views-number").text(doneData.views);
        }
        if(doneData.likes) {
            jQuery(".likes_count").text(doneData.likes);
        }
        if(doneData.dislikes) {
            jQuery(".dislikes_count").text(doneData.dislikes);
        }
        if(doneData.rating) {
            jQuery(".percentage").text(doneData.rating);
            jQuery(".rating-bar-meter").css('width', doneData.rating);
        }
      })
      .fail(function(errorData){
        console.error(errorData);
      })
      .always(function(){
        // always stuff
      })
    });
  })();

  /** Post like **/
  jQuery(".post-like a").on('click', function(e){
      e.preventDefault();

      var heart = jQuery(this);
      var post_id = heart.data("post_id");
      var post_like = heart.data("post_like");

      jQuery.ajax({
          type: "post",
          url: wpst_ajax_var.url,
          dataType   : "json",
          data: "action=post-like&nonce=" + wpst_ajax_var.nonce + "&post_like=" + post_like + "&post_id=" + post_id,
          success    : function(data, textStatus, jqXHR){
              if(data.alreadyrate !== true) {
                  jQuery(".rating-bar-meter").removeClass("not-rated-yet");
                  /*jQuery(".rating").text(Math.floor(data.pourcentage) + "%");
                  jQuery(".rating").show();*/

                  jQuery(".rating-result .percentage").text(Math.floor(data.percentage) + "%");
                  jQuery(".rating-result .percentage").show();

                  jQuery(".likes .likes_count").text(data.likes);
                  jQuery(".likes .dislikes_count").text(data.dislikes);

                  jQuery(".post-like").text(data.button);

                  if( data.nbrates > 0 ){
                      jQuery(".rating-bar-meter").animate({
                          width: data.progressbar + "%",
                      }, "fast", function() {
  // Animation complete.
  });
                  }
              }
          }
      });
      return false;
  });

  // ============================================================
  // ============================================================
  // SINGLE VIDEO ACTIONS — one stable handler set
  // - Native buttons: never navigate or change hash/scroll.
  // - Like: real WordPress AJAX endpoint.
  // - Save: persistent localStorage per post.
  // - Share: one toggle handler only.
  // ============================================================
  (function initSingleVideoActions(){
    var $doc = jQuery(document);

    // Prevent duplicate handlers when the script is re-evaluated by cache/customizer.
    $doc.off('.wpsSingleActions');

    function setActionLabel($btn, text) {
      $btn.find('span').first().text(text);
    }

    function actionFeedback($btn, cls) {
      $btn.addClass(cls);
      window.setTimeout(function(){ $btn.removeClass(cls); }, 900);
    }

    // KolorTube 1.9.10: resolve the AJAX endpoint only through the theme
    // WP-Script core (wpst_ajax_var), with a generic wps_ajax fallback, so
    // this handler never depends on a theme file path that may not exist.
    function wpsResolveAjaxEndpoint() {
      if (typeof wpst_ajax_var !== 'undefined' && wpst_ajax_var.url) {
        return { url: wpst_ajax_var.url, nonce: wpst_ajax_var.nonce };
      }
      if (typeof wps_ajax !== 'undefined' && wps_ajax.ajax_url) {
        var nonce = wps_ajax.nonce || (typeof wpst_ajax_var !== 'undefined' ? wpst_ajax_var.nonce : '');
        return { url: wps_ajax.ajax_url, nonce: nonce };
      }
      return null;
    }

    $doc.on('click.wpsSingleActions', '.video-buttons-row .capsule-btn-like', function(e){
      e.preventDefault();
      e.stopImmediatePropagation();

      var $btn = jQuery(this);
      if ($btn.data('busy') || $btn.hasClass('is-active')) return false;

      var postId = parseInt($btn.attr('data-post_id'), 10) || 0;
      var ajaxConfig = wpsResolveAjaxEndpoint();
      if (!postId || !ajaxConfig) {
        actionFeedback($btn, 'action-error');
        return false;
      }

      $btn.data('busy', true).addClass('is-loading').prop('disabled', true);

      jQuery.ajax({
        type: 'POST',
        url: ajaxConfig.url,
        dataType: 'json',
        data: {
          action: 'post-like',
          nonce: ajaxConfig.nonce,
          post_like: 'like',
          post_id: postId
        }
      }).done(function(data){
        if (!data || data.success === false) {
          actionFeedback($btn, 'action-error');
          return;
        }

        // Both a fresh vote and an already-recorded vote mean the button is active.
        $btn.addClass('is-active').attr('aria-pressed', 'true');
        setActionLabel($btn, 'ถูกใจแล้ว');

        // Refresh an on-button counter when the markup exposes one (.like-count).
        var $likeCounter = $btn.find('.like-count');
        if ($likeCounter.length && data.likes !== undefined) $likeCounter.text(data.likes);

        if (data.likes !== undefined) jQuery('.likes .likes_count').text(data.likes);
        if (data.dislikes !== undefined) jQuery('.likes .dislikes_count').text(data.dislikes);
        if (data.progressbar !== undefined) {
          jQuery('.rating-bar-meter').removeClass('not-rated-yet').css('width', parseInt(data.progressbar, 10) + '%');
        }
      }).fail(function(){
        actionFeedback($btn, 'action-error');
      }).always(function(){
        $btn.data('busy', false).removeClass('is-loading').prop('disabled', $btn.hasClass('is-active'));
      });

      return false;
    });

    function saveKey(postId) {
      return 'wps_saved_video_v3_' + postId + '_' + window.location.origin;
    }

    $doc.on('click.wpsSingleActions', '.video-buttons-row .capsule-btn-save', function(e){
      e.preventDefault();
      e.stopImmediatePropagation();

      var $btn = jQuery(this);
      if ($btn.data('busy')) return false;

      var postId = parseInt($btn.attr('data-post_id'), 10) || 0;
      if (!postId) return false;

      var active = $btn.hasClass('is-active');
      $btn.data('busy', true).addClass('is-pressing');

      try {
        if (active) {
          localStorage.removeItem(saveKey(postId));
          $btn.removeClass('is-active').attr('aria-pressed', 'false');
          setActionLabel($btn, 'บันทึก');
        } else {
          localStorage.setItem(saveKey(postId), '1');
          $btn.addClass('is-active').attr('aria-pressed', 'true');
          setActionLabel($btn, 'บันทึกแล้ว');
        }
      } catch (err) {
        // Keep the UI responsive even if browser storage is blocked.
        if (!active) {
          $btn.addClass('is-active').attr('aria-pressed', 'true');
          setActionLabel($btn, 'บันทึกแล้ว');
        }
      }

      window.setTimeout(function(){
        $btn.data('busy', false).removeClass('is-pressing');
      }, 150);
      return false;
    });

    function restoreSavedState() {
      $doc.find('.video-buttons-row .capsule-btn-save').each(function(){
        var $btn = jQuery(this);
        var postId = parseInt($btn.attr('data-post_id'), 10) || 0;
        if (!postId) return;
        try {
          var saved = localStorage.getItem(saveKey(postId)) === '1';
          $btn.toggleClass('is-active', saved).attr('aria-pressed', saved ? 'true' : 'false');
          setActionLabel($btn, saved ? 'บันทึกแล้ว' : 'บันทึก');
        } catch (err) {
          $btn.removeClass('is-active').attr('aria-pressed', 'false');
          setActionLabel($btn, 'บันทึก');
        }
      });
    }
    restoreSavedState();

    $doc.on('click.wpsSingleActions', '#show-sharing-buttons', function(e){
      e.preventDefault();
      e.stopImmediatePropagation();

      var $btn = jQuery(this);
      var $box = jQuery('#video-share-box');
      if (!$box.length || $btn.data('busy')) return false;

      var expanded = $btn.attr('aria-expanded') === 'true';
      $btn.data('busy', true).addClass('is-pressing');

      if (expanded) {
        $box.stop(true, true).slideUp(160, function(){
          $btn.removeClass('active').attr('aria-expanded', 'false').data('busy', false).removeClass('is-pressing');
        });
      } else {
        $box.stop(true, true).slideDown(180, function(){
          $btn.addClass('active').attr('aria-expanded', 'true').data('busy', false).removeClass('is-pressing');
        });
      }
      return false;
    });

    $doc.on('click.wpsSingleActions', '#clickme', function(e){
      e.preventDefault();
      e.stopImmediatePropagation();

      var $copyBtn = jQuery(this);
      var text = jQuery('#copyme').val() || '';
      if (!text) return false;

      function copied(){
        $copyBtn.addClass('copied').html('<i class="fa fa-check" aria-hidden="true"></i> คัดลอกแล้ว');
        window.setTimeout(function(){
          $copyBtn.removeClass('copied').html('<i class="fa fa-copy" aria-hidden="true"></i> คัดลอกลิงก์');
        }, 1500);
      }
      function fallbackCopy(){
        var temp = document.createElement('textarea');
        temp.value = text;
        temp.setAttribute('readonly', 'readonly');
        temp.style.position = 'fixed';
        temp.style.left = '-9999px';
        document.body.appendChild(temp);
        temp.select();
        try { document.execCommand('copy'); } catch (err) {}
        document.body.removeChild(temp);
        copied();
      }

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(copied).catch(fallbackCopy);
      } else {
        fallbackCopy();
      }
      return false;
    });
  })();

});

// Menu mobile
var forEach = function( t, o, r ) {
  if ( '[object Object]' === Object.prototype.toString.call( t ) ) {
    for ( var c in t ) {
      Object.prototype.hasOwnProperty.call( t, c ) && o.call( r, t[c], c, t );
    }
  } else {
    for ( var e = 0, l = t.length; l > e; e++ ) {
      o.call( r, t[e], e, t );
    }
  }
};
var hamburgers = document.querySelectorAll( '.hamburger' );
if ( hamburgers.length > 0 ) {
  forEach( hamburgers, function( hamburger ) {
    hamburger.addEventListener( 'click', function() {
      this.classList.toggle( 'is-active' );
    }, false );
  });
}

document.addEventListener('DOMContentLoaded', function() {
  
  // ค้นหาปุ่มทั้งหมดที่มี Attribute 'data-close'
  document.querySelectorAll('[data-close]').forEach(button => {
    button.addEventListener('click', () => {
      
      // หา Element เป้าหมายจากชื่อ ID ที่ระบุใน data-close
      const target = document.getElementById(button.dataset.close);
      
      if(target) {
        target.style.display = 'none';
      }
      
    });
  });

});