<?php
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BUBBAHUB_BOOKING_VERSION', '1.2.0' );

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

function bubbahub_booking_normalize_ticket_types( $ticket_types ) {
    if ( ! is_array( $ticket_types ) ) return array();
    $normalized = array();
    foreach ( $ticket_types as $index => $ticket ) {
        if ( ! is_array( $ticket ) ) continue;
        $name = isset( $ticket['name'] ) ? sanitize_text_field( $ticket['name'] ) : '';
        if ( '' === $name ) continue;
        $price = isset( $ticket['price'] ) ? sanitize_text_field( $ticket['price'] ) : '';
        $capacity = isset( $ticket['capacity'] ) ? absint( $ticket['capacity'] ) : 0;
        $slug = ! empty( $ticket['slug'] ) ? sanitize_key( $ticket['slug'] ) : sanitize_title( $name );
        if ( '' === $slug ) $slug = 'ticket-' . absint( $index );
        $base_slug = $slug;
        $suffix = 2;
        while ( isset( $normalized[ $slug ] ) ) $slug = $base_slug . '-' . $suffix++;
        $normalized[ $slug ] = array( 'slug' => $slug, 'name' => $name, 'price' => $price, 'capacity' => $capacity );
    }
    return array_values( $normalized );
}

function bubbahub_booking_ticket_price( $price ) {
    if ( is_numeric( $price ) ) return (float) $price;
    $value = preg_replace( '/[^0-9.\-]/', '', (string) $price );
    return is_numeric( $value ) ? (float) $value : 0.0;
}

function bubbahub_booking_ticket_breakdown_total( $ticket_breakdown ) {
    if ( ! is_array( $ticket_breakdown ) ) return array( 'places' => 0, 'total' => 0.0 );
    $places = 0;
    $total = 0.0;
    foreach ( $ticket_breakdown as $ticket ) {
        if ( ! is_array( $ticket ) ) continue;
        $qty = isset( $ticket['quantity'] ) ? max( 0, absint( $ticket['quantity'] ) ) : 0;
        if ( ! $qty ) continue;
        $places += $qty;
        $total += $qty * bubbahub_booking_ticket_price( isset( $ticket['price'] ) ? $ticket['price'] : 0 );
    }
    return array( 'places' => $places, 'total' => round( $total, 2 ) );
}

