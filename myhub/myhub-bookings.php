<?php
/**
 * BubbaHub My Hub – customer booking dashboard.
 * Shows upcoming and recent bookings and lets the logged-in customer cancel an upcoming booking.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_myhub_booking_actions', 40 );
function bubbahub_myhub_booking_actions() {
    if ( empty( $_GET['bh_myhub_booking_action'] ) || ! is_user_logged_in() ) return;
    $action = sanitize_key( wp_unslash( $_GET['bh_myhub_booking_action'] ) );
    $booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
    if ( 'cancel' !== $action || ! $booking_id ) return;
    $booking = get_post( $booking_id );
    if ( ! $booking || 'bh_booking' !== $booking->post_type ) return;
    if ( absint( get_post_meta( $booking_id, '_bh_user_id', true ) ) !== get_current_user_id() ) return;
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'bh_myhub_cancel_booking_' . $booking_id ) ) return;
    $status = sanitize_key( get_post_meta( $booking_id, '_bh_status', '' ) );
    if ( ! in_array( $status, array( 'confirmed', 'reserved' ), true ) ) return;
    update_post_meta( $booking_id, '_bh_status', 'cancelled' );
    update_post_meta( $booking_id, '_bh_cancelled_at', current_time( 'mysql' ) );
    wp_safe_redirect( add_query_arg( 'bh_booking_cancelled', '1', wp_get_referer() ?: home_url( '/my-hub/' ) ) );
    exit;
}

function bubbahub_myhub_booking_cancel_url( $booking_id ) {
    return wp_nonce_url( add_query_arg( array( 'bh_myhub_booking_action' => 'cancel', 'booking_id' => absint( $booking_id ) ), get_permalink() ), 'bh_myhub_cancel_booking_' . absint( $booking_id ) );
}

function bubbahub_myhub_booking_dashboard() {
    if ( ! is_user_logged_in() ) return '';
    $uid = get_current_user_id();
    $ids = get_posts( array(
        'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 100,
        'fields' => 'ids', 'meta_query' => array( array( 'key' => '_bh_user_id', 'value' => $uid, 'compare' => '=' ) ),
        'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true,
    ) );
    $upcoming = array(); $past = array();
    $now = current_time( 'timestamp' );
    foreach ( $ids as $booking_id ) {
        $session_id = absint( get_post_meta( $booking_id, '_bh_session_id', true ) );
        if ( ! $session_id || 'bh_session' !== get_post_type( $session_id ) ) continue;
        $date = sanitize_text_field( get_post_meta( $session_id, '_bh_date', true ) );
        $start = sanitize_text_field( get_post_meta( $session_id, '_bh_start_time', true ) );
        $timestamp = strtotime( trim( $date . ' ' . $start ) );
        if ( ! $timestamp ) continue;
        $group_id = absint( get_post_meta( $session_id, '_bh_group_id', true ) );
        $venue_id = absint( get_post_meta( $session_id, '_bh_venue_id', true ) );
        $row = array(
            'id' => $booking_id, 'session_id' => $session_id, 'group_id' => $group_id,
            'venue_id' => $venue_id, 'group' => $group_id ? get_the_title( $group_id ) : '',
            'venue' => $venue_id ? get_the_title( $venue_id ) : '', 'date' => $date, 'start' => $start,
            'end' => get_post_meta( $session_id, '_bh_end_time', true ),
            'places' => max( 1, absint( get_post_meta( $booking_id, '_bh_places', true ) ) ),
            'breakdown' => get_post_meta( $booking_id, '_bh_ticket_breakdown', true ),
            'total' => get_post_meta( $booking_id, '_bh_total_price', true ),
            'status' => sanitize_key( get_post_meta( $booking_id, '_bh_status', '' ) ),
            'payment' => sanitize_key( get_post_meta( $booking_id, '_bh_payment_status', '' ) ),
            'timestamp' => $timestamp,
        );
        if ( $timestamp >= $now && in_array( $row['status'], array( 'confirmed', 'reserved' ), true ) ) $upcoming[] = $row;
        else $past[] = $row;
    }
    usort( $upcoming, function( $a, $b ) { return $a['timestamp'] <=> $b['timestamp']; } );
    usort( $past, function( $a, $b ) { return $b['timestamp'] <=> $a['timestamp']; } );
    ob_start(); ?>
    <section class="bh-myhub-section bh-myhub-bookings">
      <div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">YOUR BOOKINGS</div><h2>My Bookings</h2><p>Keep track of your upcoming classes, tickets and booking status.</p></div></div>
      <?php if ( isset( $_GET['bh_booking_cancelled'] ) ) : ?><div class="bh-myhub-booking-notice">Your booking has been cancelled. If a refund is due, this will depend on the payment/refund process used by the class leader.</div><?php endif; ?>
      <?php if ( $upcoming ) : ?><div class="bh-myhub-booking-list">
        <?php foreach ( $upcoming as $booking ) : $breakdown = is_array( $booking['breakdown'] ) ? $booking['breakdown'] : array(); ?>
        <article class="bh-myhub-booking-detail">
          <div class="bh-myhub-booking-datebox"><strong><?php echo esc_html( wp_date( 'D', $booking['timestamp'] ) ); ?></strong><span><?php echo esc_html( wp_date( 'j M', $booking['timestamp'] ) ); ?></span></div>
          <div class="bh-myhub-booking-detail-main"><div class="bh-myhub-kicker">BOOKING #<?php echo esc_html( $booking['id'] ); ?></div><h3><?php echo esc_html( $booking['group'] ?: 'Your booking' ); ?></h3><p class="bh-myhub-booking-time"><?php echo esc_html( wp_date( 'g:i A', $booking['timestamp'] ) ); ?><?php if ( $booking['end'] ) : ?> – <?php echo esc_html( wp_date( 'g:i A', strtotime( $booking['date'] . ' ' . $booking['end'] ) ) ); ?><?php endif; ?></p><?php if ( $booking['venue'] ) : ?><p class="bh-myhub-booking-venue">⌖ <?php echo esc_html( $booking['venue'] ); ?></p><?php endif; ?>
            <div class="bh-myhub-booking-meta"><span><?php echo esc_html( $booking['places'] ); ?> place<?php echo 1 === $booking['places'] ? '' : 's'; ?></span><?php if ( '' !== (string) $booking['total'] ) : ?><span>£<?php echo esc_html( number_format( (float) $booking['total'], 2 ) ); ?></span><?php endif; ?><span><?php echo esc_html( ucfirst( $booking['status'] ?: 'pending' ) ); ?></span><?php if ( $booking['payment'] ) : ?><span>Payment: <?php echo esc_html( str_replace( '_', ' ', $booking['payment'] ) ); ?></span><?php endif; ?></div>
            <?php if ( $breakdown ) : ?><div class="bh-myhub-ticket-breakdown"><?php foreach ( $breakdown as $ticket ) : if ( ! is_array( $ticket ) ) continue; ?><span><?php echo esc_html( isset( $ticket['name'] ) ? $ticket['name'] : 'Ticket' ); ?> × <?php echo esc_html( absint( isset( $ticket['quantity'] ) ? $ticket['quantity'] : 0 ) ); ?></span><?php endforeach; ?></div><?php endif; ?>
          </div>
          <div class="bh-myhub-booking-actions"><a href="<?php echo esc_url( get_permalink( $booking['group_id'] ) ); ?>">View class</a><a class="bh-myhub-cancel-booking" href="<?php echo esc_url( bubbahub_myhub_booking_cancel_url( $booking['id'] ) ); ?>" onclick="return confirm('Cancel this booking?');">Cancel booking</a></div>
        </article>
        <?php endforeach; ?></div><?php else : ?><div class="bh-myhub-empty-family"><div class="bh-myhub-empty-icon">📅</div><div><h3>No upcoming bookings</h3><p>When you book a class through Bubba Hub, it will appear here.</p></div></div><?php endif; ?>
      <?php if ( $past ) : ?><details class="bh-myhub-past-bookings"><summary>Previous bookings</summary><div class="bh-myhub-past-list"><?php foreach ( array_slice( $past, 0, 20 ) as $booking ) : ?><div><strong><?php echo esc_html( $booking['group'] ?: 'Booking' ); ?></strong><span><?php echo esc_html( wp_date( 'j M Y, g:i A', $booking['timestamp'] ) ); ?> · <?php echo esc_html( ucfirst( $booking['status'] ?: 'completed' ) ); ?></span></div><?php endforeach; ?></div></details><?php endif; ?>
    </section>
    <?php return ob_get_clean();
}

add_filter( 'the_content', 'bubbahub_myhub_append_booking_dashboard', 35 );
function bubbahub_myhub_append_booking_dashboard( $content ) {
    if ( is_admin() || ! is_user_logged_in() || strpos( $content, 'bh-myhub-v3' ) === false || strpos( $content, 'bh-myhub-bookings' ) !== false ) return $content;
    return $content . bubbahub_myhub_booking_dashboard();
}

add_action( 'wp_head', 'bubbahub_myhub_booking_dashboard_css', 100 );
function bubbahub_myhub_booking_dashboard_css() {
    if ( ! is_user_logged_in() ) return; ?>
    <style id="bh-myhub-booking-dashboard-css">
    .bh-myhub-booking-notice{padding:14px 18px;border-radius:14px;background:#eef8ee;margin:0 0 18px}.bh-myhub-booking-list{display:grid;gap:14px}.bh-myhub-booking-detail{display:grid;grid-template-columns:68px 1fr auto;gap:18px;align-items:start;padding:18px;border:1px solid rgba(0,0,0,.08);border-radius:18px;background:#fff}.bh-myhub-booking-datebox{display:grid;place-items:center;padding:10px 6px;border-radius:14px;background:#f6f7f8;text-align:center}.bh-myhub-booking-datebox strong{font-size:13px}.bh-myhub-booking-datebox span{font-weight:800;font-size:18px}.bh-myhub-booking-detail h3{margin:2px 0 5px}.bh-myhub-booking-time,.bh-myhub-booking-venue{margin:2px 0}.bh-myhub-booking-meta,.bh-myhub-ticket-breakdown,.bh-myhub-booking-actions{display:flex;flex-wrap:wrap;gap:8px}.bh-myhub-booking-meta{margin-top:10px}.bh-myhub-booking-meta span,.bh-myhub-ticket-breakdown span{padding:5px 9px;border-radius:999px;background:#f5f6f7;font-size:12px}.bh-myhub-ticket-breakdown{margin-top:8px}.bh-myhub-booking-actions{justify-content:flex-end}.bh-myhub-booking-actions a{font-weight:700;text-decoration:none}.bh-myhub-cancel-booking{color:#b42318}.bh-myhub-past-bookings{margin-top:18px;border:1px solid rgba(0,0,0,.08);border-radius:14px;padding:14px 16px;background:#fff}.bh-myhub-past-bookings summary{cursor:pointer;font-weight:800}.bh-myhub-past-list{display:grid;gap:10px;margin-top:12px}.bh-myhub-past-list div{display:flex;justify-content:space-between;gap:15px;padding-top:10px;border-top:1px solid rgba(0,0,0,.07)}.bh-myhub-past-list span{opacity:.7;font-size:13px}@media(max-width:700px){.bh-myhub-booking-detail{grid-template-columns:56px 1fr}.bh-myhub-booking-actions{grid-column:1/-1;justify-content:flex-start}.bh-myhub-past-list div{display:block}.bh-myhub-past-list span{display:block;margin-top:3px}}
    </style><?php }
