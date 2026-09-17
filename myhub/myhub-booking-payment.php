<?php
/** Secure customer payment actions for Bubba Hub bookings. */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_myhub_booking_payment_actions', 21 );
function bubbahub_myhub_booking_payment_actions() {
    if ( ! is_user_logged_in() || empty( $_GET['bh_booking_payment'] ) || 'pay' !== sanitize_key( wp_unslash( $_GET['bh_booking_payment'] ) ) ) return;
    $booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( ! $booking_id || 'bh_booking' !== get_post_type( $booking_id ) || ! wp_verify_nonce( $nonce, 'bh_pay_booking_' . $booking_id ) ) return;
    if ( absint( get_post_meta( $booking_id, '_bh_user_id', true ) ) !== get_current_user_id() ) return;
    if ( ! in_array( get_post_meta( $booking_id, '_bh_payment_status', true ), array( 'pending', 'failed' ), true ) ) return;
    if ( ! function_exists( 'bubbahub_stripe_create_checkout' ) ) return;

    $checkout = bubbahub_stripe_create_checkout( $booking_id );
    if ( is_wp_error( $checkout ) || empty( $checkout['url'] ) ) {
        wp_safe_redirect( add_query_arg( 'bh_payment_error', rawurlencode( is_wp_error( $checkout ) ? $checkout->get_error_message() : 'Unable to start payment.' ), wp_get_referer() ?: home_url( '/myhub/' ) ) );
        exit;
    }
    update_post_meta( $booking_id, '_bh_status', 'reserved' );
    update_post_meta( $booking_id, '_bh_payment_status', 'pending' );
    wp_redirect( esc_url_raw( $checkout['url'] ) );
    exit;
}

function bubbahub_myhub_booking_payment_url( $booking_id ) {
    return wp_nonce_url( add_query_arg( array( 'bh_booking_payment' => 'pay', 'booking_id' => absint( $booking_id ) ), home_url( '/myhub/' ) ), 'bh_pay_booking_' . absint( $booking_id ) );
}

add_shortcode( 'bubbahub_customer_payment_due', 'bubbahub_customer_payment_due_shortcode' );
function bubbahub_customer_payment_due_shortcode() {
    if ( ! is_user_logged_in() ) return '';
    $ids = get_posts( array(
        'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids',
        'meta_query' => array(
            array( 'key' => '_bh_user_id', 'value' => get_current_user_id() ),
            array( 'key' => '_bh_payment_status', 'value' => array( 'pending', 'failed' ), 'compare' => 'IN' ),
        ), 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true,
    ) );
    $rows = array();
    foreach ( $ids as $id ) {
        $total = (float) get_post_meta( $id, '_bh_total_price', true );
        if ( $total <= 0 ) continue;
        $session_id = absint( get_post_meta( $id, '_bh_session_id', true ) );
        if ( ! $session_id || 'bh_session' !== get_post_type( $session_id ) ) continue;
        $group_id = absint( get_post_meta( $id, '_bh_group_id', true ) );
        $date = get_post_meta( $session_id, '_bh_date', true );
        $time = get_post_meta( $session_id, '_bh_start_time', true );
        $rows[] = array( 'id' => $id, 'title' => $group_id ? get_the_title( $group_id ) : get_the_title( $session_id ), 'date' => $date, 'time' => $time, 'total' => $total, 'status' => get_post_meta( $id, '_bh_payment_status', true ) );
    }
    if ( empty( $rows ) ) return '';
    ob_start(); ?>
    <section class="bh-myhub-section bh-myhub-payment-due">
        <div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">PAYMENT</div><h2>Payments to complete</h2><p>Your place is being held while payment is completed.</p></div></div>
        <div class="bh-myhub-bookings-list">
            <?php foreach ( $rows as $row ) : ?>
                <article class="bh-myhub-booking-card">
                    <div class="bh-myhub-booking-icon" aria-hidden="true">💳</div>
                    <div class="bh-myhub-booking-content">
                        <div class="bh-myhub-eyebrow">Booking #<?php echo absint( $row['id'] ); ?></div>
                        <h3><?php echo esc_html( $row['title'] ); ?></h3>
                        <div class="bh-myhub-booking-date"><?php echo $row['date'] ? esc_html( wp_date( 'D j M Y', strtotime( $row['date'] ) ) ) : ''; ?><?php echo $row['time'] ? ' · ' . esc_html( wp_date( 'g:i A', strtotime( $row['time'] ) ) ) : ''; ?></div>
                        <div class="bh-myhub-booking-meta"><span>£<?php echo number_format( $row['total'], 2 ); ?></span><span><?php echo esc_html( ucfirst( str_replace( '_', ' ', $row['status'] ) ) ); ?></span></div>
                        <div class="bh-myhub-booking-actions"><a class="bh-myhub-button" href="<?php echo esc_url( bubbahub_myhub_booking_payment_url( $row['id'] ) ); ?>">Pay securely with Stripe →</a></div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php return ob_get_clean();
}

add_filter( 'the_content', 'bubbahub_myhub_append_payment_due', 31 );
function bubbahub_myhub_append_payment_due( $content ) {
    if ( is_admin() || ! is_singular() || false === strpos( $content, '[bubbahub_my_hub' ) ) return $content;
    if ( false !== strpos( $content, 'bubbahub_customer_payment_due' ) ) return $content;
    return do_shortcode( '[bubbahub_customer_payment_due]' ) . $content;
}
