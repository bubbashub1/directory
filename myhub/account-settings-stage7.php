<?php
/**
 * Bubba Hub My Hub - Stage 7.
 * Connects My Hub group cards to the existing booking engine without changing booking creation.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', 'bubbahub_stage7_assets', 50 );
add_action( 'wp_ajax_bubbahub_stage7_group_booking', 'bubbahub_stage7_group_booking_ajax' );

function bubbahub_stage7_assets() {
    if ( ! is_user_logged_in() ) return;
    wp_enqueue_style( 'bubbahub-myhub' );
    wp_enqueue_script( 'jquery' );
    wp_add_inline_style( 'bubbahub-myhub', '
        .bh-myhub-booking-summary{margin:0 0 10px;padding:10px 12px;border:1px solid #e3ebe8;border-radius:13px;background:#f8faf8;color:#536d67;font-size:10px;line-height:1.5}.bh-myhub-booking-summary strong{display:block;color:#1e3330;font-size:11px;margin-bottom:3px}.bh-myhub-booking-summary .bh-booking-status{display:inline-flex;margin-top:4px;padding:4px 7px;border-radius:999px;background:#e7f2ed;color:#315b4f;font-weight:800;font-size:9px}.bh-myhub-booking-summary a{display:inline-block;margin-top:6px;color:#3f5b55;font-weight:800;text-decoration:none}.bh-myhub-booking-summary a:hover{text-decoration:underline}
    ' );
    wp_add_inline_script( 'jquery', 'window.BubbaHubStage7=' . wp_json_encode( array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'bubbahub_stage7' ),
    ) ) . ';', 'before' );
    wp_add_inline_script( 'jquery', '(function($){"use strict";
        function enhance(){
            $(".bh-myhub-group-card[data-group-id]").each(function(){
                var $card=$(this),id=parseInt($card.attr("data-group-id"),10);
                if(!id||$card.data("stage7-loaded"))return;
                $card.data("stage7-loaded",1);
                $.post(BubbaHubStage7.ajaxUrl,{action:"bubbahub_stage7_group_booking",nonce:BubbaHubStage7.nonce,group_id:id},function(r){
                    if(!r||!r.success||!r.data)return;
                    if(!r.data.has_booking&&!r.data.has_sessions)return;
                    var html="<div class=\"bh-myhub-booking-summary\">";
                    if(r.data.has_booking){html+="<strong>Upcoming booking</strong><span>"+r.data.booking_label+"</span><span class=\"bh-booking-status\">"+r.data.status_label+"</span>";}
                    if(r.data.next_session_url){html+="<a href=\""+r.data.next_session_url+"\">"+(r.data.has_booking?"View group & booking options →":"Book a session →")+"</a>";}
                    html+="</div>";
                    $card.find(".bh-myhub-group-body").prepend(html);
                },"json");
            });
        }
        $(function(){enhance();var obs=new MutationObserver(enhance);obs.observe(document.body,{childList:true,subtree:true});});
    })(jQuery);', 'after' );
}

function bubbahub_stage7_group_booking_ajax() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in.' ), 401 );
    check_ajax_referer( 'bubbahub_stage7', 'nonce' );
    $group_id = absint( $_POST['group_id'] ?? 0 );
    if ( ! $group_id || 'group' !== get_post_type( $group_id ) || 'publish' !== get_post_status( $group_id ) ) {
        wp_send_json_error( array( 'message' => 'Invalid group.' ), 400 );
    }

    $result = array( 'has_booking' => false, 'has_sessions' => false, 'booking_label' => '', 'status_label' => '', 'next_session_url' => get_permalink( $group_id ) );
    $now = current_time( 'timestamp' );

    if ( post_type_exists( 'bh_booking' ) ) {
        $booking_ids = get_posts( array(
            'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 20, 'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => array(
                array( 'key' => '_bh_user_id', 'value' => get_current_user_id(), 'compare' => '=' ),
                array( 'key' => '_bh_group_id', 'value' => $group_id, 'compare' => '=' ),
                array( 'key' => '_bh_status', 'value' => array( 'confirmed', 'reserved' ), 'compare' => 'IN' ),
            ),
        ) );
        $best = null;
        foreach ( $booking_ids as $booking_id ) {
            $session_id = absint( get_post_meta( $booking_id, '_bh_session_id', true ) );
            if ( ! $session_id || 'bh_session' !== get_post_type( $session_id ) ) continue;
            $date = sanitize_text_field( get_post_meta( $session_id, '_bh_date', true ) );
            $start = sanitize_text_field( get_post_meta( $session_id, '_bh_start_time', true ) );
            $stamp = strtotime( trim( $date . ' ' . $start ) );
            if ( ! $stamp || $stamp < $now ) continue;
            if ( null === $best || $stamp < $best['stamp'] ) $best = array( 'stamp' => $stamp, 'date' => $date, 'start' => $start, 'status' => sanitize_key( get_post_meta( $booking_id, '_bh_status', true ) ) );
        }
        if ( $best ) {
            $result['has_booking'] = true;
            $result['booking_label'] = function_exists( 'bubbahub_myhub_booking_date_label' ) ? bubbahub_myhub_booking_date_label( $best['date'], $best['start'] ) : wp_date( 'D j M, g:i A', $best['stamp'] );
            $result['status_label'] = 'confirmed' === $best['status'] ? 'Confirmed' : 'Reserved';
        }
    }

    if ( function_exists( 'bubbahub_booking_get_available_sessions' ) ) {
        $sessions = bubbahub_booking_get_available_sessions( $group_id );
        foreach ( $sessions as $session ) {
            $stamp = strtotime( trim( (string) ( $session['date'] ?? '' ) . ' ' . (string) ( $session['start_time'] ?? '' ) ) );
            if ( $stamp && $stamp >= $now ) {
                $result['has_sessions'] = true;
                $date = sanitize_text_field( $session['date'] );
                $result['next_session_url'] = add_query_arg( array( 'group_id' => $group_id, 'date' => $date, 'session_id' => absint( $session['id'] ) ), home_url( '/book/' ) );
                break;
            }
        }
    }

    wp_send_json_success( $result );
}
