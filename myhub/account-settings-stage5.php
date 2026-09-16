<?php
/**
 * Bubba Hub My Hub - Stage 5 saved groups and recommendation reasons.
 * Persists favourites/visited groups to the WordPress user account and exposes
 * a small AJAX API used by My Hub. Existing localStorage behaviour remains a
 * fallback for guests or older pages.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', 'bubbahub_stage5_assets', 30 );
add_action( 'wp_ajax_bubbahub_stage5_saved_groups', 'bubbahub_stage5_saved_groups_ajax' );
add_action( 'wp_ajax_bubbahub_stage5_group_reason', 'bubbahub_stage5_group_reason_ajax' );

function bubbahub_stage5_assets() {
    if ( ! is_user_logged_in() ) return;
    wp_enqueue_script( 'jquery' );
    wp_add_inline_script( 'jquery', 'window.BubbaHubStage5=' . wp_json_encode( array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'bubbahub_stage5' ),
    ) ) . ';', 'before' );
    wp_add_inline_script( 'jquery', '(function($){"use strict";function api(data,cb){data.action="bubbahub_stage5_saved_groups";data.nonce=BubbaHubStage5.nonce;$.post(BubbaHubStage5.ajaxUrl,data,cb,"json");}function sync(type){api({mode:"get",type:type},function(r){if(r&&r.success){localStorage.setItem("bubbahub_"+type,JSON.stringify(r.data.ids||[]));}});}function save(id,type){api({mode:"toggle",type:type,group_id:id},function(r){if(!r||!r.success)return;localStorage.setItem("bubbahub_"+type,JSON.stringify(r.data.ids||[]));$("[data-bh-save-group=\\""+id+"\\"]").toggleClass("is-saved",(r.data.ids||[]).map(String).indexOf(String(id))!==-1);});}function reason(id,$target){$.post(BubbaHubStage5.ajaxUrl,{action:"bubbahub_stage5_group_reason",nonce:BubbaHubStage5.nonce,group_id:id},function(r){if(r&&r.success)$target.html(r.data.html);else $target.html("<span>Matched to your saved family preferences.</span>");},"json");}$(function(){["favourite","visited","recently_viewed"].forEach(sync);$(document).on("click","[data-bh-save-group]",function(e){e.preventDefault();e.stopPropagation();save($(this).data("bh-save-group"),$(this).data("bh-save-type")||"favourite");});$(document).on("click","[data-bh-reason]",function(e){e.preventDefault();var $b=$(this),id=$b.data("bh-reason"),$box=$b.siblings(".bh-myhub-reason");if(!$box.length){$box=$("<div class=\\"bh-myhub-reason\\"></div>");$b.after($box);}if(!$box.data("loaded")){reason(id,$box);$box.data("loaded",1);}else{$box.toggle();}});});})(jQuery);', 'after' );
}

function bubbahub_stage5_group_ids( $type ) {
    $uid = get_current_user_id();
    $key = 'bubbahub_saved_' . sanitize_key( $type );
    $ids = get_user_meta( $uid, $key, true );
    if ( ! is_array( $ids ) ) $ids = array();
    return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}

function bubbahub_stage5_saved_groups_ajax() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in.' ), 401 );
    check_ajax_referer( 'bubbahub_stage5', 'nonce' );
    $type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'favourite';
    if ( ! in_array( $type, array( 'favourite', 'visited', 'recently_viewed' ), true ) ) $type = 'favourite';
    $ids = bubbahub_stage5_group_ids( $type );
    $mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'get';
    if ( 'toggle' === $mode ) {
        $id = absint( $_POST['group_id'] ?? 0 );
        if ( ! $id || 'group' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) wp_send_json_error( array( 'message' => 'Invalid group.' ) );
        if ( in_array( $id, $ids, true ) ) $ids = array_values( array_diff( $ids, array( $id ) ) ); else array_unshift( $ids, $id );
        $ids = array_slice( array_values( array_unique( $ids ) ), 0, 100 );
        update_user_meta( get_current_user_id(), 'bubbahub_saved_' . $type, $ids );
    }
    wp_send_json_success( array( 'ids' => $ids ) );
}

function bubbahub_stage5_group_reason_ajax() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in.' ), 401 );
    check_ajax_referer( 'bubbahub_stage5', 'nonce' );
    $id = absint( $_POST['group_id'] ?? 0 );
    if ( ! $id || 'group' !== get_post_type( $id ) ) wp_send_json_error( array( 'message' => 'Invalid group.' ) );
    $reasons = array();
    $uid = get_current_user_id();
    $term_ids = (array) get_user_meta( $uid, 'bubbahub_interest_term_ids', true );
    $taxonomy = get_user_meta( $uid, 'bubbahub_interest_taxonomy', true );
    if ( $taxonomy && taxonomy_exists( $taxonomy ) && $term_ids ) {
        $mine = get_terms( array( 'taxonomy' => $taxonomy, 'include' => array_map( 'absint', $term_ids ), 'hide_empty' => false ) );
        $group_terms = get_the_terms( $id, $taxonomy );
        if ( ! is_wp_error( $mine ) && ! is_wp_error( $group_terms ) ) {
            $names = array(); foreach ( $mine as $m ) foreach ( $group_terms as $g ) if ( (int) $m->term_id === (int) $g->term_id ) $names[] = $m->name;
            if ( $names ) $reasons[] = 'Matches your interests: ' . implode( ', ', array_slice( array_unique( $names ), 0, 3 ) );
        }
    }
    $selected = get_user_meta( $uid, 'bubbahub_selected_children', true );
    if ( ! is_array( $selected ) ) $selected = array();
    $age = function_exists( 'bubbahub_myhub_groups_age_tokens' ) ? bubbahub_myhub_groups_age_tokens( $selected ) : array();
    if ( $age ) {
        $value = function_exists( 'bubbahub_directory_get_field' ) ? bubbahub_directory_get_field( $id, 'age_range' ) : get_post_meta( $id, 'age_range', true );
        $hay = strtolower( is_array( $value ) ? implode( ' ', array_map( 'strval', $value ) ) : (string) $value );
        foreach ( $age as $token ) if ( $hay && false !== strpos( $hay, strtolower( $token ) ) ) { $reasons[] = 'Suitable for the selected child age range'; break; }
    }
    $location = get_user_meta( $uid, 'preferred_locations', true );
    if ( $location && get_the_terms( $id, 'location' ) ) $reasons[] = 'Matches a preferred local area';
    if ( ! $reasons ) $reasons[] = 'Suggested because it is a local Bubba Hub group that may suit your family.';
    $html = '<strong>Why this group?</strong><ul>'; foreach ( array_slice( $reasons, 0, 3 ) as $reason ) $html .= '<li>' . esc_html( $reason ) . '</li>'; $html .= '</ul>';
    wp_send_json_success( array( 'html' => $html ) );
}
