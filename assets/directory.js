(function($){'use strict';
function initDirectory($root){
 var $grid=$root.find('.bh-grid'),$map=$root.find('.bh-map'),$form=$root.find('.bh-searchbar'),$loading=$root.find('.bh-loading'),map=null,markers=[];

 function escapeHtml(s){return String(s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
 function escapeAttr(s){return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
 function destroyMap(){
  if(!map)return;
  markers.forEach(function(marker){marker.remove();});
  markers=[];
  map.remove();
  map=null;
 }
 function buildMap(){
  if(map)return;
  map=L.map($map[0],{scrollWheelZoom:false});
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
  var bounds=[];
  $grid.find('.bh-card').each(function(){
   var $card=$(this),lat=parseFloat($card.attr('data-lat')),lng=parseFloat($card.attr('data-lng'));
   if(!isFinite(lat)||!isFinite(lng))return;
   var marker=L.marker([lat,lng]).addTo(map),title=$card.attr('data-title')||'',url=$card.attr('data-url')||'#';
   marker.bindPopup('<strong>'+escapeHtml(title)+'</strong><br><a href="'+escapeAttr(url)+'">View More</a>');
   marker.on('click',function(){$card[0].scrollIntoView({behavior:'smooth',block:'center'});});
   markers.push(marker);bounds.push([lat,lng]);
  });
  if(bounds.length)map.fitBounds(bounds,{padding:[30,30],maxZoom:14});else map.setView([50.45,-3.58],10);
 }
 function showLoading(on){$loading.prop('hidden',!on);$grid.toggleClass('is-loading',on);}
 function refreshMap(){
  if(!map)return;
  destroyMap();
  buildMap();
  setTimeout(function(){if(map)map.invalidateSize();},100);
 }
 function loadResults(page){
  if(typeof BubbaHubDirectory==='undefined')return;
  var data={action:'bubbahub_directory_filter',nonce:BubbaHubDirectory.nonce,search:$form.find('[name="bh_search"]').val()||'',region:$form.find('[name="bh_region"]').val()||'',age:$form.find('[name="bh_age"]').val()||'',price:$form.find('[name="bh_price"]').val()||'',paged:page||1,postsPerPage:BubbaHubDirectory.postsPerPage||12};
  showLoading(true);
  $.ajax({url:BubbaHubDirectory.ajaxUrl,type:'POST',data:data,dataType:'json'}).done(function(response){
   if(!response||!response.success)return;
   $grid.html(response.data.html);
   $root.find('.bh-pagination').html(response.data.pagination||'');
   $root.find('.bh-results-count').html('<strong>'+Number(response.data.count).toLocaleString()+'</strong> groups');
   if(map)refreshMap();
   window.history.replaceState({},'',buildUrl(data));
  }).fail(function(){
   $root.find('.bh-results-count').after('<div class="bh-inline-error" role="alert">Sorry, the groups could not be updated. Please try again.</div>');
  }).always(function(){showLoading(false);});
 }
 function buildUrl(data){
  var url=new URL(window.location.href);
  [['bh_search',data.search],['bh_region',data.region],['bh_age',data.age],['bh_price',data.price]].forEach(function(pair){if(pair[1])url.searchParams.set(pair[0],pair[1]);else url.searchParams.delete(pair[0]);});
  if(data.paged>1)url.searchParams.set('paged',data.paged);else url.searchParams.delete('paged');
  return url.toString();
 }
 $root.on('click','.bh-advanced-toggle',function(){var open=$(this).attr('aria-expanded')==='true';$(this).attr('aria-expanded',String(!open));$root.find('.bh-advanced').prop('hidden',open);});
 $form.on('submit',function(e){e.preventDefault();loadResults(1);});
 $root.on('click','.bh-pagination a',function(e){e.preventDefault();var href=$(this).attr('href')||'',match=href.match(/[?&]paged=(\d+)/),page=match?parseInt(match[1],10):1;loadResults(page);});
 $root.on('click','.bh-reset-inline',function(){
  $form.find('input[type="search"]').val('');$form.find('select').val('');loadResults(1);
 });
 $root.on('click','.bh-cols',function(){var n=$(this).data('columns');$root.find('.bh-cols').removeClass('is-active');$(this).addClass('is-active');$grid.attr('data-columns',n);if(map)setTimeout(function(){map.invalidateSize();},100);});
 $root.on('click','.bh-map-toggle',function(){
  var show=$map.prop('hidden');
  if(show){$grid.hide();$map.prop('hidden',false).show();$(this).addClass('is-active').text('List');buildMap();setTimeout(function(){if(map)map.invalidateSize();},150);}
  else{$map.hide().prop('hidden',true);$grid.show();$(this).removeClass('is-active').text('Map');}
 });
}
$(function(){$('.bh-directory').each(function(){initDirectory($(this));});});
})(jQuery);
