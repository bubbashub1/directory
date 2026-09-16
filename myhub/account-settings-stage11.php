<?php
/**
 * Bubba Hub My Hub - Stage 11.
 * Adds a safe cancellation-request workflow without changing the booking engine status.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_post_bubbahub_stage11_cancel_request', 'bubbahub_stage11_cancel_request' );
add_shortcode( 'bubbahub_booking_cancellation', 'bubbahub_stage11_cancellation_shortcode' );

function bubbahub_stage11_owner( $booking_id ) {
    return is_user_logged_in()
        && 'bh_booking' === get_post_type( $booking_id )
        && absint( get_post_meta( $booking_id, '_bh_user_id', true ) ) === get_current_user_id();
}

function bubbahub_stage11_request_key( $booking_id ) {
    return 'bubbahub_cancel_request_' . absint( $booking_id );
}

function bubbahub_stage11_get_request( $booking_id ) {
    $value = get_post_meta( $booking_id, bubbahub_stage11_request_key( $booking_id ), true );
    return is_array( $value ) ? $value : array();
}

function bubbahub_stage11_cancel_request() {
    if ( ! is_user_logged_in() ) wp_die( 'Please log in.', 'Bubba Hub', array( 'response' => 401 ) );

    $booking_id = absint( $_POST['booking_id'] ?? 0 );
    if ( ! $booking_id || ! bubbahub_stage11_owner( $booking_id ) ) {
        wp_die( 'Booking not found.', 'Bubba Hub', array( 'response' => 404 ) );
    }

    check_admin_referer( 'bubbahub_stage11_cancel_' . $booking_id );

    $status = sanitize_key( get_post_meta( $booking_id, '_bh_status', true ) );
    if ( ! in_array( $status, array( 'confirmed', 'reserved' ), true ) ) {
        wp_safe_redirect( add_query_arg( 'bh_cancel_error', 'status', wp_get_referer() ?: home_url( '/my-bookings/' ) ) );
        exit;
    }

    $existing = bubbahub_stage11_get_request( $booking_id );
    if ( ! empty( $existing['status'] ) && 'requested' === $existing['status'] ) {
        wp_safe_redirect( add_query_arg( 'bh_cancel', 'already_requested', wp_get_referer() ?: home_url( '/my-bookings/' ) ) );
        exit;
    }

    $reason = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
    $user = wp_get_current_user();
    $request = array(
        'status'    => 'requested',
        'booking_id'=> $booking_id,
        'user_id'   => get_current_user_id(),
        'name'      => sanitize_text_field( $user->display_name ),
        'email'     => sanitize_email( $user->user_email ),
        'reason'    => $reason,
        'requested' => current_time( 'mysql' ),
    );

    update_post_meta( $booking_id, bubbahub_stage11_request_key( $booking_id ), $request );

    $subject = 'Bubba Hub cancellation request #' . $booking_id;
    $message = "A cancellation request has been submitted.\n\nBooking: #{$booking_id}\nName: {$request['name']}\nEmail: {$request['email']}\nReason: " . ( $reason ?: 'No reason supplied.' );
    wp_mail( get_option( 'admin_email' ), $subject, $message, array( 'Reply-To: ' . $request['email'] ) );

    wp_safe_redirect( add_query_arg( 'bh_cancel', 'requested', wp_get_referer() ?: home_url( '/my-bookings/' ) ) );
    exit;
}

function bubbahub_stage11_cancellation_shortcode() {
    if ( ! is_user_logged_in() ) return '<div class="bh-stage11-message">Please log in to manage a booking.</div>';

    $booking_id = absint( $_GET['booking_id'] ?? 0 );
    if ( ! $booking_id || ! bubbahub_stage11_owner( $booking_id ) ) {
        return '<div class="bh-stage11-message">Booking not found.</div>';
    }

    $request = bubbahub_stage11_get_request( $booking_id );
    ob_start();
    ?>
    <div class="bh-stage11-cancel">
        <?php if ( ! empty( $request['status'] ) && 'requested' === $request['status'] ) : ?>
            <div class="bh-stage11-message success"><strong>Cancellation requested</strong><br>Your request has been sent to Bubba Hub. Your booking has not been cancelled automatically.</div>
        <?php else : ?>
            <h3>Request cancellation</h3>
            <p>If you can no longer attend, send a cancellation request to Bubba Hub. We will review it and update the booking separately.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="bubbahub_stage11_cancel_request">
                <input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">
                <?php wp_nonce_field( 'bubbahub_stage11_cancel_' . $booking_id ); ?>
                <label for="bh-stage11-reason">Reason (optional)</label>
                <textarea id="bh-stage11-reason" name="reason" rows="4" maxlength="1000" placeholder="Tell us anything that may help..."></textarea>
                <button type="submit">Send cancellation request</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function bubbahub_stage11_render_actions( $booking_id ) {
    if ( ! bubbahub_stage11_owner( $booking_id ) ) return '';
    $request = bubbahub_stage11_get_request( $booking_id );
    if ( ! empty( $request['status'] ) && 'requested' === $request['status'] ) {
        return '<span class="bh-stage11-requested">Cancellation requested</span>';
    }
    $url = add_query_arg( 'booking_id', absint( $booking_id ), get_permalink() ?: home_url( '/' ) );
    return '<a href="' . esc_url( $url ) . '#bubbahub-cancellation">Request cancellation</a>';
}

add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_user_logged_in() ) return;
    wp_register_style( 'bubbahub-myhub-stage11', false, array(), defined( 'BUBBAHUB_MYHUB_VERSION' ) ? BUBBAHUB_MYHUB_VERSION : '1.6.7' );
    wp_enqueue_style( 'bubbahub-myhub-stage11' );
    wp_add_inline_style( 'bubbahub-myhub-stage11', '.bh-stage11-cancel{margin-top:18px;padding:16px;border:1px solid #e4ece8;border-radius:15px;background:#fbfcfb}.bh-stage11-cancel h3{margin:0 0 7px;color:#263f39;font-size:16px}.bh-stage11-cancel p,.bh-stage11-message{font-size:12px;line-height:1.55;color:#647873}.bh-stage11-cancel label{display:block;font-size:11px;font-weight:700;color:#536d67;margin:12px 0 5px}.bh-stage11-cancel textarea{width:100%;box-sizing:border-box;border:1px solid #dbe6e1;border-radius:10px;padding:10px;font:inherit;resize:vertical}.bh-stage11-cancel button{margin-top:10px;border:0;border-radius:10px;padding:10px 14px;background:#315b4f;color:#fff;font-weight:700;cursor:pointer}.bh-stage11-message.success{padding:13px;border-radius:12px;background:#edf6f1;color:#315b4f}.bh-stage11-requested{display:inline-block;padding:9px 12px;border-radius:10px;background:#f4f0df;color:#75652d;font-size:11px;font-weight:700}' );
} );
