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
})(jQuery);