<?php
/**
 * Plugin Name: BubbaHub Booking Engine
 * Description: Stable booking data layer for BubbaHub Groups. Provides booking sessions, capacity tracking and booking records for later Ninja Forms + GetPaid integration.
 * Version: 1.0.0
 * Author: BubbaHub
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BUBBAHUB_BOOKING_VERSION', '1.0.0' );

action_add_action( 'init', 'bubbahub_booking_register_post_types' );

function action_add_action( $hook, $callback ) {
    add_action( $hook, $callback );
}

/**
 * Register the two private data types used by the booking engine.
 *
 * bh_session = a bookable occurrence of a Group.
 * bh_booking = a customer's reservation/booking against a session.
 */
function bubbahub_booking_register_post_types() {
    register_post_type( 'bh_session', array(
        'labels' => array(
            'name'          => 'Booking Sessions',
            'singular_name' => 'Booking Session',
            'menu_name'     => 'Booking Sessions',
        ),
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'supports'            => array( 'title' ),
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'exclude_from_search' => true,
        'rewrite'             => false,
    ) );

    register_post_type( 'bh_booking', array(
        'labels' => array(
            'name'          => 'Bookings',
            'singular_name' => 'Booking',
            'menu_name'     => 'Bookings',
        ),
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'supports'            => array( 'title' ),
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'exclude_from_search' => true,
        'rewrite'             => false,
    ) );
}

/**
 * Safe meta getter.
 */
function bubbahub_booking_meta( $post_id, $key, $default = '' ) {
    $value = get_post_meta( $post_id, $key, true );
    return ( $value !== '' && $value !== false && $value !== null ) ? $value : $default;
}

/**
 * Return a session's capacity and current confirmed/reserved places.
 * Pending/failed/cancelled bookings are not counted.
 */
function bubbahub_booking_session_stats( $session_id ) {
    $capacity = max( 0, (int) bubbahub_booking_meta( $session_id, '_bh_capacity', 0 ) );

    $query = new WP_Query( array(
        'post_type'      => 'bh_booking',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            array(
                'key'     => '_bh_session_id',
                'value'   => $session_id,
                'compare' => '=',
            ),
            array(
                'key'     => '_bh_status',
                'value'   => array( 'confirmed', 'reserved' ),
                'compare' => 'IN',
            ),
        ),
    ) );

    $used = count( $query->posts );
    $remaining = $capacity > 0 ? max( 0, $capacity - $used ) : null;

    return array(
        'capacity'  => $capacity,
        'used'      => $used,
        'remaining' => $remaining,
        'full'      => ( $capacity > 0 && $remaining <= 0 ),
    );
}

/**
 * Find available sessions for a Group.
 * Optional date filter uses Y-m-d.
 */
function bubbahub_booking_get_available_sessions( $group_id, $date = '' ) {
    $meta_query = array(
        array(
            'key'     => '_bh_group_id',
            'value'   => absint( $group_id ),
            'compare' => '=',
        ),
        array(
            'key'     => '_bh_session_status',
            'value'   => 'open',
            'compare' => '=',
        ),
    );

    if ( $date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        $meta_query[] = array(
            'key'     => '_bh_date',
            'value'   => $date,
            'compare' => '=',
        );
    }

    $query = new WP_Query( array(
        'post_type'      => 'bh_session',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value',
        'meta_key'       => '_bh_datetime_sort',
        'order'          => 'ASC',
        'no_found_rows'  => true,
        'meta_query'     => $meta_query,
    ) );

    $sessions = array();

    foreach ( $query->posts as $session ) {
        $stats = bubbahub_booking_session_stats( $session->ID );
        if ( $stats['full'] ) {
            continue;
        }

        $sessions[] = array(
            'id'             => $session->ID,
            'title'          => get_the_title( $session->ID ),
            'group_id'       => (int) bubbahub_booking_meta( $session->ID, '_bh_group_id', 0 ),
            'venue_id'       => (int) bubbahub_booking_meta( $session->ID, '_bh_venue_id', 0 ),
            'date'           => bubbahub_booking_meta( $session->ID, '_bh_date', '' ),
            'start_time'     => bubbahub_booking_meta( $session->ID, '_bh_start_time', '' ),
            'end_time'       => bubbahub_booking_meta( $session->ID, '_bh_end_time', '' ),
            'price'          => bubbahub_booking_meta( $session->ID, '_bh_price', '' ),
            'booking_method' => bubbahub_booking_meta( $session->ID, '_bh_booking_method', 'form' ),
            'external_url'   => bubbahub_booking_meta( $session->ID, '_bh_external_url', '' ),
            'reserve_enabled'=> (bool) bubbahub_booking_meta( $session->ID, '_bh_reserve_enabled', false ),
            'capacity'       => $stats['capacity'],
            'used'           => $stats['used'],
            'remaining'      => $stats['remaining'],
        );
    }

    return $sessions;
}

