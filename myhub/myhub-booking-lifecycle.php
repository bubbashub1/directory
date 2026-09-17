<?php
/**
 * BubbaHub My Hub – customer booking lifecycle.
 * Adds a secure booking dashboard, cancellation requests and booking emails.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_customer_booking_dashboard', 'bubbahub_customer_booking_dashboard_shortcode' );
add_action( 'init', 'bubbahub_myhub_booking_lifecycle_actions', 20 );
add_action( 'bubbahub_booking_created', 'bubbahub_myhub_booking_created_email', 10, 1 );

function bubbahub_myhub_booking_owned( $booking_id ) {
    if ( ! is_user_logged_in() ) return false;
    return absint( get_post_meta( $booking_id, '_bh_user_id', true ) ) === get_current_user_id();
}

function bubbahub_myhub_booking_lifecycle_actions() {
    if ( ! is_user_logged_in() || empty( $_GET['bh_booking_action'] ) || empty( $_GET['booking_id'] ) ) return;
    $action = sanitize_key( wp_unslash( $_GET['bh_booking_action'] ) );
    $booking_id = absint( $_GET['booking_id'] );
    if ( 'cancel' !== $action || ! $booking_id || ! bubbahub_myhub_booking_owned( $booking_id ) ) return;
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'bh_cancel_booking_' . $booking_id ) ) return;
    if ( 'bh_booking' !== get_post_type( $booking_id ) ) return;
    $status = get_post_meta( $booking_id, '_bh_status', true );
    if ( in_array( $status, array( 'confirmed', 'reserved' ), true ) ) {
        update_post_meta( $booking_id, '_bh_status', 'cancelled' );
        update_post_meta( $booking_id, '_bh_cancelled_by', 'customer' );
        update_post_meta( $booking_id, '_bh_cancelled_at', current_time( 'mysql' ) );
        update_post_meta( $booking_id, '_bh_payment_status', get_post_meta( $booking_id, '_bh_total_price', true ) > 0 ? 'refund_requested' : 'cancelled' );
    }
    wp_safe_redirect( remove_query_arg( array( 'bh_booking_action', 'booking_id', '_wpnonce' ) ) );
    exit;
}

function bubbahub_myhub_booking_cancel_url( $booking_id ) {
    return wp_nonce_url( add_query_arg( array( 'bh_booking_action' => 'cancel', 'booking_id' => absint( $booking_id ) ) ), 'bh_cancel_booking_' . absint( $booking_id ) );
}

function bubbahub_myhub_booking_rows( $include_past = false ) {
    if ( ! is_user_logged_in() ) return array();
    $ids = get_posts( array(
        'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 250, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => '_bh_user_id', 'value' => get_current_user_id(), 'compare' => '=' ) ),
        'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true,
    ) );
    $now = current_time( 'timestamp' ); $rows = array();
    foreach ( $ids as $booking_id ) {
        $session_id = absint( get_post_meta( $booking_id, '_bh_session_id', true ) );
        if ( ! $session_id || 'bh_session' !== get_post_type( $session_id ) ) continue;
        $date = sanitize_text_field( get_post_meta( $session_id, '_bh_date', true ) );
        $start = sanitize_text_field( get_post_meta( $session_id, '_bh_start_time', true ) );
        $end = sanitize_text_field( get_post_meta( $session_id, '_bh_end_time', true ) );
        $timestamp = strtotime( trim( $date . ' ' . $start ) );
        if ( ! $include_past && $timestamp && $timestamp < $now ) continue;
        $group_id = absint( get_post_meta( $session_id, '_bh_group_id', true ) );
        $venue_id = absint( get_post_meta( $session_id, '_bh_venue_id', true ) );
        $breakdown = get_post_meta( $booking_id, '_bh_ticket_breakdown', true );
        $rows[] = array(
            'id' => $booking_id, 'session_id' => $session_id, 'group_id' => $group_id, 'venue_id' => $venue_id,
            'group' => $group_id ? get_the_title( $group_id ) : get_the_title( $session_id ),
            'group_url' => $group_id ? get_permalink( $group_id ) : '', 'venue' => $venue_id ? get_the_title( $venue_id ) : '',
            'date' => $date, 'start' => $start, 'end' => $end, 'timestamp' => $timestamp ?: 0,
            'places' => max( 1, absint( get_post_meta( $booking_id, '_bh_places', true ) ) ),
            'total' => (float) get_post_meta( $booking_id, '_bh_total_price', true ), 'status' => sanitize_key( get_post_meta( $booking_id, '_bh_status', true ) ),
            'payment_status' => sanitize_key( get_post_meta( $booking_id, '_bh_payment_status', true ) ), 'tickets' => is_array( $breakdown ) ? $breakdown : array(),
        );
    }
    usort( $rows, function( $a, $b ) { return $a['timestamp'] <=> $b['timestamp']; } );
    return $rows;
}

function bubbahub_myhub_booking_card( $row, $past = false ) {
    ob_start(); ?>
    <article class="bh-myhub-booking-card bh-myhub-booking-card-lifecycle">
        <div class="bh-myhub-booking-icon" aria-hidden="true">🎟️</div>
        <div class="bh-myhub-booking-content">
            <div class="bh-myhub-eyebrow">Booking #<?php echo absint( $row['id'] ); ?></div>
            <h3><?php echo esc_html( $row['group'] ); ?></h3>
            <div class="bh-myhub-booking-date"><strong><?php echo esc_html( $row['timestamp'] ? wp_date( 'D j M Y', $row['timestamp'] ) : $row['date'] ); ?></strong><?php echo $row['start'] ? ' · ' . esc_html( wp_date( 'g:i A', strtotime( $row['start'] ) ) ) : ''; ?><?php echo $row['end'] ? ' – ' . esc_html( wp_date( 'g:i A', strtotime( $row['end'] ) ) ) : ''; ?></div>
            <?php if ( $row['venue'] ) : ?><div class="bh-myhub-booking-venue">⌖ <?php echo esc_html( $row['venue'] ); ?></div><?php endif; ?>
            <div class="bh-myhub-booking-meta"><span><?php echo absint( $row['places'] ); ?> place<?php echo 1 === $row['places'] ? '' : 's'; ?></span><span><?php echo $row['total'] > 0 ? '£' . number_format( $row['total'], 2 ) : 'Free / no payment'; ?></span><span class="bh-booking-status status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( ucfirst( $row['status'] ?: 'pending' ) ); ?></span><?php if ( $row['payment_status'] ) : ?><span><?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['payment_status'] ) ) ); ?></span><?php endif; ?></div>
            <?php if ( $row['tickets'] ) : ?><details class="bh-myhub-ticket-breakdown"><summary>Ticket breakdown</summary><ul><?php foreach ( $row['tickets'] as $ticket ) : ?><li><?php echo esc_html( isset( $ticket['name'] ) ? $ticket['name'] : 'Ticket' ); ?> × <?php echo absint( isset( $ticket['quantity'] ) ? $ticket['quantity'] : 0 ); ?></li><?php endforeach; ?></ul></details><?php endif; ?>
            <div class="bh-myhub-booking-actions">
                <?php if ( $row['group_url'] ) : ?><a href="<?php echo esc_url( $row['group_url'] ); ?>">View class</a><?php endif; ?>
                <?php if ( ! $past && in_array( $row['status'], array( 'confirmed', 'reserved' ), true ) ) : ?><a class="is-danger" href="<?php echo esc_url( bubbahub_myhub_booking_cancel_url( $row['id'] ) ); ?>" onclick="return window.confirm('Cancel this booking?');">Cancel booking</a><?php endif; ?>
            </div>
        </div>
    </article>
    <?php return ob_get_clean();
}

function bubbahub_customer_booking_dashboard_shortcode() {
    if ( ! is_user_logged_in() ) return '<section class="bh-myhub-section"><div class="bh-myhub-login"><h2>My Bookings</h2><p>Please log in to view your bookings.</p></div></section>';
    $upcoming = bubbahub_myhub_booking_rows( false );
    $past = bubbahub_myhub_booking_rows( true );
    $upcoming_ids = array_map( 'absint', wp_list_pluck( $upcoming, 'id' ) );
    $past = array_values( array_filter( $past, function( $row ) use ( $upcoming_ids ) { return ! in_array( absint( $row['id'] ), $upcoming_ids, true ); } ) );
    ob_start(); ?>
    <section class="bh-myhub-section bh-myhub-bookings-section">
        <div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">YOUR BOOKINGS</div><h2>My Bookings</h2><p>Keep track of your upcoming classes, tickets and booking status.</p></div></div>
        <?php if ( $upcoming ) : ?><div class="bh-myhub-bookings-list"><?php foreach ( $upcoming as $row ) echo bubbahub_myhub_booking_card( $row ); ?></div><?php else : ?><div class="bh-myhub-empty-family"><div class="bh-myhub-empty-icon">📅</div><div><h3>No upcoming bookings</h3><p>When you book a class, it will appear here automatically.</p></div></div><?php endif; ?>
        <?php if ( $past ) : ?><details class="bh-myhub-past-bookings"><summary>Previous bookings (<?php echo count( $past ); ?>)</summary><div class="bh-myhub-bookings-list"><?php foreach ( array_slice( $past, 0, 50 ) as $row ) echo bubbahub_myhub_booking_card( $row, true ); ?></div></details><?php endif; ?>
    </section>
    <?php return ob_get_clean();
}

function bubbahub_myhub_booking_created_email( $booking_id ) {
    $booking_id = absint( $booking_id ); if ( ! $booking_id ) return;
    if ( get_post_meta( $booking_id, '_bh_confirmation_email_sent', true ) ) return;
    $email = sanitize_email( get_post_meta( $booking_id, '_bh_customer_email', true ) );
    if ( ! is_email( $email ) ) return;
    $session_id = absint( get_post_meta( $booking_id, '_bh_session_id', true ) );
    $group_id = absint( get_post_meta( $booking_id, '_bh_group_id', true ) );
    $date = get_post_meta( $session_id, '_bh_date', true ); $start = get_post_meta( $session_id, '_bh_start_time', true );
    $subject = 'Bubba Hub booking confirmation #' . $booking_id;
    $body = "Hi " . sanitize_text_field( get_post_meta( $booking_id, '_bh_customer_name', true ) ) . ",\n\nYour Bubba Hub booking has been recorded.\n\nBooking: #" . $booking_id . "\nClass: " . ( $group_id ? get_the_title( $group_id ) : get_the_title( $session_id ) ) . "\nDate: " . ( $date ? wp_date( 'l, j F Y', strtotime( $date ) ) : '' ) . "\nTime: " . $start . "\nPlaces: " . absint( get_post_meta( $booking_id, '_bh_places', true ) ) . "\n\nYou can view your booking in My Hub on Bubba Hub.\n\nThanks,\nBubba Hub";
    if ( wp_mail( $email, $subject, $body ) ) update_post_meta( $booking_id, '_bh_confirmation_email_sent', current_time( 'mysql' ) );
}

add_filter( 'the_content', 'bubbahub_myhub_append_booking_dashboard', 30 );
function bubbahub_myhub_append_booking_dashboard( $content ) {
    if ( is_admin() || ! is_singular() || false === strpos( $content, '[bubbahub_my_hub' ) ) return $content;
    if ( false !== strpos( $content, 'bubbahub_customer_booking_dashboard' ) ) return $content;
    return $content . do_shortcode( '[bubbahub_customer_booking_dashboard]' );
}
