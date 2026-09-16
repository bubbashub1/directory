<?php
/**
 * Bubba Hub My Hub - UI and My Bookings fixes.
 * Keeps WordPress/theme typography and native account styling while providing
 * the user-facing labels and a working /my-bookings/ page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_ui_overrides', 90 );
function bubbahub_myhub_ui_overrides() {
    if ( ! is_user_logged_in() ) return;
    wp_register_style( 'bubbahub-myhub-ui-overrides', false, array(), defined( 'BUBBAHUB_MYHUB_VERSION' ) ? BUBBAHUB_MYHUB_VERSION : '1.6.8' );
    wp_enqueue_style( 'bubbahub-myhub-ui-overrides' );
    wp_add_inline_style( 'bubbahub-myhub-ui-overrides', '
        .bh-myhub,.bh-profile-shell,.bh-myhub button,.bh-myhub input,.bh-myhub select,.bh-myhub textarea,.bh-profile-shell button,.bh-profile-shell input,.bh-profile-shell select,.bh-profile-shell textarea{font-family:inherit}
        .bh-profile-shell .bh-profile-header.dark{background:var(--wp--preset--color--base,#fff)!important;color:inherit!important;border-color:#e6ebe9!important;box-shadow:none!important}
        .bh-profile-shell .bh-profile-header.dark p,.bh-profile-shell .bh-profile-header.dark .bh-profile-kicker,.bh-profile-shell .bh-profile-header.dark .bh-profile-back.light{color:inherit!important}
        .bh-profile-shell .bh-pro-pill{color:inherit!important;border-color:#e6ebe9!important;background:#f8f9f5!important}
        .bh-my-bookings-page{max-width:1100px;margin:0 auto;padding:24px 16px 70px}.bh-my-bookings-page h1{margin:0 0 8px}.bh-my-bookings-intro{margin:0 0 24px;opacity:.78}.bh-my-bookings-list{display:grid;gap:14px}.bh-my-booking{display:flex;justify-content:space-between;gap:20px;align-items:center;padding:20px;background:#fff;border:1px solid #e6ebe9;border-radius:18px}.bh-my-booking-main{min-width:0}.bh-my-booking-title{margin:0 0 5px;font-size:18px}.bh-my-booking-meta{margin:0;font-size:13px;line-height:1.6;opacity:.78}.bh-my-booking-status{display:inline-flex;margin-top:9px;padding:5px 9px;border-radius:999px;background:#edf6f1;font-size:11px;font-weight:700}.bh-my-booking-action{flex:0 0 auto}.bh-my-booking-action a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 15px;border-radius:11px;background:#f2f6f4;text-decoration:none;font-weight:700}.bh-my-bookings-empty,.bh-my-bookings-login{padding:28px;border:1px solid #e6ebe9;border-radius:18px;text-align:center}.bh-my-bookings-empty{border-style:dashed;border-color:#cfd8d3}@media(max-width:620px){.bh-my-bookings-page{padding-left:12px;padding-right:12px}.bh-my-booking{display:block}.bh-my-booking-action{margin-top:14px}.bh-my-booking-action a{width:100%}}
    ' );

    wp_register_script( 'bubbahub-myhub-ui-overrides', false, array(), defined( 'BUBBAHUB_MYHUB_VERSION' ) ? BUBBAHUB_MYHUB_VERSION : '1.6.8', true );
    wp_enqueue_script( 'bubbahub-myhub-ui-overrides' );
    wp_add_inline_script( 'bubbahub-myhub-ui-overrides', "
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.bh-profile-shell .bh-profile-card-heading span').forEach(function (el) {
                if (el.textContent.trim() === 'Ultimate Member / WordPress') el.textContent = 'My Membership';
                if (el.textContent.trim() === 'GetPaid connection') el.textContent = 'Payment Options';
            });
            document.querySelectorAll('.bh-profile-shell a').forEach(function (el) {
                var text = el.textContent.trim();
                if (text === 'Open Ultimate Member →') el.textContent = 'View My Membership →';
                if (text === 'Open payments →') el.textContent = 'Payment Options →';
            });
        });
    " );
}

add_shortcode( 'bubbahub_my_bookings', 'bubbahub_my_bookings_shortcode' );
add_action( 'init', 'bubbahub_myhub_register_bookings_page', 40 );

function bubbahub_myhub_register_bookings_page() {
    if ( get_page_by_path( 'my-bookings' ) ) return;
    wp_insert_post( array(
        'post_title'   => 'My Bookings',
        'post_name'    => 'my-bookings',
        'post_content' => '[bubbahub_my_bookings]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ) );
}

function bubbahub_myhub_booking_items() {
    if ( ! is_user_logged_in() ) return array();
    $ids = get_posts( array(
        'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
        'meta_query' => array(
            array( 'key' => '_bh_user_id', 'value' => get_current_user_id(), 'compare' => '=' ),
            array( 'key' => '_bh_status', 'value' => array( 'confirmed', 'reserved' ), 'compare' => 'IN' ),
        ),
    ) );
    $items = array();
    foreach ( $ids as $booking_id ) {
        $session_id = absint( get_post_meta( $booking_id, '_bh_session_id', true ) );
        if ( ! $session_id || 'bh_session' !== get_post_type( $session_id ) ) continue;
        $date = sanitize_text_field( get_post_meta( $session_id, '_bh_date', true ) );
        $start = sanitize_text_field( get_post_meta( $session_id, '_bh_start_time', true ) );
        $stamp = strtotime( trim( $date . ' ' . $start ) );
        if ( ! $stamp ) continue;
        $group_id = absint( get_post_meta( $session_id, '_bh_group_id', true ) );
        $venue_id = absint( get_post_meta( $session_id, '_bh_venue_id', true ) );
        $items[] = array(
            'id' => $booking_id, 'session' => $session_id, 'title' => get_the_title( $session_id ), 'date' => $date, 'start' => $start, 'stamp' => $stamp,
            'group' => $group_id ? get_the_title( $group_id ) : '', 'venue' => $venue_id ? get_the_title( $venue_id ) : '',
            'status' => sanitize_key( get_post_meta( $booking_id, '_bh_status', true ) ),
        );
    }
    usort( $items, function( $a, $b ) { return $a['stamp'] <=> $b['stamp']; } );
    return $items;
}

function bubbahub_my_bookings_shortcode() {
    if ( ! is_user_logged_in() ) return '<div class="bh-my-bookings-page"><div class="bh-my-bookings-login"><h2>Please log in</h2><p>Log in to view your bookings.</p></div></div>';

    /* Stage 10 already contains the secure booking-detail view. */
    if ( ! empty( $_GET['booking_id'] ) && function_exists( 'bubbahub_stage10_booking_details_shortcode' ) ) {
        return bubbahub_stage10_booking_details_shortcode();
    }

    $items = bubbahub_myhub_booking_items();
    ob_start(); ?>
    <main class="bh-my-bookings-page">
        <h1>My Bookings</h1>
        <p class="bh-my-bookings-intro">View your upcoming Bubba Hub bookings and open the full booking details.</p>
        <?php if ( empty( $items ) ) : ?>
            <div class="bh-my-bookings-empty"><h2>No upcoming bookings</h2><p>When you book a class or group, it will appear here.</p><a href="<?php echo esc_url( home_url( '/directory/' ) ); ?>">Browse groups →</a></div>
        <?php else : ?>
            <div class="bh-my-bookings-list">
                <?php foreach ( $items as $booking ) :
                    $details_url = add_query_arg( 'booking_id', absint( $booking['id'] ), home_url( '/my-bookings/' ) );
                    $date_label = wp_date( 'D j M Y', $booking['stamp'] );
                    $time_label = $booking['start'] ? wp_date( 'g:i A', strtotime( $booking['start'] ) ) : '';
                    ?>
                    <article class="bh-my-booking">
                        <div class="bh-my-booking-main">
                            <h2 class="bh-my-booking-title"><?php echo esc_html( $booking['title'] ?: $booking['group'] ?: 'Booked session' ); ?></h2>
                            <p class="bh-my-booking-meta"><?php echo esc_html( $date_label ); ?><?php if ( $time_label ) : ?> · <?php echo esc_html( $time_label ); ?><?php endif; ?><?php if ( $booking['venue'] ) : ?> · <?php echo esc_html( $booking['venue'] ); ?><?php endif; ?></p>
                            <?php if ( $booking['group'] && $booking['group'] !== $booking['title'] ) : ?><p class="bh-my-booking-meta"><?php echo esc_html( $booking['group'] ); ?></p><?php endif; ?>
                            <span class="bh-my-booking-status"><?php echo esc_html( 'confirmed' === $booking['status'] ? 'Confirmed' : 'Reserved' ); ?></span>
                        </div>
                        <div class="bh-my-booking-action"><a href="<?php echo esc_url( $details_url ); ?>">View booking →</a></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    <?php return ob_get_clean();
}

add_filter( 'the_content', 'bubbahub_myhub_bookings_content_fallback', 30 );
function bubbahub_myhub_bookings_content_fallback( $content ) {
    if ( is_admin() || ! is_singular( 'page' ) || ! is_page( 'my-bookings' ) || ! in_the_loop() || ! is_main_query() ) return $content;
    if ( has_shortcode( $content, 'bubbahub_my_bookings' ) || has_shortcode( $content, 'bubbahub_booking_details' ) ) return $content;
    return $content . do_shortcode( '[bubbahub_my_bookings]' );
}
