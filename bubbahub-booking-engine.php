<?php
/**
 * Plugin Name: BubbaHub Booking Engine
 * Description: Session, availability and booking engine for BubbaHub groups.
 * Version: 1.1.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BUBBAHUB_BOOKING_VERSION', '1.1.0' );

function bubbahub_booking_meta( $post_id, $key, $default = '' ) {
    $value = get_post_meta( $post_id, $key, true );
    return ( '' === $value || null === $value ) ? $default : $value;
}

add_action( 'init', function() {
    register_post_type( 'bh_session', array(
        'labels' => array( 'name' => 'Booking Sessions', 'singular_name' => 'Booking Session' ),
        'public' => false, 'show_ui' => true, 'show_in_menu' => true,
        'supports' => array( 'title' ), 'menu_icon' => 'dashicons-calendar-alt'
    ) );
    register_post_type( 'bh_booking', array(
        'labels' => array( 'name' => 'Bookings', 'singular_name' => 'Booking' ),
        'public' => false, 'show_ui' => true, 'show_in_menu' => true,
        'supports' => array( 'title' ), 'menu_icon' => 'dashicons-tickets-alt'
    ) );
} );

function bubbahub_booking_session_stats( $session_id ) {
    $capacity = absint( bubbahub_booking_meta( $session_id, '_bh_capacity', 0 ) );
    $bookings = get_posts( array(
        'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => -1,
        'fields' => 'ids', 'meta_query' => array( array( 'key' => '_bh_session_id', 'value' => absint( $session_id ) ) )
    ) );
    $used = 0;
    foreach ( $bookings as $booking_id ) {
        $status = bubbahub_booking_meta( $booking_id, '_bh_status', '' );
        if ( in_array( $status, array( 'confirmed', 'reserved' ), true ) ) $used += max( 1, absint( bubbahub_booking_meta( $booking_id, '_bh_places', 1 ) ) );
    }
    $remaining = $capacity > 0 ? max( 0, $capacity - $used ) : null;
    return array( 'capacity' => $capacity, 'used' => $used, 'remaining' => $remaining, 'full' => $capacity > 0 && $remaining <= 0 );
}

function bubbahub_booking_normalize_session_date( $value ) {
    $value = trim( (string) $value );
    if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value ) ) return $value;
    if ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m ) ) return $m[3] . '-' . $m[2] . '-' . $m[1];
    if ( preg_match( '/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m ) ) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
}

function bubbahub_booking_get_available_sessions( $group_id, $date = '' ) {
    $query = new WP_Query( array(
        'post_type' => 'bh_session', 'post_status' => 'publish', 'posts_per_page' => -1,
        'orderby' => 'meta_value', 'meta_key' => '_bh_datetime_sort', 'order' => 'ASC', 'no_found_rows' => true,
        'meta_query' => array(
            array( 'key' => '_bh_group_id', 'value' => absint( $group_id ), 'compare' => '=' ),
            array( 'key' => '_bh_session_status', 'value' => 'open', 'compare' => '=' ),
        )
    ) );
    $requested_date = bubbahub_booking_normalize_session_date( $date );
    $sessions = array();
    foreach ( $query->posts as $session ) {
        $session_date = bubbahub_booking_normalize_session_date( bubbahub_booking_meta( $session->ID, '_bh_date', '' ) );
        if ( $requested_date && $session_date !== $requested_date ) continue;
        $stats = bubbahub_booking_session_stats( $session->ID );
        if ( $stats['full'] ) continue;
        $booking_action = bubbahub_booking_meta( $session->ID, '_bh_booking_action', '' );
        $booking_method = bubbahub_booking_meta( $session->ID, '_bh_booking_method', 'form' );
        if ( ! in_array( $booking_action, array( 'book_now', 'reserve_spot', 'external', 'none' ), true ) ) {
            $booking_action = 'external' === $booking_method ? 'external' : ( 'none' === $booking_method ? 'none' : ( (bool) bubbahub_booking_meta( $session->ID, '_bh_reserve_enabled', false ) ? 'reserve_spot' : 'book_now' ) );
        }
        $sessions[] = array(
            'id' => $session->ID, 'title' => get_the_title( $session->ID ), 'group_id' => absint( bubbahub_booking_meta( $session->ID, '_bh_group_id', 0 ) ),
            'venue_id' => absint( bubbahub_booking_meta( $session->ID, '_bh_venue_id', 0 ) ), 'date' => $session_date,
            'start_time' => bubbahub_booking_meta( $session->ID, '_bh_start_time', '' ), 'end_time' => bubbahub_booking_meta( $session->ID, '_bh_end_time', '' ),
            'price' => bubbahub_booking_meta( $session->ID, '_bh_price', '' ), 'booking_action' => $booking_action,
            'booking_method' => $booking_method, 'ninja_form_id' => absint( bubbahub_booking_meta( $session->ID, '_bh_ninja_form_id', 0 ) ),
            'external_url' => bubbahub_booking_meta( $session->ID, '_bh_external_url', '' ), 'reserve_enabled' => (bool) bubbahub_booking_meta( $session->ID, '_bh_reserve_enabled', false ),
            'capacity' => $stats['capacity'], 'used' => $stats['used'], 'remaining' => $stats['remaining'],
        );
    }
    return $sessions;
}

add_action( 'wp_ajax_bubbahub_booking_sessions', 'bubbahub_booking_sessions_ajax' );
add_action( 'wp_ajax_nopriv_bubbahub_booking_sessions', 'bubbahub_booking_sessions_ajax' );
function bubbahub_booking_sessions_ajax() {
    check_ajax_referer( 'bubbahub_booking', 'nonce' );
    $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
    $date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
    if ( ! $group_id || get_post_type( $group_id ) !== 'group' ) wp_send_json_error( array( 'message' => 'Invalid group.' ), 400 );
    wp_send_json_success( array( 'sessions' => bubbahub_booking_get_available_sessions( $group_id, $date ) ) );
}

function bubbahub_booking_create( $args = array() ) {
    $args = wp_parse_args( $args, array( 'session_id'=>0,'group_id'=>0,'venue_id'=>0,'user_id'=>get_current_user_id(),'customer_name'=>'','customer_email'=>'','places'=>1,'status'=>'reserved','payment_status'=>'not_required','payment_method'=>'','invoice_id'=>0,'notes'=>'' ) );
    $session_id = absint( $args['session_id'] );
    if ( ! $session_id || get_post_type( $session_id ) !== 'bh_session' ) return new WP_Error( 'invalid_session', 'The selected booking session is invalid.' );
    $stats = bubbahub_booking_session_stats( $session_id ); $places = max( 1, (int) $args['places'] );
    if ( $stats['capacity'] > 0 && ( $stats['remaining'] === null || $places > $stats['remaining'] ) ) return new WP_Error( 'session_full', 'There are not enough spaces remaining for this session.' );
    $booking_id = wp_insert_post( array( 'post_type'=>'bh_booking','post_status'=>'publish','post_title'=>sprintf( 'Booking - %s - %s', get_the_title( $args['group_id'] ) ?: 'Group', sanitize_text_field( $args['customer_name'] ) ?: sanitize_email( $args['customer_email'] ) ) ), true );
    if ( is_wp_error( $booking_id ) ) return $booking_id;
    foreach ( array( '_bh_session_id'=> $session_id, '_bh_group_id'=>absint($args['group_id']), '_bh_venue_id'=>absint($args['venue_id']), '_bh_user_id'=>absint($args['user_id']), '_bh_customer_name'=>sanitize_text_field($args['customer_name']), '_bh_customer_email'=>sanitize_email($args['customer_email']), '_bh_places'=>$places, '_bh_status'=>sanitize_key($args['status']), '_bh_payment_status'=>sanitize_key($args['payment_status']), '_bh_payment_method'=>sanitize_key($args['payment_method']), '_bh_invoice_id'=>absint($args['invoice_id']), '_bh_notes'=>sanitize_textarea_field($args['notes']) ) as $key=>$value ) update_post_meta($booking_id,$key,$value);
    return $booking_id;
}

require_once plugin_dir_path( __FILE__ ) . 'bubbahub-booking-frontend.php';
require_once plugin_dir_path( __FILE__ ) . 'bubbahub-booking-session-admin.php';
