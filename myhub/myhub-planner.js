(function($){'use strict';
var cfg=window.BubbaHubMyHubGroups||window.BubbaHubMyHubPlanner||{};
function refresh($planner){
 if(!cfg.ajaxUrl||!cfg.nonce)return;
 var children=$planner.find('[data-planner-child]:checked').map(function(){return $(this).val();}).get();
 var interest=$planner.find('[data-planner-interest]').val()||'';
 var location=$planner.find('[data-planner-location]').val()||'';
 var showNaps=$planner.find('[data-planner-naps]').prop('checked')?1:0;
 var $results=$planner.find('[data-planner-results]').addClass('bh-planner-refreshing');
 $.post(cfg.ajaxUrl,{action:'bubbahub_myhub_planner',nonce:cfg.nonce,children:JSON.stringify(children),interest:interest,location:location,show_naps:showNaps},function(r){$results.html(r&&r.success?r.data.html:'<div class="bh-planner-empty">We could not update your planner. Please try again.</div>');},'json').fail(function(){$results.html('<div class="bh-planner-empty">We could not update your planner. Please try again.</div>');}).always(function(){$results.removeClass('bh-planner-refreshing');});
}
function init(){
 var $hub=$('.bh-myhub-v2,.bh-myhub-v3').first();
 if(!$hub.length||$hub.find('[data-bh-weekly-planner]').length)return;
 var $family=$hub.find('.bh-myhub-family-grid').first().closest('.bh-myhub-section');
 if(!$family.length)return;
 var $section=$('<section class="bh-myhub-section bh-weekly-planner" data-bh-weekly-planner><div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">YOUR WEEK</div><h2>Weekly Planner</h2><p>A simple week view built around your children, interests and preferred locations.</p></div></div><div class="bh-planner-controls"><div><strong>Children</strong><div class="bh-planner-pills"></div></div><div><strong>Interest</strong><input type="text" data-planner-interest placeholder="Use your saved interests or add a filter"></div><div><strong>Preferred location</strong><input type="text" data-planner-location placeholder="Use your saved preferred locations or add a filter"></div><label class="bh-planner-nap-toggle"><input type="checkbox" data-planner-naps><span>Show groups during nap times</span></label><button type="button" class="bh-myhub-button" data-planner-refresh>Update planner</button></div><div class="bh-planner-nap-note" data-planner-nap-note hidden>⚠️ These activities overlap a child&#39;s saved nap time. Check the individual listing before booking.</div><div class="bh-planner-results" data-planner-results><div class="bh-planner-empty">Loading your personalised week…</div></div></section>');
 var inserted=false;
 $hub.find('.bh-myhub-section').each(function(){if($(this).find('h2').first().text().trim()==='My Child Profiles'){$section.insertAfter(this);inserted=true;return false;}});
 if(!inserted)return;
 var $pills=$section.find('.bh-planner-pills');
 $hub.find('.bh-myhub-selected-child').each(function(){var id=$(this).data('child-id');var name=$(this).closest('.bh-myhub-child-card').find('h3').first().text()||'Child';var safeName=$('<div>').text(name).html();var safeId=String(id).replace(/"/g,'&quot;');$pills.append('<label><input type="checkbox" data-planner-child value="'+safeId+'" checked><span>'+safeName+'</span></label>');});
 $section.on('click','[data-planner-refresh]',function(){refresh($section);});
 $section.on('change','[data-planner-child], [data-planner-naps]',function(){refresh($section);});
 refresh($section);
}
$(init);
})(jQuery);
