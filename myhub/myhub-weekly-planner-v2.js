(function($){
'use strict';
function calendarAjax($section,form){
 var $form=$(form);if(!$section.length||!$form.length||$section.data('calendarLoading'))return;
 var data=$form.serializeArray();data.push({name:'action',value:'bubbahub_calendar_filter'},{name:'nonce',value:$section.attr('data-calendar-nonce')});
 $section.data('calendarLoading',true).addClass('is-loading');
 $.ajax({url:$section.attr('data-calendar-ajax'),type:'POST',data:data,dataType:'json'}).done(function(response){
  if(response&&response.success&&response.data&&response.data.html){$section.replaceWith($(response.data.html));}
 }).fail(function(){$section.find('.bh-calendar-search-actions').append('<span class="bh-calendar-ajax-error" role="alert">Sorry, the calendar could not be updated. Please try again.</span>');}).always(function(){$section.data('calendarLoading',false).removeClass('is-loading');});
}
$(document).on('submit','.bh-weekly-planner-v2 .bh-calendar-search-form',function(e){e.preventDefault();calendarAjax($(this).closest('.bh-weekly-planner-v2'),this);});
$(document).on('change','.bh-weekly-planner-v2 .bh-calendar-advanced-search select',function(){calendarAjax($(this).closest('.bh-weekly-planner-v2'),$(this).closest('.bh-calendar-search-form'));});
$(document).on('click','.bh-weekly-planner-v2 .bh-calendar-use-location',function(){
 var $button=$(this),$section=$button.closest('.bh-weekly-planner-v2'),$form=$button.closest('.bh-calendar-search-form');
 if(!navigator.geolocation)return;
 $button.prop('disabled',true).text('…');
 navigator.geolocation.getCurrentPosition(function(pos){
  $form.find('[name="bh_lat"]').val(pos.coords.latitude);$form.find('[name="bh_lng"]').val(pos.coords.longitude);
  $button.prop('disabled',false).text('✓');calendarAjax($section,$form);
 },function(){$button.prop('disabled',false).text('⌖');},{enableHighAccuracy:false,timeout:10000,maximumAge:300000});
});
$(document).on('click','.bh-weekly-planner-v2 [data-scroll-saved-calendars]',function(){
 var $button=$(this),$track=$button.closest('.bh-saved-calendars').find('[data-saved-calendars-track]').first();
 if(!$track.length)return;
 var direction=parseInt($button.attr('data-scroll-saved-calendars'),10)||1;
 var card=$track.find('.bh-saved-calendar-card').first();
 var distance=card.length ? card.outerWidth(true) : Math.max(260,$track.innerWidth()*0.85);
 $track[0].scrollBy({left:direction*distance,behavior:'smooth'});
});
$(document).on('click','.bh-weekly-planner-v2 [data-delete-saved-calendar]',function(){
 var $button=$(this),$section=$button.closest('.bh-weekly-planner-v2'),id=$button.attr('data-calendar-id'),nonce=$section.attr('data-save-nonce');
 if(!id||!nonce||$button.prop('disabled'))return;
 if(!window.confirm('Remove this custom calendar from My Hub?'))return;
 $button.prop('disabled',true).text('Removing…');
 $.ajax({
  url:$section.attr('data-calendar-ajax'),
  type:'POST',
  dataType:'json',
  data:{action:'bubbahub_delete_custom_calendar',calendar_id:id,nonce:nonce}
 }).done(function(response){
  if(response&&response.success){
   var $card=$button.closest('.bh-saved-calendar-card');
   var wasActive=$card.hasClass('is-active');
   $card.slideUp(180,function(){
    $card.remove();
    var $track=$section.find('[data-saved-calendars-track]').first();
    if(!$track.find('.bh-saved-calendar-card').length)$section.find('.bh-saved-calendars').remove();
    if(wasActive)window.location.href=window.location.href.replace(/([?&])bh_saved_calendar=[^&]*/,'$1').replace(/[?&]$/,'');
   });
  }else{
   $button.prop('disabled',false).text('Remove');
   window.alert(response&&response.data&&response.data.message?response.data.message:'The calendar could not be removed.');
  }
 }).fail(function(){
  $button.prop('disabled',false).text('Remove');
  window.alert('The calendar could not be removed. Please try again.');
 });
});
})(jQuery);