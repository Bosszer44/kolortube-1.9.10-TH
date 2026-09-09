(function(){
  'use strict';
  function ready(fn){if(document.readyState!=='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);}}
  ready(function(){
    document.querySelectorAll('[data-wps-floating-page-id]').forEach(function(layer){
      var pageId=layer.getAttribute('data-wps-floating-page-id');
      if(!pageId||!document.body.classList.contains('postid-'+pageId)){layer.remove();}
    });
    if(!window.__wpsPlayerAdCloseBound){
      window.__wpsPlayerAdCloseBound=true;
      document.addEventListener('click',function(e){
        var btn=e.target.closest('.happy-inside-player .close-text');
        if(!btn){return;}
        var box=btn.closest('.happy-inside-player');
        if(box){box.style.display='none';}
      });
    }

    var header=document.getElementById('wrapper-navbar');
    var mobileMenu=document.getElementById('navbarNavDropdown');
    if(header&&mobileMenu){
      var mobileQuery=window.matchMedia('(max-width: 1399.98px)');
      var applyMobileMenuState=function(isOpen){
        isOpen=mobileQuery.matches&&isOpen;
        document.body.classList.toggle('wps-mobile-menu-open',isOpen);
        document.documentElement.style.setProperty('--wps-mobile-header-bottom',Math.max(0,header.getBoundingClientRect().bottom)+'px');
      };
      var syncMobileMenu=function(){applyMobileMenuState(mobileMenu.classList.contains('show'));};
      new MutationObserver(syncMobileMenu).observe(mobileMenu,{attributes:true,attributeFilter:['class']});
      window.addEventListener('resize',syncMobileMenu,{passive:true});
      window.addEventListener('orientationchange',syncMobileMenu,{passive:true});
      if(window.jQuery){
        window.jQuery(mobileMenu)
          .on('show.bs.collapse shown.bs.collapse hide.bs.collapse',function(){applyMobileMenuState(true);})
          .on('hidden.bs.collapse',syncMobileMenu);
      }
      syncMobileMenu();
    }
    if(window.WPSProfessional&&WPSProfessional.videoProtection){
      document.querySelectorAll('video').forEach(function(video){
        video.setAttribute('controlsList','nodownload noremoteplayback');
        video.setAttribute('disablePictureInPicture','');
        video.classList.add('wps-video-protected');
        video.addEventListener('contextmenu',function(e){e.preventDefault();});
        video.addEventListener('dragstart',function(e){e.preventDefault();});
      });
    }
    // The Customizer preview loads this asset without the localized settings object.
    // Keep the one-row home sliders interactive there, while leaving other features disabled.
    if(window.WPSProfessional&&!WPSProfessional.enabled){return;}
    document.querySelectorAll('.wps-home-section.is-slider[data-wps-home-slider]').forEach(function(slider){
      if(slider.dataset.wpsSliderBound){return;}
      slider.dataset.wpsSliderBound='1';
      var grid=slider.querySelector('.wps-home-card-grid');
      var previousButton=slider.querySelector('[data-wps-slider-prev]');
      var nextButton=slider.querySelector('[data-wps-slider-next]');
      var hasControls=!!(previousButton||nextButton);
      var interval=Math.max(1000,Number(slider.getAttribute('data-interval'))||3000);
      var timer=null;
      var shouldAutoplay=slider.getAttribute('data-autoplay')==='1'&&!window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      function cardStep(){
        var first=grid&&grid.querySelector('.wps-home-card');
        var gap=grid?parseFloat(getComputedStyle(grid).gap)||0:0;
        return first ? first.getBoundingClientRect().width+gap : (grid?grid.clientWidth:0);
      }
      function updateControls(){
        if(!grid||!hasControls){return;}
        var max=Math.max(0,grid.scrollWidth-grid.clientWidth);
        if(previousButton){previousButton.disabled=grid.scrollLeft<=2;}
        if(nextButton){nextButton.disabled=grid.scrollLeft>=max-2;}
      }
      function step(direction){
        if(!grid||grid.scrollWidth<=grid.clientWidth){return;}
        var amount=cardStep();
        var next=grid.scrollLeft+amount*(direction||1);
        var max=Math.max(0,grid.scrollWidth-grid.clientWidth);
        if(hasControls){next=Math.max(0,Math.min(max,next));}
        else {
          if(next>=max-2){next=0;}
          if(next<=0&&direction<0){next=max;}
        }
        grid.scrollTo({left:next,behavior:'smooth'});
        window.setTimeout(updateControls,360);
      }
      function start(){if(shouldAutoplay&&!timer){timer=window.setInterval(step,interval);}}
      function stop(){if(timer){window.clearInterval(timer);timer=null;}}
      var pointerId=null,startX=0,startScroll=0,lastDistance=0,dragged=false,suppressClick=false;
      function endDrag(event){
        if(pointerId===null||event.pointerId!==pointerId){return;}
        if(grid.hasPointerCapture&&grid.hasPointerCapture(pointerId)){grid.releasePointerCapture(pointerId);}
        pointerId=null;
        grid.classList.remove('is-dragging');
        if(dragged){
          var amount=cardStep();
          var max=Math.max(0,grid.scrollWidth-grid.clientWidth);
          var next=Math.max(0,Math.min(max,startScroll+(lastDistance<0?amount:-amount)));
          grid.scrollTo({left:next,behavior:'smooth'});
          suppressClick=true;window.setTimeout(function(){suppressClick=false;},0);
          window.setTimeout(updateControls,360);
        }
        start();
      }
      grid.addEventListener('pointerdown',function(event){
        if(event.button!==0){return;}
        pointerId=event.pointerId;startX=event.clientX;startScroll=grid.scrollLeft;lastDistance=0;dragged=false;stop();
      });
      grid.addEventListener('pointermove',function(event){
        if(pointerId===null||event.pointerId!==pointerId){return;}
        var distance=event.clientX-startX;lastDistance=distance;
        if(Math.abs(distance)>4){dragged=true;grid.classList.add('is-dragging');grid.scrollLeft=startScroll-distance;event.preventDefault();}
      });
      grid.addEventListener('pointerup',endDrag);
      grid.addEventListener('pointercancel',endDrag);
      grid.addEventListener('click',function(event){
        if(!suppressClick){return;}
        event.preventDefault();event.stopPropagation();suppressClick=false;
      },true);
      if(previousButton){previousButton.addEventListener('click',function(){stop();step(-1);});}
      if(nextButton){nextButton.addEventListener('click',function(){stop();step(1);});}
      var controlTimer=null;
      grid.addEventListener('scroll',function(){
        window.clearTimeout(controlTimer);
        controlTimer=window.setTimeout(updateControls,80);
      },{passive:true});
      if(!slider.hasAttribute('tabindex')){slider.setAttribute('tabindex','0');}
      slider.addEventListener('keydown',function(event){
        if(event.key==='ArrowLeft'||event.key==='ArrowRight'){
          event.preventDefault();stop();step(event.key==='ArrowLeft'?-1:1);
        }
      });
      slider.addEventListener('pointerenter',stop);
      slider.addEventListener('pointerleave',start);
      slider.addEventListener('focusin',stop);
      slider.addEventListener('focusout',start);
      updateControls();
      start();
    });
    if(!window.WPSProfessional){return;}
    document.querySelectorAll('[data-wps-search]').forEach(function(form){
      var input=form.querySelector('input[type="search"]');
      var box=form.querySelector('[data-wps-suggestions]');
      if(!input||!box){return;}
      var timer=null,controller=null;
      function close(){box.classList.remove('is-open');box.innerHTML='';}
      function state(text){box.innerHTML='<div class="wps-search-state"></div>';box.firstChild.textContent=text;box.classList.add('is-open');}
      input.addEventListener('input',function(){
        clearTimeout(timer);var q=input.value.trim();if(q.length<Number(WPSProfessional.minChars||2)){close();return;}
        timer=setTimeout(function(){
          if(controller){controller.abort();}controller=new AbortController();state(WPSProfessional.loading);
          fetch(WPSProfessional.endpoint+'?q='+encodeURIComponent(q),{credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}})
            .then(function(r){if(!r.ok){throw new Error('HTTP '+r.status);}return r.json();})
            .then(function(data){var items=data.items||[];if(!items.length){state(WPSProfessional.empty);return;}box.innerHTML='';items.forEach(function(item){var a=document.createElement('a');a.className='wps-search-item';a.href=item.url;var strong=document.createElement('strong');strong.textContent=item.title;var tag=document.createElement('span');tag.textContent=item.label;a.appendChild(strong);a.appendChild(tag);box.appendChild(a);});box.classList.add('is-open');})
            .catch(function(err){if(err.name!=='AbortError'){state(WPSProfessional.error);}});
        },220);
      });
      document.addEventListener('click',function(e){if(!form.contains(e.target)){close();}});
      input.addEventListener('keydown',function(e){if(e.key==='Escape'){close();}});
    });
  });
})();

/* WPS 4.3.62 duplicate-card safety net: one post ID per visual block. */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.single-post .wp-block-gallery .wp-block-image img, .single-post .gallery img').forEach(function (image) {
    if (image.closest('a') || image.dataset.wpsGalleryFullLinked) return;
    var link = document.createElement('a');
    link.href = image.dataset.fullUrl || image.currentSrc || image.src;
    link.target = '_blank';
    link.rel = 'noopener';
    link.className = 'gallery-full-link';
    link.setAttribute('aria-label', 'Open full-size image');
    image.parentNode.insertBefore(link, image);
    link.appendChild(image);
    image.dataset.wpsGalleryFullLinked = '1';
  });

  document.querySelectorAll('.wps-home-card-grid, .row.video-list').forEach(function (grid) {
    var seen = Object.create(null);
    grid.querySelectorAll('[data-post-id], article[id^="post-"]').forEach(function (card) {
      var id = card.getAttribute('data-post-id') || (card.id || '').replace(/^post-/, '');
      if (!id) return;
      if (seen[id]) { var target = card.closest('[class*=\"col-\"]') || card; target.setAttribute('hidden', 'hidden'); target.setAttribute('data-wps-duplicate-hidden', '1'); }
      else seen[id] = true;
    });
  });
});
