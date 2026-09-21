<?php
/**
 * BubbaHub Schedule Engine
 * Turns recurring schedule definitions into real bh_session occurrences.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_schedule_engine_bootstrap', 20 );
add_action( 'bubbahub_schedule_generate', 'bubbahub_schedule_engine_generate_all' );

function bubbahub_schedule_engine_bootstrap() {
    if ( ! wp_next_scheduled( 'bubbahub_schedule_generate' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'bubbahub_schedule_generate' );
    }
}

function bubbahub_schedule_engine_meta( $id, $key, $default = '' ) {
    $value = get_post_meta( $id, $key, true );
    return ( '' === $value || false === $value || null === $value ) ? $default : $value;
}

function bubbahub_schedule_engine_definition( $id ) {
    if ( function_exists( 'bubbahub_schedule_get_definition' ) ) return bubbahub_schedule_get_definition( $id );
    return array(
        'session_id' => absint( $id ),
        'group_id' => absint( bubbahub_schedule_engine_meta( $id, '_bh_group_id', 0 ) ),
        'venue_id' => absint( bubbahub_schedule_engine_meta( $id, '_bh_venue_id', 0 ) ),
        'date' => bubbahub_schedule_engine_meta( $id, '_bh_date', '' ),
        'start_time' => bubbahub_schedule_engine_meta( $id, '_bh_start_time', '' ),
        'end_time' => bubbahub_schedule_engine_meta( $id, '_bh_end_time', '' ),
        'recurrence' => bubbahub_schedule_engine_meta( $id, '_bh_recurrence', 'none' ),
        'interval' => max( 1, absint( bubbahub_schedule_engine_meta( $id, '_bh_recurrence_interval', 1 ) ) ),
        'weekday' => bubbahub_schedule_engine_meta( $id, '_bh_recurrence_weekday', '' ),
        'start_date' => bubbahub_schedule_engine_meta( $id, '_bh_recurrence_start', '' ),
        'end_date' => bubbahub_schedule_engine_meta( $id, '_bh_recurrence_end', '' ),
        'term_time' => (bool) bubbahub_schedule_engine_meta( $id, '_bh_term_time', false ),
        'exclusions' => (array) bubbahub_schedule_engine_meta( $id, '_bh_recurrence_exclusions', array() ),
    );
}

function bubbahub_schedule_engine_date_is_valid( $date, $definition ) {
    if ( empty( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) return false;
    if ( ! empty( $definition['start_date'] ) && $date < $definition['start_date'] ) return false;
    if ( ! empty( $definition['end_date'] ) && $date > $definition['end_date'] ) return false;
    if ( in_array( $date, (array) $definition['exclusions'], true ) ) return false;
    if ( ! empty( $definition['weekday'] ) && strtolower( wp_date( 'l', strtotime( $date ) ) ) !== strtolower( $definition['weekday'] ) ) return false;
    return true;
}

function bubbahub_schedule_engine_occurrence_key( $source_id, $date, $start_time ) {
    return 'bh_occ_' . md5( absint( $source_id ) . '|' . $date . '|' . $start_time );
}

function bubbahub_schedule_engine_occurrence_exists( $source_id, $date, $start_time ) {
    $posts = get_posts( array(
        'post_type' => 'bh_session', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids',
        'meta_query' => array(
            array( 'key' => '_bh_schedule_source', 'value' => absint( $source_id ), 'compare' => '=' ),
            array( 'key' => '_bh_date', 'value' => $date, 'compare' => '=' ),
            array( 'key' => '_bh_start_time', 'value' => $start_time, 'compare' => '=' ),
        ), 'no_found_rows' => true,
    ) );
    return ! empty( $posts );
}

function bubbahub_schedule_engine_create_occurrence( $source_id, $date, $definition ) {
    if ( bubbahub_schedule_engine_occurrence_exists( $source_id, $date, $definition['start_time'] ) ) return 0;
    $source = get_post( $source_id );
    if ( ! $source ) return 0;

    $post_id = wp_insert_post( array(
        'post_type' => 'bh_session', 'post_status' => 'publish',
        'post_title' => sprintf( '%s – %s', $source->post_title, wp_date( get_option( 'date_format' ), strtotime( $date ) ) ),
        'post_author' => $source->post_author,
    ), true );
    if ( is_wp_error( $post_id ) ) return 0;

    // Carry booking configuration to generated occurrences. Without these fields,
    // recurring sessions can exist but appear unavailable to the public booking flow.
    $copy_keys = array(
        '_bh_group_id', '_bh_venue_id', '_bh_start_time', '_bh_end_time', '_bh_capacity',
        '_bh_price', '_bh_ticket_types', '_bh_booking_url', '_bh_session_status',
        '_bh_booking_action', '_bh_booking_method', '_bh_reserve_enabled', '_bh_ninja_form_id',
        '_bh_external_url', '_bh_external_label', '_bh_datetime_sort'
    );
    foreach ( $copy_keys as $key ) {
        $value = get_post_meta( $source_id, $key, true );
        if ( '' !== $value && false !== $value ) update_post_meta( $post_id, $key, $value );
    }
    update_post_meta( $post_id, '_bh_date', $date );
    update_post_meta( $post_id, '_bh_schedule_source', absint( $source_id ) );
    update_post_meta( $post_id, '_bh_occurrence_key', bubbahub_schedule_engine_occurrence_key( $source_id, $date, $definition['start_time'] ) );
    update_post_meta( $post_id, '_bh_is_occurrence', 1 );
    update_post_meta( $post_id, '_bh_recurrence', 'none' );
    return $post_id;
}

function bubbahub_schedule_engine_generate( $source_id, $days = 90 ) {
    $source_id = absint( $source_id );
    if ( ! $source_id || 'bh_session' !== get_post_type( $source_id ) ) return 0;
    $definition = bubbahub_schedule_engine_definition( $source_id );
    if ( empty( $definition ) || 'none' === $definition['recurrence'] ) return 0;
    if ( empty( $definition['date'] ) || empty( $definition['start_time'] ) ) return 0;

    $origin = new DateTimeImmutable( $definition['date'] );
    $start = ! empty( $definition['start_date'] ) ? max( $definition['start_date'], $definition['date'] ) : $definition['date'];
    $today_end = gmdate( 'Y-m-d', strtotime( '+' . absint( $days ) . ' days' ) );
    $end = ! empty( $definition['end_date'] ) ? min( $definition['end_date'], $today_end ) : $today_end;
    $cursor = new DateTimeImmutable( $start );
    $limit = new DateTimeImmutable( $end );
    $created = 0;

    while ( $cursor <= $limit ) {
        $date = $cursor->format( 'Y-m-d' );
        if ( bubbahub_schedule_engine_date_is_valid( $date, $definition ) ) {
            $match = false;
            if ( 'weekly' === $definition['recurrence'] || 'fortnightly' === $definition['recurrence'] ) {
                $days_from_origin = (int) $origin->diff( $cursor )->format( '%r%a' );
                $period = 'fortnightly' === $definition['recurrence'] ? 14 : 7;
                $match = $days_from_origin >= 0 && 0 === $days_from_origin % ( $period * max( 1, $definition['interval'] ) );
            } elseif ( 'monthly' === $definition['recurrence'] ) {
                $months = ( (int) $cursor->format( 'Y' ) * 12 + (int) $cursor->format( 'm' ) ) - ( (int) $origin->format( 'Y' ) * 12 + (int) $origin->format( 'm' ) );
                $match = $cursor->format( 'd' ) === $origin->format( 'd' ) && $months >= 0 && 0 === $months % max( 1, $definition['interval'] );
            }
            if ( $match ) $created += bubbahub_schedule_engine_create_occurrence( $source_id, $date, $definition ) ? 1 : 0;
        }
        $cursor = $cursor->modify( '+1 day' );
    }
    return $created;
}

function bubbahub_schedule_engine_generate_all() {
    $ids = get_posts( array( 'post_type' => 'bh_session', 'post_status' => array( 'publish', 'draft', 'private' ), 'posts_per_page' => 250, 'fields' => 'ids', 'meta_key' => '_bh_recurrence', 'meta_compare' => 'EXISTS', 'no_found_rows' => true ) );
    foreach ( $ids as $id ) bubbahub_schedule_engine_generate( $id, 90 );
}

function bubbahub_schedule_generate_for_session( $session_id, $days = 90 ) {
    return bubbahub_schedule_engine_generate( $session_id, $days );
}
