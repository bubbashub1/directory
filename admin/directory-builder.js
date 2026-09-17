jQuery(function($){
    'use strict';
    var cfg = window.BubbaHubDirectoryBuilder || {};
    var canvas = $('#bh-builder-canvas');
    var message = $('#bh-builder-message');
    var elements = cfg.elements || {};
    var layout = Array.isArray(cfg.layout) ? cfg.layout : [];

    function render(){
        canvas.empty();
        if(!layout.length){
            canvas.append('<div class="bh-builder-empty">Drop elements here to build your layout.</div>');
            return;
        }
        $.each(layout,function(i,item){
            var el=elements[item.type]||{label:item.type,icon:'•'};
            var row=$('<div class="bh-builder-row"/>').attr('data-id',item.id).attr('data-type',item.type);
            row.append('<span class="bh-builder-drag dashicons dashicons-move" aria-hidden="true"></span>');
            row.append($('<span class="bh-builder-row-icon"/>').text(el.icon));
            row.append($('<span class="bh-builder-row-label"/>').text(el.label));
            row.append('<button type="button" class="bh-builder-remove" aria-label="Remove element">Remove</button>');
            canvas.append(row);
        });
        canvas.sortable({items:'.bh-builder-row',handle:'.bh-builder-drag',placeholder:'bh-builder-sort-placeholder',update:sync});
    }
    function sync(){
        var next=[];
        canvas.find('.bh-builder-row').each(function(){
            var row=$(this);
            next.push({id:row.data('id'),type:row.data('type'),label:(elements[row.data('type')]||{}).label||row.data('type')});
        });
        layout=next;
    }
    function add(type){
        if(!elements[type]) return;
        layout.push({id:'bh-'+Date.now()+'-'+Math.floor(Math.random()*10000),type:type,label:elements[type].label});
        render();
    }
    $('.bh-builder-palette-item').on('dragstart',function(e){e.originalEvent.dataTransfer.setData('text/plain',$(this).data('type'));});
    canvas.on('dragover',function(e){e.preventDefault();});
    canvas.on('drop',function(e){e.preventDefault();add(e.originalEvent.dataTransfer.getData('text/plain'));});
    canvas.on('click','.bh-builder-remove',function(){
        var id=$(this).closest('.bh-builder-row').data('id');
        layout=$.grep(layout,function(item){return item.id!==id;});
        render();
    });
    $('#bh-builder-save').on('click',function(){
        sync();
        var btn=$(this).prop('disabled',true).text('Saving…');
        message.removeClass('success error').text('');
        $.post(cfg.ajaxUrl,{action:'bubbahub_directory_builder_save',nonce:cfg.nonce,layout:JSON.stringify(layout)})
            .done(function(resp){
                if(resp&&resp.success){message.addClass('success').text(resp.data.message||'Layout saved.');layout=resp.data.layout||layout;render();}
                else{message.addClass('error').text(resp&&resp.data&&resp.data.message?resp.data.message:'Could not save layout.');}
            })
            .fail(function(){message.addClass('error').text('Could not save layout. Please try again.');})
            .always(function(){btn.prop('disabled',false).text('Save Layout');});
    });
    render();
});
