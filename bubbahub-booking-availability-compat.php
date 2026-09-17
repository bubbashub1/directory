<?php
/**
 * Plugin Name: BubbaHub Booking Availability Compatibility
 * Description: Hardens Booking Engine session/date lookup against legacy metadata formats.
 * Version: 1.0.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_availability_compat_value( $post_id, $key, $default = '' ) {
    $value = get_post_meta( $post_id, $key, true );
    return ( $value === '' || $value === null ) ? $default : $value;
}

function bubbahub_availability_compat_id( $value ) {
    if ( is_object( $value ) && isset( $value->ID ) ) return absint( $value->ID );
    if ( is_array( $value ) ) {
        foreach ( array( 'ID', 'id', 'value', 'ID_' ) as $key ) {
            if ( isset( $value[ $key ] ) && is_numeric( $value[ $key ] ) ) return absint( $value[ $key ] );
        }
    }
    return absint( $value );
}

function bubbahub_availability_compat_date( $value ) {
    if ( $value instanceof DateTimeInterface ) return $value->format( 'Y-m-d' );
    $value = trim( (string) $value );
    if ( $value === '' ) return '';

    if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value ) ) return $value;
    if ( preg_match( '/^(\d{8})$/', $value, $m ) ) {
        return substr( $m[1], 0, 4 ) . '-' . substr( $m[1], 4, 2 ) . '-' . substr( $m[1], 6, 2 );
    }
    if ( preg_match( '/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $value, $m ) ) {
        return sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
    }
    if ( is_numeric( $value ) && (int) $value > 100000000 ) {
        $timestamp = (int) $value;
        return wp_date( 'Y-m-d', $timestamp );
    }
    return '';
}

function bubbahub_availability_compat_open( $value ) {
    if ( is_bool( $value ) ) return $value;
    if ( is_numeric( $value ) ) return (int) $value === 1;
    return in_array( strtolower( trim( (string) $value ) ), array( 'open', '1', 'true', 'yes', 'on' ), true );
}

function bubbahub_availability_compat_stats( $session_id ) {
    $capacity = absint( bubbahub_availability_compat_value( $session_id, '_bh_capacity', 0 ) );
    $bookings = get_posts( array(
        'post_type'      => 'bh_booking',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => array( array( 'key' => '_bh_session_id', 'value' => absint( $session_id ) ) ),
    ) );

    $used = 0;
    foreach ( $bookings as $booking_id ) {
        $status = strtolower( trim( (string) bubbahub_availability_compat_value( $booking_id, '_bh_status', '' ) ) );
        if ( in_array( $status, array( 'confirmed', 'reserved' ), true ) ) {
            $used += max( 1, absint( bubbahub_availability_compat_value( $booking_id, '_bh_places', 1 ) ) );
        }
    }

    $remaining = $capacity > 0 ? max( 0, $capacity - $used ) : null;
    return array( 'capacity' => $capacity, 'used' => $used, 'remaining' => $remaining, 'full' => $capacity > 0 && $remaining <= 0 );
}

function bubbahub_availability_compat_sessions( $group_id, $requested_date = '' ) {
    $group_id = absint( $group_id );
    $requested_date = bubbahub_availability_compat_date( $requested_date );
    if ( ! $group_id ) return array();

    /*
     * Deliberately do not put _bh_group_id or _bh_session_status in WP_Query.
     * Older sessions can contain these values as ACF IDs, strings or legacy
     * boolean/status values. We normalise them in PHP instead.
     */
    $posts = get_posts( array(
        'post_type'      => 'bh_session',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value',
        'meta_key'       => '_bh_datetime_sort',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ) );

    $sessions = array();

    foreach ( $posts as $session_id ) {
        $stored_group = bubbahub_availability_compat_id( bubbahub_availability_compat_value( $session_id, '_bh_group_id', 0 ) );
        if ( $stored_group !== $group_id ) continue;

        $status = bubbahub_availability_compat_value( $session_id, '_bh_session_status', 'open' );
        if ( ! bubbahub_availability_compat_open( $status ) ) continue;

        $session_date = bubbahub_availability_compat_date( bubbahub_availability_compat_value( $session_id, '_bh_date', '' ) );
        if ( $requested_date && $session_date !== $requested_date ) continue;
        if ( ! $session_date ) continue;

        $stats = bubbahub_availability_compat_stats( $session_id );
        if ( $stats['full'] ) continue;

        $booking_action = sanitize_key( bubbahub_availability_compat_value( $session_id, '_bh_booking_action', '' ) );
        $booking_method = sanitize_key( bubbahub_availability_compat_value( $session_id, '_bh_booking_method', 'form' ) );
        $reserve_enabled = bubbahub_availability_compat_value( $session_id, '_bh_reserve_enabled', false );

        if ( ! in_array( $booking_action, array( 'book_now', 'reserve_spot', 'external', 'none' ), true ) ) {
            $booking_action = $booking_method === 'external'
                ? 'external'
                : ( $booking_method === 'none'
                    ? 'none'
                    : ( bubbahub_availability_compat_open( $reserve_enabled ) ? 'reserve_spot' : 'book_now' ) );
        }

        $sessions[] = array(
            'id'               => $session_id,
            'title'            => get_the_title( $session_id ),
            'group_id'         => $stored_group,
            'venue_id'         => bubbahub_availability_compat_id( bubbahub_availability_compat_value( $session_id, '_bh_venue_id', 0 ) ),
            'date'             => $session_date,
            'start_time'       => bubbahub_availability_compat_value( $session_id, '_bh_start_time', '' ),
            'end_time'         => bubbahub_availability_compat_value( $session_id, '_bh_end_time', '' ),
            'price'            => bubbahub_availability_compat_value( $session_id, '_bh_price', '' ),
            'booking_action'   => $booking_action,
            'booking_method'  => $booking_method,
            'ninja_form_id'    => absint( bubbahub_availability_compat_value( $session_id, '_bh_ninja_form_id', 0 ) ),
            'external_url'    => bubbahub_availability_compat_value( $session_id, '_bh_external_url', '' ),
            'reserve_enabled' => bubbahub_availability_compat_open( $reserve_enabled ),
            'capacity'         => $stats['capacity'],
            'used'             => $stats['used'],
            'remaining'        => $stats['remaining'],
        );
    }

    return $sessions;
}

function bubbahub_availability_compat_ajax() {
    check_ajax_referer( 'bubbahub_booking', 'nonce' );

    $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
    $date     = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

    if ( ! $group_id || get_post_type( $group_id ) !== 'group' ) {
        wp_send_json_error( array( 'message' => 'Invalid group.' ), 400 );
    }

    wp_send_json_success( array(
        'sessions' => bubbahub_availability_compat_sessions( $group_id, $date ),
    ) );
}

/* Replace the strict AJAX lookup after all plugins have registered their hooks. */
add_action( 'plugins_loaded', function() {
    remove_action( 'wp_ajax_bubbahub_booking_sessions', 'bubbahub_booking_sessions_ajax' );
    remove_action( 'wp_ajax_nopriv_bubbahub_booking_sessions', 'bubbahub_booking_sessions_ajax' );
    add_action( 'wp_ajax_bubbahub_booking_sessions', 'bubbahub_availability_compat_ajax' );
    add_action( 'wp_ajax_nopriv_bubbahub_booking_sessions', 'bubbahub_availability_compat_ajax' );
}, 99 );
