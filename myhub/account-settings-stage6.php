<?php
/**
 * Bubba Hub My Hub - Stage 6.
 * Adds server-side recently-viewed tracking and UI polish for saved groups.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', 'bubbahub_stage6_assets', 40 );
add_action( 'wp_ajax_bubbahub_stage6_track_group', 'bubbahub_stage6_track_group_ajax' );

function bubbahub_stage6_assets() {
    if ( ! is_user_logged_in() ) return;

    wp_enqueue_style( 'bubbahub-myhub' );
    wp_enqueue_script( 'jquery' );

    wp_add_inline_style( 'bubbahub-myhub', '
        .bh-myhub-card-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin:0 0 10px}.bh-myhub-save-group,.bh-myhub-why-group{border:1px solid #dfe8e4;background:#f8faf8;color:#3f5b55;border-radius:999px;padding:7px 10px;font:700 10px/1.2 inherit;cursor:pointer}.bh-myhub-save-group:hover,.bh-myhub-why-group:hover{border-color:#8aa69e;background:#eef5f2}.bh-myhub-save-group.is-saved{background:#1e3330;color:#fff;border-color:#1e3330}.bh-myhub-reason{margin:0 0 10px;padding:10px 12px;border:1px solid #e3ebe8;border-radius:13px;background:#fbfcfa;color:#536d67;font-size:10px;line-height:1.55}.bh-myhub-reason strong{display:block;color:#1e3330;font-size:11px;margin-bottom:3px}.bh-myhub-reason ul{margin:4px 0 0 15px;padding:0}.bh-myhub-reason li{margin:2px 0}.bh-myhub-group-card .bh-myhub-group-view{display:inline-flex;align-items:center;gap:5px}.bh-myhub-group-card.is-saved{outline:2px solid rgba(102,135,133,.12)}
        @media(max-width:600px){.bh-myhub-card-actions{gap:6px}.bh-myhub-save-group,.bh-myhub-why-group{font-size:9px;padding:7px 9px}}
    ' );

    wp_add_inline_script( 'jquery', 'window.BubbaHubStage6=' . wp_json_encode( array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'bubbahub_stage6' ),
    ) ) . ';', 'before' );

    wp_add_inline_script( 'jquery', '(function($){"use strict";
        function list(key){try{return JSON.parse(localStorage.getItem("bubbahub_"+key)||"[]");}catch(e){return [];}}
        function setList(key,items){localStorage.setItem("bubbahub_"+key,JSON.stringify(items));}
        function track(id){
            id=String(id||""); if(!id)return;
            var local=list("recently_viewed").filter(function(x){return String(x)!==id;});
            local.unshift(id); setList("recently_viewed",local.slice(0,20));
            $.post(BubbaHubStage6.ajaxUrl,{action:"bubbahub_stage6_track_group",nonce:BubbaHubStage6.nonce,group_id:id},function(r){
                if(r&&r.success&&r.data&&Array.isArray(r.data.ids))setList("recently_viewed",r.data.ids);
            },"json");
        }
        function currentGroupId(){
            var id=$("body").attr("data-group-id")||$("body").attr("data-bh-group-id")||$("meta[name=bubbahub-group-id]").attr("content");
            if(id)return id;
            var path=window.location.pathname;
            var m=path.match(/(?:directory\/|group\/)(\d+)(?:\/|$)/); return m?m[1]:"";
        }
        function syncButtons(){
            var saved=list("favourite").map(String);
            $("[data-bh-save-group]").each(function(){
                var $b=$(this),id=String($b.data("bh-save-group"));
                var yes=saved.indexOf(id)!==-1;
                $b.toggleClass("is-saved",yes).attr("aria-pressed",yes?"true":"false").text(yes?"♥ Saved":"♡ Save");
                $b.closest(".bh-myhub-group-card").toggleClass("is-saved",yes);
            });
        }
        $(function(){
            var id=currentGroupId();
            if(id)track(id);
            syncButtons();
            $(document).on("click","[data-bh-save-group]",function(){setTimeout(syncButtons,120);});
        });
    })(jQuery);', 'after' );
}

function bubbahub_stage6_saved_view_ids() {
    $ids = get_user_meta( get_current_user_id(), 'bubbahub_saved_recently_viewed', true );
    if ( ! is_array( $ids ) ) $ids = array();
    return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}

function bubbahub_stage6_track_group_ajax() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in.' ), 401 );
    check_ajax_referer( 'bubbahub_stage6', 'nonce' );

    $id = absint( $_POST['group_id'] ?? 0 );
    if ( ! $id || 'group' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
        wp_send_json_error( array( 'message' => 'Invalid group.' ) );
    }

    $ids = bubbahub_stage6_saved_view_ids();
    $ids = array_values( array_diff( $ids, array( $id ) ) );
    array_unshift( $ids, $id );
    $ids = array_slice( $ids, 0, 20 );
    update_user_meta( get_current_user_id(), 'bubbahub_saved_recently_viewed', $ids );

    wp_send_json_success( array( 'ids' => $ids ) );
}
