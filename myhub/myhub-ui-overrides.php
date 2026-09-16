<?php
/**
 * Bubba Hub My Hub - UI, My Bookings and child-form fixes.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_ui_overrides', 90 );
function bubbahub_myhub_ui_overrides() {
    if ( ! is_user_logged_in() ) return;
    wp_register_style( 'bubbahub-myhub-ui-overrides', false, array(), defined( 'BUBBAHUB_MYHUB_VERSION' ) ? BUBBAHUB_MYHUB_VERSION : '1.6.8' );
    wp_enqueue_style( 'bubbahub-myhub-ui-overrides' );
    wp_add_inline_style( 'bubbahub-myhub-ui-overrides', '\n        .bh-myhub,.bh-profile-shell,.bh-myhub button,.bh-myhub input,.bh-myhub select,.bh-myhub textarea,.bh-profile-shell button,.bh-profile-shell input,.bh-profile-shell select,.bh-profile-shell textarea{font-family:inherit}\n        .bh-profile-shell{max-width:1100px;margin:0 auto;padding:24px 16px 80px;box-sizing:border-box;color:#1e3330}\n        .bh-profile-shell *{box-sizing:border-box}\n        .bh-profile-shell a,.bh-profile-shell a:hover,.bh-profile-shell a:focus,.bh-profile-shell a:visited{ text-decoration:none!important }\n        .bh-profile-shell h1,.bh-profile-shell h2,.bh-profile-shell h3,.bh-profile-shell h4,.bh-profile-shell h5,.bh-profile-shell h6,.bh-profile-shell p,.bh-profile-shell span,.bh-profile-shell label{ text-decoration:none!important }\n        .bh-profile-shell .bh-profile-header{display:flex;justify-content:space-between;gap:28px;align-items:flex-start;background:#fff!important;color:#1e3330!important;border:1px solid #e6ebe9;border-radius:24px;padding:28px;margin:0 0 20px;box-shadow:0 2px 10px rgba(0,0,0,.04)}\n        .bh-profile-shell .bh-profile-header.dark{background:#fff!important;color:#1e3330!important;border-color:#e6ebe9!important;box-shadow:none!important}\n        .bh-profile-shell .bh-profile-header p,.bh-profile-shell .bh-profile-header.dark p{margin:8px 0 0;color:#668785!important;font-size:13px;line-height:1.6}\n        .bh-profile-shell .bh-profile-kicker{display:block;font-size:10px;font-weight:800;letter-spacing:.16em;color:#bc6c25!important;margin-bottom:7px}\n        .bh-profile-shell .bh-profile-header h1,.bh-profile-shell .bh-profile-header h2{margin:0;font-size:28px;line-height:1.2}\n        .bh-profile-shell .bh-profile-back,.bh-profile-shell .bh-profile-back.light{display:inline-flex;align-items:center;color:#668785!important;font-size:12px;font-weight:700;white-space:nowrap}\n        .bh-profile-shell .bh-profile-card{background:#fff;border:1px solid #e6ebe9;border-radius:20px;padding:22px;margin:0 0 16px;box-shadow:0 2px 8px rgba(0,0,0,.025)}\n        .bh-profile-shell .bh-profile-card-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;border-bottom:1px solid #e6ebe9;padding:0 0 13px;margin:0 0 18px}\n        .bh-profile-shell .bh-profile-card-heading h3{margin:0;font-size:17px;line-height:1.3}\n        .bh-profile-shell .bh-profile-card-heading span,.bh-profile-shell .bh-profile-card-heading a{font-size:11px;color:#668785!important}\n        .bh-profile-shell .bh-profile-grid{display:grid!important;gap:16px!important;width:100%}\n        .bh-profile-shell .bh-profile-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))!important}\n        .bh-profile-shell .bh-profile-grid.one{grid-template-columns:minmax(0,1fr)!important}\n        .bh-profile-shell .bh-profile-grid label{display:flex!important;flex-direction:column!important;align-items:stretch!important;gap:7px!important;width:100%!important;font-size:11px;font-weight:700;line-height:1.3}\n        .bh-profile-shell .bh-profile-grid input,.bh-profile-shell .bh-profile-grid select,.bh-profile-shell .bh-profile-grid textarea{display:block!important;width:100%!important;max-width:100%!important;min-height:44px;border:1px solid #dce5e1!important;border-radius:12px!important;background:#fff!important;padding:11px 13px!important;color:#1e3330!important;font-size:13px!important;line-height:1.4!important;box-shadow:none!important}\n        .bh-profile-shell .bh-profile-grid textarea{min-height:110px;resize:vertical}\n        .bh-profile-shell .bh-profile-grid input:focus,.bh-profile-shell .bh-profile-grid select:focus,.bh-profile-shell .bh-profile-grid textarea:focus{outline:2px solid rgba(102,135,133,.18)!important;border-color:#668785!important}\n        .bh-profile-shell .bh-nap-grid{display:grid;gap:8px}\n        .bh-profile-shell .bh-nap-row{display:grid;grid-template-columns:52px 42px minmax(0,1fr) 24px minmax(0,1fr);align-items:center;gap:8px;background:#f8f9f5;border:1px solid #e6ebe9;border-radius:14px;padding:10px}\n        .bh-profile-shell .bh-nap-row strong{font-size:12px}.bh-profile-shell .bh-nap-row>span{font-size:10px;color:#668785;text-align:center}.bh-profile-shell .bh-nap-row input[type=time]{min-width:0;width:100%;border:1px solid #dce5e1;border-radius:9px;padding:7px;background:#fff}\n        .bh-profile-shell .bh-switch input{display:none!important}.bh-profile-shell .bh-switch span{display:block;width:34px;height:20px;background:#cbd7d4;border-radius:20px;position:relative}.bh-profile-shell .bh-switch span:after{content:'';position:absolute;width:16px;height:16px;top:2px;left:2px;border-radius:50%;background:#fff;transition:.15s}.bh-profile-shell .bh-switch input:checked+span{background:#668785}.bh-profile-shell .bh-switch input:checked+span:after{left:16px}\n        .bh-profile-shell .bh-profile-actions{display:flex;justify-content:flex-end;align-items:center;gap:10px;flex-wrap:wrap;margin-top:4px}.bh-profile-shell .bh-profile-actions button{border:0;background:#668785;color:#fff;border-radius:12px;padding:12px 20px;font-weight:800;cursor:pointer;font-size:12px}.bh-profile-shell .bh-profile-actions .bh-profile-delete{background:#fff;color:#8a3c3c;border:1px solid #ecc4c4}\n        .bh-profile-shell .bh-profile-success{background:#e8f4e8;border:1px solid #b8d8c0;color:#315b4f;border-radius:14px;padding:12px 15px;margin-bottom:16px;font-size:12px;font-weight:700}.bh-profile-shell .bh-profile-error{background:#fff0f0;border:1px solid #ecc4c4;color:#8a3c3c;border-radius:14px;padding:12px 15px;margin-bottom:16px;font-size:12px;font-weight:700}\n        .bh-profile-shell .bh-profile-header.dark p,.bh-profile-shell .bh-profile-header.dark .bh-profile-kicker,.bh-profile-shell .bh-profile-header.dark .bh-profile-back.light{color:inherit!important}.bh-profile-shell .bh-pro-pill{color:inherit!important;border-color:#e6ebe9!important;background:#f8f9f5!important}\n        .bh-my-bookings-page{max-width:1100px;margin:0 auto;padding:24px 16px 70px}.bh-my-bookings-page h1{margin:0 0 8px}.bh-my-bookings-intro{margin:0 0 24px;opacity:.78}.bh-my-bookings-list{display:grid;gap:14px}.bh-my-booking{display:flex;justify-content:space-between;gap:20px;align-items:center;padding:20px;background:#fff;border:1px solid #e6ebe9;border-radius:18px}.bh-my-booking-main{min-width:0}.bh-my-booking-title{margin:0 0 5px;font-size:18px}.bh-my-booking-meta{margin:0;font-size:13px;line-height:1.6;opacity:.78}.bh-my-booking-status{display:inline-flex;margin-top:9px;padding:5px 9px;border-radius:999px;background:#edf6f1;font-size:11px;font-weight:700}.bh-my-booking-action{flex:0 0 auto}.bh-my-booking-action a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 15px;border-radius:11px;background:#f2f6f4;text-decoration:none}.bh-my-bookings-empty,.bh-my-bookings-login{padding:28px;border:1px solid #e6ebe9;border-radius:18px;text-align:center}.bh-my-bookings-empty{border-style:dashed;border-color:#cfd8d3}@media(max-width:800px){.bh-profile-shell .bh-profile-grid.two{grid-template-columns:1fr!important}.bh-profile-shell .bh-profile-header{flex-direction:column}.bh-profile-shell .bh-profile-back{white-space:normal}.bh-profile-shell .bh-nap-row{grid-template-columns:44px 38px minmax(0,1fr) 18px minmax(0,1fr)}}@media(max-width:620px){.bh-my-bookings-page{padding-left:12px;padding-right:12px}.bh-my-booking{display:block}.bh-my-booking-action{margin-top:14px}.bh-my-booking-action a{width:100%}}@media(max-width:480px){.bh-profile-shell{padding:16px 10px 60px}.bh-profile-shell .bh-profile-header,.bh-profile-shell .bh-profile-card{padding:18px}.bh-profile-shell .bh-profile-header h1,.bh-profile-shell .bh-profile-header h2{font-size:23px}.bh-profile-shell .bh-nap-row{grid-template-columns:38px 32px minmax(0,1fr) 12px minmax(0,1fr);gap:6px}.bh-profile-shell .bh-nap-row input[type=time]{font-size:11px;padding:6px}}\n    ' );

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
                try {
                    var url = new URL(el.href, window.location.origin);
                    if (url.searchParams.has('bh_add_child')) {
                        url.searchParams.delete('bh_account_settings');
                        el.href = url.toString();
                    }
                } catch (e) {}
            });
        });
    " );
}

/* The stable My Hub dashboard now turns ?bh_add_child=1 into the actual child editor. */
add_action( 'init', 'bubbahub_myhub_child_route_override', 31 );
function bubbahub_myhub_child_route_override() {
    if ( ! function_exists( 'bubbahub_myhub_v3_render' ) ) return;
    remove_shortcode( 'bubbahub_my_hub' );
    remove_shortcode( 'bubbahub-my-hub' );
    add_shortcode( 'bubbahub_my_hub', 'bubbahub_myhub_child_aware_render' );
    add_shortcode( 'bubbahub-my-hub', 'bubbahub_myhub_child_aware_render' );
}
function bubbahub_myhub_child_aware_render() {
    if ( ! empty( $_GET['bh_add_child'] ) && function_exists( 'bubbahub_profile_child_form' ) ) {
        $child_id = isset( $_GET['child_id'] ) ? absint( $_GET['child_id'] ) : 0;
        return bubbahub_profile_child_form( $child_id );
    }
    return bubbahub_myhub_v3_render();
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
    if ( ! empty( $_GET['booking_id'] ) && function_exists( 'bubbahub_stage10_booking_details_shortcode' ) ) return bubbahub_stage10_booking_details_shortcode();
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