function bubbahub_booking_session_stats( $session_id ) {
    $capacity = absint( bubbahub_booking_meta( $session_id, '_bh_capacity', 0 ) );
    $ticket_types = bubbahub_booking_normalize_ticket_types( get_post_meta( $session_id, '_bh_ticket_types', true ) );
    $bookings = get_posts( array(
        'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => -1,
        'fields' => 'ids', 'meta_query' => array( array( 'key' => '_bh_session_id', 'value' => absint( $session_id ) ) )
    ) );
    $used = 0;
    $ticket_used = array();
    foreach ( $ticket_types as $ticket ) $ticket_used[ $ticket['slug'] ] = 0;
    foreach ( $bookings as $booking_id ) {
        $status = bubbahub_booking_meta( $booking_id, '_bh_status', '' );
        if ( ! in_array( $status, array( 'confirmed', 'reserved' ), true ) ) continue;
        $used += max( 1, absint( bubbahub_booking_meta( $booking_id, '_bh_places', 1 ) ) );
        $breakdown = get_post_meta( $booking_id, '_bh_ticket_breakdown', true );
        if ( is_array( $breakdown ) ) {
            foreach ( $breakdown as $ticket ) {
                if ( ! is_array( $ticket ) ) continue;
                $slug = isset( $ticket['slug'] ) ? sanitize_key( $ticket['slug'] ) : '';
                if ( $slug && isset( $ticket_used[ $slug ] ) ) $ticket_used[ $slug ] += max( 0, absint( isset( $ticket['quantity'] ) ? $ticket['quantity'] : 0 ) );
            }
        }
    }
    $remaining = $capacity > 0 ? max( 0, $capacity - $used ) : null;
    $ticket_availability = array();
    foreach ( $ticket_types as $ticket ) {
        $ticket_capacity = absint( $ticket['capacity'] );
        $ticket_remaining = $ticket_capacity > 0 ? max( 0, $ticket_capacity - absint( $ticket_used[ $ticket['slug'] ] ) ) : null;
        $ticket_availability[] = array_merge( $ticket, array(
            'used' => absint( $ticket_used[ $ticket['slug'] ] ),
            'remaining' => $ticket_remaining,
            'full' => $ticket_capacity > 0 && $ticket_remaining <= 0,
        ) );
    }
    $has_available_ticket = false;
    foreach ( $ticket_availability as $ticket ) if ( ! $ticket['full'] ) { $has_available_ticket = true; break; }
    $full = $capacity > 0 && $remaining <= 0;
    if ( $ticket_types && ! $has_available_ticket ) $full = true;
    return array( 'capacity' => $capacity, 'used' => $used, 'remaining' => $remaining, 'full' => $full, 'ticket_types' => $ticket_availability );
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
        $ninja_form_id = absint( bubbahub_booking_meta( $session->ID, '_bh_ninja_form_id', 0 ) );
        if ( 'book_now' === $booking_action && ! $ninja_form_id ) $ninja_form_id = 4;
        $sessions[] = array(
            'id' => $session->ID, 'title' => get_the_title( $session->ID ), 'group_id' => absint( bubbahub_booking_meta( $session->ID, '_bh_group_id', 0 ) ),
            'venue_id' => absint( bubbahub_booking_meta( $session->ID, '_bh_venue_id', 0 ) ), 'date' => $session_date,
            'start_time' => bubbahub_booking_meta( $session->ID, '_bh_start_time', '' ), 'end_time' => bubbahub_booking_meta( $session->ID, '_bh_end_time', '' ),
            'price' => bubbahub_booking_meta( $session->ID, '_bh_price', '' ), 'booking_action' => $booking_action,
            'booking_method' => $booking_method, 'ninja_form_id' => $ninja_form_id,
            'external_url' => bubbahub_booking_meta( $session->ID, '_bh_external_url', '' ), 'reserve_enabled' => (bool) bubbahub_booking_meta( $session->ID, '_bh_reserve_enabled', false ),
            'capacity' => $stats['capacity'], 'used' => $stats['used'], 'remaining' => $stats['remaining'],
            'ticket_types' => $stats['ticket_types'],
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
    $args = wp_parse_args( $args, array( 'session_id'=>0,'group_id'=>0,'venue_id'=>0,'user_id'=>get_current_user_id(),'customer_name'=>'','customer_email'=>'','places'=>1,'ticket_breakdown'=>array(),'total_price'=>0,'status'=>'reserved','payment_status'=>'not_required','payment_method'=>'','invoice_id'=>0,'notes'=>'' ) );
    $session_id = absint( $args['session_id'] );
    if ( ! $session_id || get_post_type( $session_id ) !== 'bh_session' ) return new WP_Error( 'invalid_session', 'The selected booking session is invalid.' );

    // Always derive the booking relationship from the session rather than trusting
    // caller-supplied group/venue IDs. This keeps internal callers consistent and
    // prevents a forged booking request from attaching a booking to another listing.
    $session_group_id = absint( bubbahub_booking_meta( $session_id, '_bh_group_id', 0 ) );
    $session_venue_id = absint( bubbahub_booking_meta( $session_id, '_bh_venue_id', 0 ) );
    if ( ! $session_group_id || 'group' !== get_post_type( $session_group_id ) ) return new WP_Error( 'invalid_group', 'The booking session is not linked to a valid group.' );
    if ( absint( $args['group_id'] ) && absint( $args['group_id'] ) !== $session_group_id ) return new WP_Error( 'group_mismatch', 'The selected group does not match the booking session.' );
    if ( absint( $args['venue_id'] ) && $session_venue_id && absint( $args['venue_id'] ) !== $session_venue_id ) return new WP_Error( 'venue_mismatch', 'The selected venue does not match the booking session.' );
    if ( 'open' !== bubbahub_booking_meta( $session_id, '_bh_session_status', 'open' ) ) return new WP_Error( 'session_closed', 'This booking session is no longer open.' );

    $args['group_id'] = $session_group_id;
    $args['venue_id'] = $session_venue_id;

    // Serialise capacity checks for the same session so two simultaneous
    // booking requests cannot both reserve the final places.
    global $wpdb;
    $booking_lock = 'bubbahub_booking_' . $session_id;
    $booking_lock_acquired = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 5 )', $booking_lock ) );
    if ( 1 !== $booking_lock_acquired ) return new WP_Error( 'booking_lock_timeout', 'This session is being booked right now. Please try again.' );

    $stats = bubbahub_booking_session_stats( $session_id );
    $ticket_types = $stats['ticket_types'];
    $ticket_map = array();
    foreach ( $ticket_types as $ticket ) $ticket_map[ $ticket['slug'] ] = $ticket;

    $breakdown = array();
    if ( is_array( $args['ticket_breakdown'] ) ) {
        foreach ( $args['ticket_breakdown'] as $key => $ticket ) {
            if ( ! is_array( $ticket ) ) continue;
            $slug = isset( $ticket['slug'] ) ? sanitize_key( $ticket['slug'] ) : sanitize_key( $key );
            $quantity = isset( $ticket['quantity'] ) ? max( 0, absint( $ticket['quantity'] ) ) : 0;
            if ( ! $quantity || ! isset( $ticket_map[ $slug ] ) ) continue;
            $definition = $ticket_map[ $slug ];
            if ( $definition['capacity'] > 0 && $quantity > $definition['remaining'] ) {
                $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $booking_lock ) );
                return new WP_Error( 'ticket_full', sprintf( 'There are not enough %s tickets remaining.', $definition['name'] ) );
            }
            $breakdown[] = array( 'slug' => $slug, 'name' => $definition['name'], 'price' => $definition['price'], 'quantity' => $quantity );
        }
    }

    $calculated = bubbahub_booking_ticket_breakdown_total( $breakdown );
    $places = $calculated['places'] > 0 ? $calculated['places'] : max( 1, (int) $args['places'] );
    if ( $ticket_types && ! $calculated['places'] ) {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $booking_lock ) );
        return new WP_Error( 'ticket_required', 'Please select at least one ticket.' );
    }
    if ( $stats['capacity'] > 0 && ( $stats['remaining'] === null || $places > $stats['remaining'] ) ) {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $booking_lock ) );
        return new WP_Error( 'session_full', 'There are not enough spaces remaining for this session.' );
    }

    $total_price = $calculated['places'] > 0 ? $calculated['total'] : (float) $args['total_price'];
    $booking_id = wp_insert_post( array( 'post_type'=>'bh_booking','post_status'=>'publish','post_title'=>sprintf( 'Booking - %s - %s', get_the_title( $args['group_id'] ) ?: 'Group', sanitize_text_field( $args['customer_name'] ) ?: sanitize_email( $args['customer_email'] ) ) ), true );
    if ( is_wp_error( $booking_id ) ) {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $booking_lock ) );
        return $booking_id;
    }
    foreach ( array( '_bh_session_id'=> $session_id, '_bh_group_id'=>absint($args['group_id']), '_bh_venue_id'=>absint($args['venue_id']), '_bh_user_id'=>absint($args['user_id']), '_bh_customer_name'=>sanitize_text_field($args['customer_name']), '_bh_customer_email'=>sanitize_email($args['customer_email']), '_bh_places'=>$places, '_bh_ticket_breakdown'=>$breakdown, '_bh_total_price'=>number_format( $total_price, 2, '.', '' ), '_bh_status'=>sanitize_key($args['status']), '_bh_payment_status'=>sanitize_key($args['payment_status']), '_bh_payment_method'=>sanitize_key($args['payment_method']), '_bh_invoice_id'=>absint($args['invoice_id']), '_bh_notes'=>sanitize_textarea_field($args['notes']) ) as $key=>$value ) update_post_meta($booking_id,$key,$value);
    $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $booking_lock ) );
    do_action( 'bubbahub_booking_created', $booking_id, $args );
    return $booking_id;
}

require_once plugin_dir_path( __FILE__ ) . 'bubbahub-booking-frontend.php';
require_once plugin_dir_path( __FILE__ ) . 'bubbahub-booking-session-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'bubbahub-ninja-booking-integration.php';