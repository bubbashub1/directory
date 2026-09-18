(function($){'use strict';
function removeGroupReadingTime(){
  if(!document.body.classList.contains('bubbahub-group-page'))return;
  var candidates=document.querySelectorAll('body *');
  candidates.forEach(function(el){
    if(el.closest('.bhg-single'))return;
    var text=(el.textContent||'').trim();
    if(/^\\d+\\s*[–-]\\s*\\d+\\s+minutes?$/i.test(text)&&el.children.length===0){el.remove();}
  });
}
function init(){var $root=$('.bhg-single');if(!$root.length)return;
 function key(action){return 'bubbahub_'+action;}
 function read(action){try{return JSON.parse(localStorage.getItem(key(action))||'[]');}catch(e){return[];}}
 function write(action,list){try{localStorage.setItem(key(action),JSON.stringify(list));}catch(e){}}
 function sync(){var id=String($root.data('post-id'));['favourite','compare','visited'].forEach(function(a){var active=read(a).indexOf(id)!==-1;$root.find('[data-badge-action="'+a+'"]').toggleClass('is-active',active).attr('aria-pressed',active?'true':'false');});}
 $root.on('click','[data-badge-action]',function(){var $b=$(this),a=$b.data('badge-action'),id=String($root.data('post-id')),list=read(a),i=list.indexOf(id);if(i===-1)list.push(id);else list.splice(i,1);write(a,list);sync();});
 $root.on('click','[data-share]',function(){var data={title:document.title,url:window.location.href};if(navigator.share){navigator.share(data).catch(function(){});}else if(navigator.clipboard){navigator.clipboard.writeText(window.location.href).then(function(){$root.find('[data-share]').addClass('is-copied').text('✓ Link copied');setTimeout(function(){$root.find('[data-share]').removeClass('is-copied').html('<span aria-hidden="true">↗</span> Share now');},1800);});}});
 function initGallery(){
  var $g=$root.find('[data-gallery]'); if(!$g.length)return;
  var $slides=$g.find('.bhg-gallery-slide'), $dots=$g.find('[data-gallery-dot]'), index=0, timer;
  if($slides.length<2)return;
  function show(i){index=(i+$slides.length)%$slides.length;$slides.removeClass('is-active').eq(index).addClass('is-active');$dots.removeClass('is-active').eq(index).addClass('is-active');}
  function restart(){clearInterval(timer);timer=setInterval(function(){show(index+1);},5000);}
  $root.on('click','[data-gallery-prev]',function(){show(index-1);restart();});
  $root.on('click','[data-gallery-next]',function(){show(index+1);restart();});
  $root.on('click','[data-gallery-dot]',function(){show(parseInt($(this).data('gallery-dot'),10)||0);restart();});
  restart();
 }
 function initContactModal(){
  var $m=$root.find('#bh-contact-modal'); if(!$m.length)return;
  $root.on('click','[data-contact-open]',function(e){e.preventDefault();var groupId=$root.data('post-id');if(groupId){document.cookie='bubbahub_contact_group_id='+encodeURIComponent(String(groupId))+'; path=/; SameSite=Lax';}$m.removeAttr('hidden').attr('aria-hidden','false').addClass('is-open');$('body').addClass('bh-modal-open');});
  $root.on('click','[data-contact-close]',function(){$m.attr('hidden',true).attr('aria-hidden','true').removeClass('is-open');$('body').removeClass('bh-modal-open');});
  $(document).on('keydown.bhContact',function(e){if(e.key==='Escape'&&$m.hasClass('is-open')){$m.attr('hidden',true).attr('aria-hidden','true').removeClass('is-open');$('body').removeClass('bh-modal-open');}});
 }
 function buildMap(){var $map=$root.find('#bhg-map');if(!$map.length||!window.L)return;var lat=parseFloat($map.data('lat')),lng=parseFloat($map.data('lng'));if(!isFinite(lat)||!isFinite(lng))return;var map=L.map($map[0],{scrollWheelZoom:false}).setView([lat,lng],15);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);L.marker([lat,lng]).addTo(map);setTimeout(function(){map.invalidateSize();},100);}
 $root.on('change','.bhg-venue-select',function(){var venue=$(this).val(),$track=$root.find('[data-related-track]');$track.addClass('is-loading');$.ajax({url:BubbaHubGroupPage.ajaxUrl,type:'POST',dataType:'json',data:{action:'bubbahub_group_alternatives',nonce:BubbaHubGroupPage.nonce,post_id:$root.data('post-id'),venue_id:venue}}).done(function(r){if(r&&r.success)$track.html(r.data.html);}).always(function(){$track.removeClass('is-loading');});});
 $root.on('click','[data-carousel-prev]',function(){var t=$root.find('[data-related-track]')[0];if(t)t.scrollBy({left:-320,behavior:'smooth'});});
 $root.on('click','[data-carousel-next]',function(){var t=$root.find('[data-related-track]')[0];if(t)t.scrollBy({left:320,behavior:'smooth'});});
 sync();initGallery();initContactModal();buildMap();removeGroupReadingTime();
}
$(init);
})(jQuery);
