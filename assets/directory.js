(function($){'use strict';
function initDirectory($root){
 var $grid=$root.find('.bh-grid'),$map=$root.find('.bh-map'),map=null,markers=[];
 $root.on('click','.bh-advanced-toggle',function(){var open=$(this).attr('aria-expanded')==='true';$(this).attr('aria-expanded',String(!open));$root.find('.bh-advanced').prop('hidden',open);});
 $root.on('click','.bh-cols',function(){var n=$(this).data('columns');$root.find('.bh-cols').removeClass('is-active');$(this).addClass('is-active');$grid.attr('data-columns',n);if(map)setTimeout(function(){map.invalidateSize();},100);});
 function buildMap(){
  if(map)return;
  map=L.map($map[0],{scrollWheelZoom:false});
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap contributors'}).addTo(map);
  var bounds=[];
  $grid.find('.bh-card').each(function(){var $card=$(this),lat=parseFloat($card.attr('data-lat')),lng=parseFloat($card.attr('data-lng'));if(!isFinite(lat)||!isFinite(lng))return;var marker=L.marker([lat,lng]).addTo(map);var title=$card.attr('data-title')||'';var url=$card.attr('data-url')||'#';marker.bindPopup('<strong>'+escapeHtml(title)+'</strong><br><a href="'+escapeAttr(url)+'">View More</a>');marker.on('click',function(){$card[0].scrollIntoView({behavior:'smooth',block:'center'});});markers.push(marker);bounds.push([lat,lng]);});
  if(bounds.length)map.fitBounds(bounds,{padding:[30,30],maxZoom:14});else map.setView([50.45,-3.58],10);
 }
 function escapeHtml(s){return String(s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});}
 function escapeAttr(s){return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
 $root.on('click','.bh-map-toggle',function(){var show=!$map.is(':visible');if(show){$grid.hide();$map.prop('hidden',false).show();$(this).addClass('is-active').text('List');buildMap();setTimeout(function(){map.invalidateSize();},150);}else{$map.hide().prop('hidden',true);$grid.show();$(this).removeClass('is-active').text('Map');}});
}
$(function(){$('.bh-directory').each(function(){initDirectory($(this));});});
})(jQuery);