/**
 * Create a booking record. Payment/invoice creation is intentionally NOT done here.
 * Stage 2 will connect this record to Ninja Forms and Stage 3 to GetPaid.
 */
function bubbahub_booking_create( $args = array() ) {
    $defaults = array(
        'session_id'      => 0,
        'group_id'        => 0,
        'venue_id'        => 0,
        'user_id'         => get_current_user_id(),
        'customer_name'   => '',
        'customer_email'  => '',
        'places'          => 1,
        'status'          => 'reserved',
        'payment_status'  => 'not_required',
        'payment_method'  => '',
        'invoice_id'      => 0,
        'notes'           => '',
    );

    $args = wp_parse_args( $args, $defaults );
    $session_id = absint( $args['session_id'] );

    if ( ! $session_id || get_post_type( $session_id ) !== 'bh_session' ) {
        return new WP_Error( 'invalid_session', 'The selected booking session is invalid.' );
    }

    $group_id = absint( $args['group_id'] );
    if ( ! $group_id ) {
        $group_id = absint( bubbahub_booking_meta( $session_id, '_bh_group_id', 0 ) );
    }

    $venue_id = absint( $args['venue_id'] );
    if ( ! $venue_id ) {
        $venue_id = absint( bubbahub_booking_meta( $session_id, '_bh_venue_id', 0 ) );
    }

    $places = max( 1, (int) $args['places'] );
    $stats = bubbahub_booking_session_stats( $session_id );
    if ( $stats['capacity'] > 0 && ( $stats['remaining'] === null || $places > $stats['remaining'] ) ) {
        return new WP_Error( 'session_full', 'There are not enough spaces remaining for this session.' );
    }

    $title = sprintf(
        'Booking - %s - %s',
        get_the_title( $group_id ) ?: 'Group',
        sanitize_text_field( $args['customer_name'] ) ?: sanitize_email( $args['customer_email'] )
    );

    $booking_id = wp_insert_post( array(
        'post_type'   => 'bh_booking',
        'post_status' => 'publish',
        'post_title'  => $title,
    ), true );

    if ( is_wp_error( $booking_id ) ) {
        return $booking_id;
    }

    $meta = array(
        '_bh_session_id'     => $session_id,
        '_bh_group_id'       => $group_id,
        '_bh_venue_id'       => $venue_id,
        '_bh_user_id'        => absint( $args['user_id'] ),
        '_bh_customer_name'  => sanitize_text_field( $args['customer_name'] ),
        '_bh_customer_email' => sanitize_email( $args['customer_email'] ),
        '_bh_places'         => $places,
        '_bh_status'         => sanitize_key( $args['status'] ),
        '_bh_payment_status' => sanitize_key( $args['payment_status'] ),
        '_bh_payment_method' => sanitize_key( $args['payment_method'] ),
        '_bh_invoice_id'     => absint( $args['invoice_id'] ),
        '_bh_notes'          => sanitize_textarea_field( $args['notes'] ),
    );

    foreach ( $meta as $key => $value ) {
        update_post_meta( $booking_id, $key, $value );
    }

    return $booking_id;
}

/**
 * Lightweight availability endpoint for the future Ninja Forms date selector.
 */
add_action( 'wp_ajax_bubbahub_booking_sessions', 'bubbahub_booking_sessions_ajax' );
add_action( 'wp_ajax_nopriv_bubbahub_booking_sessions', 'bubbahub_booking_sessions_ajax' );

function bubbahub_booking_sessions_ajax() {
    check_ajax_referer( 'bubbahub_booking', 'nonce' );

    $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
    $date     = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

    if ( ! $group_id || get_post_type( $group_id ) !== 'group' ) {
        wp_send_json_error( array( 'message' => 'Invalid group.' ), 400 );
    }

    wp_send_json_success( array(
        'sessions' => bubbahub_booking_get_available_sessions( $group_id, $date ),
    ) );
}

add_action( 'wp_enqueue_scripts', 'bubbahub_booking_enqueue_frontend' );

function bubbahub_booking_enqueue_frontend() {
    if ( ! is_singular( 'group' ) ) {
        return;
    }

    wp_register_script( 'bubbahub-booking-engine', false, array(), BUBBAHUB_BOOKING_VERSION, true );
    wp_enqueue_script( 'bubbahub-booking-engine' );
    wp_localize_script( 'bubbahub-booking-engine', 'BubbaHubBooking', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'bubbahub_booking' ),
    ) );
}
