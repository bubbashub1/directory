(function($){'use strict';
function list(action){try{return JSON.parse(localStorage.getItem('bubbahub_'+action)||'[]').map(String);}catch(e){return [];}}
function request($target,type,ids,limit){
    var data={action:'bubbahub_myhub_groups',nonce:BubbaHubMyHubGroups.nonce,type:type,ids:JSON.stringify(ids),favourites:JSON.stringify(list('favourite')),visited:JSON.stringify(list('visited'))};
    $.post(BubbaHubMyHubGroups.ajaxUrl,data,function(response){
        if(!response||!response.success){$target.html('<div class="bh-myhub-groups-empty"><strong>We could not load these groups.</strong><span>Please refresh and try again.</span></div>');return;}
        var html=response.data.html||'';
        if(!html){
            var message=type==='favourite'?'You have not favourited any groups yet.':type==='visited'?'You have not marked any groups as visited yet.':'We will suggest local groups here as you explore Bubba Hub.';
            $target.html('<div class="bh-myhub-groups-empty"><strong>'+message+'</strong><a href="'+BubbaHubMyHubGroups.pageUrl+'">Explore My Groups</a></div>');return;
        }
        $target.html('<div class="bh-myhub-groups-track">'+html+'</div>');
        if(type!=='page') addControls($target);
    },'json').fail(function(){$target.html('<div class="bh-myhub-groups-empty"><strong>We could not load these groups.</strong><span>Please refresh and try again.</span></div>');});
}
function addControls($target){var $track=$target.find('.bh-myhub-groups-track');if($track.children().length<=1)return;$target.prepend('<button type="button" class="bh-myhub-carousel-prev" aria-label="Previous groups">‹</button>');$target.append('<button type="button" class="bh-myhub-carousel-next" aria-label="Next groups">›</button>');$target.on('click','.bh-myhub-carousel-prev',function(){$track.animate({scrollLeft:$track.scrollLeft()-300},220);});$target.on('click','.bh-myhub-carousel-next',function(){$track.animate({scrollLeft:$track.scrollLeft()+300},220);});}
function initWidget($widget){var type=$widget.data('group-type'),viewMore=$widget.data('view-more');var ids=list(type);request($widget,type,ids,6);var $link=$widget.closest('.bh-myhub-group-column').find('[data-group-view-more]');if($link.length&&viewMore){$link.attr('href',BubbaHubMyHubGroups.pageUrl+'?group_view='+encodeURIComponent(type));}}
function initPage($page){var type=$page.find('[data-myhub-group-results]').data('group-view'),ids=list(type);request($page.find('[data-myhub-group-results]'),'page',type==='suggested'?ids:ids,24);}
$(function(){
    if(typeof BubbaHubMyHubGroups==='undefined')return;
    $('[data-myhub-group-widget]').each(function(){initWidget($(this));});
    $('[data-myhub-groups-page]').each(function(){initPage($(this));});
});
})(jQuery);
