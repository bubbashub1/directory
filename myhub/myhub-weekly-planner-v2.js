(function($){
'use strict';
function calendarAjax($section,form){
 var $form=$(form);if(!$section.length||!$form.length)return;
 var advancedOpen=$section.attr('data-advanced-open')==='1';
 var requestId=(parseInt($section.attr('data-calendar-request-id'),10)||0)+1;
 $section.attr('data-calendar-request-id',requestId);
 var previousRequest=$section.data('calendarRequest');
 if(previousRequest&&previousRequest.readyState!==4)previousRequest.abort();
 var data=$form.serializeArray();data.push({name:'action',value:'bubbahub_calendar_filter'},{name:'nonce',value:$section.attr('data-calendar-nonce')});
 var editCalendarId=$section.attr('data-edit-calendar')||'';
 if(editCalendarId)data.push({name:'bh_edit_calendar',value:editCalendarId});
 $section.data('calendarLoading',true).addClass('is-loading');
 var request=$.ajax({url:$section.attr('data-calendar-ajax'),type:'POST',data:data,dataType:'json'});
 $section.data('calendarRequest',request);
 request.done(function(response){
  if(requestId!==parseInt($section.attr('data-calendar-request-id'),10))return;
  if(response&&response.success&&response.data&&response.data.html){
  var $replacement=$(response.data.html);
  $replacement.attr('data-advanced-open',advancedOpen?'1':'0');
  $section.replaceWith($replacement);
  if(advancedOpen) setAdvancedSearch($replacement,true);
}
 }).fail(function(xhr,status){
  if(status==='abort'||requestId!==parseInt($section.attr('data-calendar-request-id'),10))return;
  $section.find('.bh-calendar-ajax-error').remove();
  $section.find('.bh-calendar-search-actions').append('<span class="bh-calendar-ajax-error" role="alert">Sorry, the calendar could not be updated. Please try again.</span>');
 }).always(function(){
  if(requestId!==parseInt($section.attr('data-calendar-request-id'),10))return;
  $section.data('calendarLoading',false).removeClass('is-loading');
  $section.removeData('calendarRequest');
 });
}
function setAdvancedSearch($section,open){
 var $panel=$section.find('.bh-calendar-advanced-search').first();
 var $toggle=$section.find('.bh-calendar-advanced-toggle').first();
 if(!$panel.length||!$toggle.length)return;
 $panel.prop('hidden',!open).attr('aria-hidden',open?'false':'true');
 $toggle.attr('aria-expanded',open?'true':'false');
 $section.attr('data-advanced-open',open?'1':'0');
}
$(document).on('click','.bh-weekly-planner-v2 .bh-calendar-advanced-toggle',function(e){
 e.preventDefault();
 var $section=$(this).closest('.bh-weekly-planner-v2');
 var isOpen=$section.attr('data-advanced-open')==='1';
 setAdvancedSearch($section,!isOpen);
});
$(document).on('click','.bh-weekly-planner-v2 [data-save-calendar]',function(){
 var $button=$(this),$section=$button.closest('.bh-weekly-planner-v2'),$panel=$section.find('[data-save-panel]').first();
 if(!$panel.length)return;
 $panel.prop('hidden',false).removeAttr('aria-hidden');
 $panel.find('input').first().trigger('focus');
});
$(document).on('click','.bh-weekly-planner-v2 [data-cancel-save]',function(){
 var $panel=$(this).closest('[data-save-panel]');
 $panel.prop('hidden',true);
});
$(document).on('click','.bh-weekly-planner-v2 [data-confirm-save]',function(){
 var $button=$(this),$section=$button.closest('.bh-weekly-planner-v2'),$form=$section.find('.bh-calendar-search-form').first(),$panel=$button.closest('[data-save-panel]'),$name=$panel.find('input').first();
 var name=$.trim($name.val()||''),nonce=$section.attr('data-save-nonce');
 if(!name){$name.trigger('focus');return;}
 if(!nonce||!$form.length||$button.prop('disabled'))return;
 var data=$form.serializeArray();
 data.push({name:'action',value:'bubbahub_save_custom_calendar'},{name:'nonce',value:nonce},{name:'calendar_name',value:name});
 var editId=$section.attr('data-edit-calendar')||'';
 if(editId)data.push({name:'calendar_id',value:editId});
 $button.prop('disabled',true).text(editId?'Updating…':'Saving…');
 $.ajax({url:$section.attr('data-calendar-ajax'),type:'POST',data:data,dataType:'json'}).done(function(response){
  if(response&&response.success){
   if(editId){
    window.location.href=window.location.href.replace(/([?&])bh_edit_calendar=[^&]*/,'$1').replace(/[?&]$/,'');
   }else{
    window.location.reload();
   }
  }else{
   $button.prop('disabled',false).text(editId?'Update':'Save');
   window.alert(response&&response.data&&response.data.message?response.data.message:'The calendar could not be saved.');
  }
 }).fail(function(){
  $button.prop('disabled',false).text(editId?'Update':'Save');
  window.alert('The calendar could not be saved. Please try again.');
 });
});
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