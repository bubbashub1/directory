<?php
/**
 * BubbaHub Leader Schedule Booking Tools
 * Adds live booking capacity and attendee management to leader sessions.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_leader_schedule_booking_tools_actions', 32 );
function bubbahub_leader_schedule_booking_tools_actions() {
    if ( empty( $_GET['bh_booking_action'] ) ) return;
    if ( ! is_user_logged_in() || ! function_exists( 'bubbahub_leader_dashboard_is_allowed' ) || ! bubbahub_leader_dashboard_is_allowed() ) return;
    $action = sanitize_key( wp_unslash( $_GET['bh_booking_action'] ) );
    $session_id = isset( $_GET['session'] ) ? absint( $_GET['session'] ) : 0;
    $booking_id = isset( $_GET['booking'] ) ? absint( $_GET['booking'] ) : 0;
    if ( ! $session_id || ! function_exists( 'bubbahub_leader_schedule_owned' ) || ! bubbahub_leader_schedule_owned( $session_id ) ) return;
    if ( ! $booking_id || 'bh_booking' !== get_post_type( $booking_id ) ) return;
    if ( absint( get_post_meta( $booking_id, '_bh_session_id', true ) ) !== $session_id ) return;
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'bh_booking_' . $action . '_' . $booking_id ) ) return;
    if ( 'confirm' === $action ) update_post_meta( $booking_id, '_bh_status', 'confirmed' );
    elseif ( 'cancel' === $action ) update_post_meta( $booking_id, '_bh_status', 'cancelled' );
    else return;
    wp_safe_redirect( add_query_arg( array( 'session' => $session_id, 'booking_updated' => $action ), bubbahub_leader_management_url( 'schedule' ) ) ); exit;
}

function bubbahub_leader_schedule_booking_action_url( $action, $session_id, $booking_id ) {
    return wp_nonce_url( add_query_arg( array( 'bh_booking_action' => $action, 'session' => absint( $session_id ), 'booking' => absint( $booking_id ) ), bubbahub_leader_management_url( 'schedule' ) ), 'bh_booking_' . $action . '_' . absint( $booking_id ) );
}

function bubbahub_leader_schedule_booking_data( $session_id ) {
    $ids = get_posts( array(
        'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => '_bh_session_id', 'value' => absint( $session_id ), 'compare' => '=' ) ),
        'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true,
    ) );
    $rows = array();
    foreach ( $ids as $id ) {
        $status = get_post_meta( $id, '_bh_status', true ) ?: 'pending';
        $rows[] = array(
            'id' => $id,
            'name' => get_post_meta( $id, '_bh_customer_name', true ) ?: 'Customer',
            'email' => get_post_meta( $id, '_bh_customer_email', true ),
            'places' => max( 1, absint( get_post_meta( $id, '_bh_places', true ) ) ),
            'status' => $status,
        );
    }
    return $rows;
}

function bubbahub_leader_schedule_booking_panel( $session_id ) {
    if ( ! function_exists( 'bubbahub_booking_session_stats' ) ) return '';
    $stats = bubbahub_booking_session_stats( $session_id );
    $rows = bubbahub_leader_schedule_booking_data( $session_id );
    $remaining = null === $stats['remaining'] ? 'Unlimited' : (string) $stats['remaining'];
    $confirmed_places = 0;
    foreach ( $rows as $row ) if ( in_array( $row['status'], array( 'confirmed', 'reserved' ), true ) ) $confirmed_places += $row['places'];
    ob_start(); ?>
    <section class="bh-schedule-booking-panel">
      <div class="bh-schedule-booking-summary"><div><strong><?php echo esc_html( $confirmed_places ); ?></strong><span>Booked</span></div><div><strong><?php echo esc_html( $remaining ); ?></strong><span>Spaces left</span></div><div><strong><?php echo esc_html( $stats['capacity'] ?: '∞' ); ?></strong><span>Capacity</span></div></div>
      <div class="bh-schedule-booking-heading"><div><p class="bh-leader-eyebrow">Bookings</p><h3>Attendees</h3></div></div>
      <?php if ( $rows ) : ?><div class="bh-schedule-attendees"><?php foreach ( $rows as $row ) : ?><div class="bh-attendee-row"><div class="bh-attendee-avatar"><?php echo esc_html( strtoupper( substr( $row['name'], 0, 1 ) ) ); ?></div><div class="bh-attendee-info"><strong><?php echo esc_html( $row['name'] ); ?></strong><span><?php echo esc_html( $row['email'] ); ?> · <?php echo esc_html( $row['places'] ); ?> place<?php echo 1 === $row['places'] ? '' : 's'; ?></span></div><span class="bh-attendee-status"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span><?php if ( 'confirmed' !== $row['status'] ) : ?><a href="<?php echo esc_url( bubbahub_leader_schedule_booking_action_url( 'confirm', $session_id, $row['id'] ) ); ?>">Confirm</a><?php endif; ?><?php if ( 'cancelled' !== $row['status'] ) : ?><a class="bh-danger" href="<?php echo esc_url( bubbahub_leader_schedule_booking_action_url( 'cancel', $session_id, $row['id'] ) ); ?>" onclick="return confirm('Cancel this booking?');">Cancel</a><?php endif; ?></div><?php endforeach; ?></div><?php else : ?><p class="bh-schedule-no-bookings">No bookings for this session yet.</p><?php endif; ?>
    </section>
    <?php return ob_get_clean();
}

add_filter( 'bubbahub_leader_schedule_after_editor', 'bubbahub_leader_schedule_booking_tools_render', 10, 2 );
function bubbahub_leader_schedule_booking_tools_render( $html, $session_id ) { return $html . bubbahub_leader_schedule_booking_panel( $session_id ); }
