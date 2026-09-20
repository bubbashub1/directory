<?php
/**
 * Bubba Hub My Hub - stable UI and bookings integration.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_ui_overrides_stable', 90 );
function bubbahub_myhub_ui_overrides_stable() {
    if ( ! is_user_logged_in() ) return;
    $version = defined( 'BUBBAHUB_MYHUB_VERSION' ) ? BUBBAHUB_MYHUB_VERSION : '1.6.11';
    wp_register_style( 'bubbahub-myhub-ui-overrides-stable', false, array(), $version );
    wp_enqueue_style( 'bubbahub-myhub-ui-overrides-stable' );
    $css = <<<'CSS'
.bh-profile-shell,.bh-profile-shell *{box-sizing:border-box}.bh-profile-shell{max-width:1100px;margin:0 auto;padding:24px 16px 70px;color:#1e3330}.bh-profile-shell a{text-decoration:none!important}.bh-profile-shell .bh-profile-header{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;background:#fff!important;color:#1e3330!important;border:1px solid #e6ebe9;border-radius:24px;padding:28px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,.04)}.bh-profile-shell .bh-profile-header.dark{background:#1e3330!important;color:#fff!important;border-color:#2d4743!important}.bh-profile-shell .bh-profile-header.dark p{color:#a8c2bc!important}.bh-profile-shell .bh-profile-header p{color:#668785!important;font-size:13px;line-height:1.6}.bh-profile-shell .bh-profile-card{background:#fff;border:1px solid #e6ebe9;border-radius:20px;padding:22px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,.025)}.bh-profile-shell .bh-profile-grid{display:grid!important;gap:16px!important;width:100%}.bh-profile-shell .bh-profile-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))!important}.bh-profile-shell .bh-profile-grid.one{grid-template-columns:1fr!important}.bh-profile-shell .bh-profile-grid input,.bh-profile-shell .bh-profile-grid select,.bh-profile-shell .bh-profile-grid textarea{width:100%!important;max-width:100%!important;min-height:44px;border:1px solid #dce5e1!important;border-radius:12px!important;background:#fff!important;padding:11px 13px!important;color:#1e3330!important}.bh-profile-shell input[type=file]{padding:9px!important;background:#f8fbfa!important}.bh-myhub-avatar img{width:100%;height:100%;object-fit:cover;border-radius:inherit;display:block}.bh-myhub-child-specialist{font-weight:700}.bh-my-bookings-page{max-width:1100px;margin:0 auto;padding:24px 16px 70px}.bh-my-bookings-list{display:grid;gap:14px}.bh-my-booking{display:flex;justify-content:space-between;gap:20px;align-items:center;padding:20px;background:#fff;border:1px solid #e6ebe9;border-radius:18px}.bh-my-booking-title{margin:0 0 5px;font-size:18px}.bh-my-booking-meta{margin:0;font-size:13px;line-height:1.6;opacity:.78}.bh-my-booking-status{display:inline-flex;margin-top:9px;padding:5px 9px;border-radius:999px;background:#edf6f1;font-size:11px;font-weight:700}.bh-my-booking-action a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:9px 15px;border-radius:11px;background:#f2f6f4}.bh-my-bookings-empty,.bh-my-bookings-login{padding:28px;border:1px solid #e6ebe9;border-radius:18px;text-align:center}.bh-my-bookings-empty{border-style:dashed}@media(max-width:800px){.bh-profile-shell .bh-profile-grid.two{grid-template-columns:1fr!important}.bh-profile-shell .bh-profile-header{flex-direction:column}}@media(max-width:620px){.bh-my-booking{display:block}.bh-my-booking-action{margin-top:14px}.bh-my-booking-action a{width:100%}.bh-my-bookings-page{padding-left:12px;padding-right:12px}}@media(max-width:480px){.bh-profile-shell{padding:16px 10px 60px}.bh-profile-shell .bh-profile-header,.bh-profile-shell .bh-profile-card{padding:18px}}
CSS;
    wp_add_inline_style( 'bubbahub-myhub-ui-overrides-stable', $css );
}

add_action( 'init', 'bubbahub_myhub_child_route_override_stable', 31 );
function bubbahub_myhub_child_route_override_stable() {
    if ( ! function_exists( 'bubbahub_myhub_v3_render' ) ) return;
    remove_shortcode( 'bubbahub_my_hub' );
    remove_shortcode( 'bubbahub-my-hub' );
    add_shortcode( 'bubbahub_my_hub', 'bubbahub_myhub_child_aware_render_stable' );
    add_shortcode( 'bubbahub-my-hub', 'bubbahub_myhub_child_aware_render_stable' );
}
function bubbahub_myhub_child_aware_render_stable() {
    if ( ! empty( $_GET['bh_add_child'] ) && function_exists( 'bubbahub_profile_child_form' ) ) {
        $child_id = isset( $_GET['child_id'] ) ? absint( $_GET['child_id'] ) : 0;
        return bubbahub_profile_child_form( $child_id );
    }
    return bubbahub_myhub_v3_render();
}

add_shortcode( 'bubbahub_my_bookings', 'bubbahub_my_bookings_shortcode_stable' );
add_action( 'init', 'bubbahub_myhub_register_bookings_page_stable', 40 );
function bubbahub_myhub_register_bookings_page_stable() {
    if ( get_page_by_path( 'my-bookings' ) ) return;
    wp_insert_post( array( 'post_title' => 'My Bookings', 'post_name' => 'my-bookings', 'post_content' => '[bubbahub_my_bookings]', 'post_status' => 'publish', 'post_type' => 'page' ) );
}
function bubbahub_myhub_booking_items_stable() {
    if ( ! is_user_logged_in() ) return array();
    $ids = get_posts( array('post_type'=>'bh_booking','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true,'meta_query'=>array(array('key'=>'_bh_user_id','value'=>get_current_user_id(),'compare'=>'='),array('key'=>'_bh_status','value'=>array('confirmed','reserved'),'compare'=>'IN'))) );
    $items = array();
    foreach ( $ids as $booking_id ) {
        $session_id = absint( get_post_meta( $booking_id, '_bh_session_id', true ) );
        if ( ! $session_id || 'bh_session' !== get_post_type( $session_id ) ) continue;
        $date = sanitize_text_field( get_post_meta( $session_id, '_bh_date', true ) );
        $start = sanitize_text_field( get_post_meta( $session_id, '_bh_start_time', true ) );
        $stamp = strtotime( trim( $date . ' ' . $start ) );
        if ( ! $stamp || $stamp < current_time( 'timestamp' ) ) continue;
        $group_id = absint( get_post_meta( $session_id, '_bh_group_id', true ) );
        $venue_id = absint( get_post_meta( $session_id, '_bh_venue_id', true ) );
        $items[] = array('id'=>$booking_id,'session'=>$session_id,'title'=>get_the_title($session_id),'date'=>$date,'start'=>$start,'stamp'=>$stamp,'group'=>$group_id?get_the_title($group_id):'','venue'=>$venue_id?get_the_title($venue_id):'','status'=>sanitize_key(get_post_meta($booking_id,'_bh_status',true)));
    }
    usort( $items, function( $a, $b ) { return $a['stamp'] <=> $b['stamp']; } );
    return $items;
}
function bubbahub_my_bookings_shortcode_stable() {
    if ( ! is_user_logged_in() ) return '<div class="bh-my-bookings-page"><div class="bh-my-bookings-login"><h2>Please log in</h2><p>Log in to view your bookings.</p></div></div>';
    if ( ! empty( $_GET['booking_id'] ) && function_exists( 'bubbahub_stage10_booking_details_shortcode' ) ) return bubbahub_stage10_booking_details_shortcode();
    $items = bubbahub_myhub_booking_items_stable();
    ob_start(); ?>
    <main class="bh-my-bookings-page"><h1>My Bookings</h1><p>View your upcoming Bubba Hub bookings and open the full booking details.</p>
    <?php if ( empty( $items ) ) : ?>
        <div class="bh-my-bookings-empty"><h2>No upcoming bookings</h2><p>When you book a class or group, it will appear here.</p><a href="<?php echo esc_url( home_url( '/directory/' ) ); ?>">Browse groups →</a></div>
    <?php else : ?><div class="bh-my-bookings-list">
        <?php foreach ( $items as $booking ) : $details_url = add_query_arg( 'booking_id', absint($booking['id']), home_url('/my-bookings/') ); ?>
        <article class="bh-my-booking"><div><h2 class="bh-my-booking-title"><?php echo esc_html($booking['title']?:$booking['group']?:'Booked session'); ?></h2><p class="bh-my-booking-meta"><?php echo esc_html(wp_date('D j M Y',$booking['stamp'])); ?><?php if($booking['start']): ?> · <?php echo esc_html(wp_date('g:i A',strtotime($booking['start']))); ?><?php endif; ?><?php if($booking['venue']): ?> · <?php echo esc_html($booking['venue']); ?><?php endif; ?></p><span class="bh-my-booking-status"><?php echo esc_html('confirmed'===$booking['status']?'Confirmed':'Reserved'); ?></span></div><div class="bh-my-booking-action"><a href="<?php echo esc_url($details_url); ?>">View booking →</a></div></article>
        <?php endforeach; ?></div><?php endif; ?></main>
    <?php return ob_get_clean();
}
add_filter( 'the_content', 'bubbahub_myhub_bookings_content_fallback_stable', 30 );
function bubbahub_myhub_bookings_content_fallback_stable( $content ) {
    if ( is_admin() || ! is_singular('page') || ! is_page('my-bookings') || ! in_the_loop() || ! is_main_query() ) return $content;
    if ( has_shortcode($content,'bubbahub_my_bookings') || has_shortcode($content,'bubbahub_booking_details') ) return $content;
    return $content . do_shortcode('[bubbahub_my_bookings]');
}
