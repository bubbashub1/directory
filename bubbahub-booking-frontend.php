<?php
/**
 * BubbaHub Booking Engine — Group frontend integration.
 * Internal include loaded by the Booking Engine plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'bubbahub_booking_get_available_sessions' ) ) {
    return;
}

add_action( 'wp_enqueue_scripts', 'bubbahub_booking_frontend_assets', 30 );
function bubbahub_booking_frontend_assets() {
    if ( ! is_singular( 'group' ) ) return;
    $url = plugin_dir_url( __FILE__ );
    $version = defined( 'BUBBAHUB_BOOKING_VERSION' ) ? BUBBAHUB_BOOKING_VERSION : '1.1.0';
    wp_enqueue_style( 'bubbahub-booking-frontend', $url . 'assets/booking.css', array(), $version );
    wp_enqueue_script( 'bubbahub-booking-frontend', $url . 'assets/booking.js', array(), $version, true );
    wp_localize_script( 'bubbahub-booking-frontend', 'BubbaHubBookingUI', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'bubbahub_booking' ),
        'loading' => 'Loading availability…',
        'empty'   => 'No bookable sessions are currently available for this group.',
        'error'   => 'We could not load availability. Please try again.',
    ) );
}

add_action( 'wp_ajax_bubbahub_booking_reserve', 'bubbahub_booking_reserve_ajax' );
add_action( 'wp_ajax_nopriv_bubbahub_booking_reserve', 'bubbahub_booking_reserve_ajax' );
function bubbahub_booking_reserve_ajax() {
    check_ajax_referer( 'bubbahub_booking', 'nonce' );
    $session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
    $places = isset( $_POST['places'] ) ? max( 1, absint( $_POST['places'] ) ) : 1;
    $name = isset( $_POST['customer_name'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_name'] ) ) : '';
    $email = isset( $_POST['customer_email'] ) ? sanitize_email( wp_unslash( $_POST['customer_email'] ) ) : '';
    if ( ! $session_id || get_post_type( $session_id ) !== 'bh_session' ) wp_send_json_error( array( 'message' => 'Invalid booking session.' ), 400 );
    if ( ! $name || ! is_email( $email ) ) wp_send_json_error( array( 'message' => 'Please enter your name and a valid email address.' ), 400 );
    if ( ! (bool) bubbahub_booking_meta( $session_id, '_bh_reserve_enabled', false ) ) wp_send_json_error( array( 'message' => 'Reservations are not enabled for this session.' ), 400 );
    $group_id = absint( bubbahub_booking_meta( $session_id, '_bh_group_id', 0 ) );
    $venue_id = absint( bubbahub_booking_meta( $session_id, '_bh_venue_id', 0 ) );
    $booking_id = bubbahub_booking_create( array( 'session_id' => $session_id, 'group_id' => $group_id, 'venue_id' => $venue_id, 'customer_name' => $name, 'customer_email' => $email, 'places' => $places, 'status' => 'reserved', 'payment_status' => 'not_required', 'payment_method' => 'reservation' ) );
    if ( is_wp_error( $booking_id ) ) wp_send_json_error( array( 'message' => $booking_id->get_error_message() ), 400 );
    wp_send_json_success( array( 'booking_id' => (int) $booking_id, 'message' => 'Your space has been reserved.' ) );
}

function bubbahub_booking_render_group_widget( $group_id ) {
    if ( ! $group_id || get_post_type( $group_id ) !== 'group' ) return;
    $sessions = bubbahub_booking_get_available_sessions( $group_id );
    $dates = array();
    foreach ( $sessions as $session ) if ( ! empty( $session['date'] ) ) $dates[ $session['date'] ] = $session['date'];
    ksort( $dates );
    ?>
    <div id="bh-booking-modal" class="bh-booking-modal" hidden aria-hidden="true">
        <div class="bh-booking-modal-backdrop" data-booking-close></div>
        <div class="bh-booking-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="bh-booking-title">
            <button type="button" class="bh-booking-modal-close" data-booking-close aria-label="Close booking">×</button>
            <section id="bh-booking" class="bh-booking-widget" data-group-id="<?php echo esc_attr( $group_id ); ?>">
                <div class="bh-booking-heading"><div><span class="bh-booking-eyebrow">BOOKING &amp; AVAILABILITY</span><h2 id="bh-booking-title">Book this group</h2><p>Select a date to see the available sessions and spaces.</p></div></div>
                <div class="bh-booking-step">
                    <label for="bh-booking-date">1. Select a date</label>
                    <select id="bh-booking-date" class="bh-booking-select">
                        <option value="">Choose a date</option>
                        <?php foreach ( $dates as $date ) : ?>
                            <option value="<?php echo esc_attr( $date ); ?>"><?php echo esc_html( wp_date( 'l, j F Y', strtotime( $date ) ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bh-booking-step"><div class="bh-booking-label-row"><label>2. Select a session</label><span class="bh-booking-status" aria-live="polite"></span></div><div class="bh-booking-sessions" data-sessions><div class="bh-booking-empty">Choose a date to see available sessions.</div></div></div>
                <div class="bh-booking-actions" data-booking-actions hidden><div class="bh-booking-selected" data-selected-session>Choose a session above.</div><div class="bh-booking-action-buttons" data-action-buttons></div></div>
                <div class="bh-booking-reserve" data-reserve-panel hidden><div class="bh-booking-reserve-head"><div><strong>Reserve your spot</strong><span>No payment is taken for a reservation.</span></div><button type="button" class="bh-booking-close" data-reserve-close aria-label="Close reservation form">×</button></div><div class="bh-booking-form-grid"><label>Name<input type="text" data-reserve-name autocomplete="name"></label><label>Email<input type="email" data-reserve-email autocomplete="email"></label><label>Places<input type="number" data-reserve-places min="1" value="1" inputmode="numeric"></label></div><button type="button" class="bh-booking-primary" data-reserve-submit>Reserve Spot</button><div class="bh-booking-message" data-reserve-message aria-live="polite"></div></div>
            </section>
        </div>
    </div>
    <?php
}
