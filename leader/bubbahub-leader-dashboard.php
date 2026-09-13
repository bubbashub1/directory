<?php
/**
 * Plugin Name: BubbaHub Leader Dashboard
 * Description: Front-end dashboard for BubbaHub leaders and leaderpro users.
 * Version: 1.1.0
 * Author: BubbaHub
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BUBBAHUB_LEADER_DASHBOARD_VERSION', '1.1.0' );
define( 'BUBBAHUB_LEADER_DASHBOARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUBBAHUB_LEADER_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/leader-dashboard-loader.php';

register_activation_hook( __FILE__, 'bubbahub_leader_dashboard_activate' );
function bubbahub_leader_dashboard_activate() {
    $page = get_page_by_path( 'leader' );
    if ( ! $page ) {
        $page_id = wp_insert_post( array( 'post_title'=>'Leader Dashboard', 'post_name'=>'leader', 'post_content'=>'[bubbahub_leader_dashboard]', 'post_status'=>'publish', 'post_type'=>'page' ) );
        if ( $page_id && ! is_wp_error( $page_id ) ) update_option( 'bubbahub_leader_dashboard_page_id', (int) $page_id );
    } else update_option( 'bubbahub_leader_dashboard_page_id', (int) $page->ID );
    flush_rewrite_rules();
}

add_action( 'wp_enqueue_scripts', 'bubbahub_leader_dashboard_assets' );
function bubbahub_leader_dashboard_assets() {
    if ( ! is_page( 'leader' ) ) return;
    $css = BUBBAHUB_LEADER_DASHBOARD_DIR . 'leader-dashboard.css';
    if ( file_exists( $css ) ) wp_enqueue_style( 'bubbahub-leader-dashboard', BUBBAHUB_LEADER_DASHBOARD_URL . 'leader-dashboard.css', array(), BUBBAHUB_LEADER_DASHBOARD_VERSION );
}

add_shortcode( 'bubbahub_leader_dashboard', 'bubbahub_leader_dashboard_shortcode' );
function bubbahub_leader_dashboard_is_allowed() {
    if ( ! is_user_logged_in() ) return false;
    if ( current_user_can( 'manage_options' ) ) return true;
    $user = wp_get_current_user();
    return (bool) array_intersect( array( 'leader', 'leaderpro' ), (array) $user->roles );
}

function bubbahub_leader_dashboard_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div class="bh-leader-message"><div class="bh-leader-message-card"><span class="bh-leader-message-icon">🔐</span><h2>Leader Dashboard</h2><p>Please log in to access your BubbaHub leader dashboard.</p><a class="bh-leader-button" href="' . esc_url( wp_login_url( home_url( '/leader/' ) ) ) . '">Log in</a></div></div>';
    }
    if ( ! bubbahub_leader_dashboard_is_allowed() ) {
        return '<div class="bh-leader-message"><div class="bh-leader-message-card"><span class="bh-leader-message-icon">🔒</span><h2>Leader Dashboard</h2><p>This area is available to approved BubbaHub leaders.</p><a class="bh-leader-button" href="' . esc_url( home_url( '/' ) ) . '">Return to BubbaHub</a></div></div>';
    }

    $user = wp_get_current_user();
    $name = $user->first_name ? $user->first_name : $user->display_name;
    $groups = get_posts( array( 'post_type'=>'group', 'post_status'=>array('publish','draft','pending','private'), 'author'=>$user->ID, 'posts_per_page'=>-1, 'fields'=>'ids', 'no_found_rows'=>true ) );
    $venues = post_type_exists('venue') ? get_posts( array( 'post_type'=>'venue', 'post_status'=>array('publish','draft','pending','private'), 'author'=>$user->ID, 'posts_per_page'=>-1, 'fields'=>'ids', 'no_found_rows'=>true ) ) : array();
    $booking_ids = function_exists('bubbahub_leader_booking_ids') ? bubbahub_leader_booking_ids() : array();
    $published_groups = array_filter( $groups, function($id){ return 'publish' === get_post_status($id); } );
    $published_venues = array_filter( $venues, function($id){ return 'publish' === get_post_status($id); } );
    $dashboard_url = home_url( '/leader/' );
    $logout_url = wp_logout_url( $dashboard_url );
    $edit_listing = isset($_GET['edit_listing']) ? absint($_GET['edit_listing']) : 0;
    $edit_venue = isset($_GET['edit_venue']) ? absint($_GET['edit_venue']) : 0;
    $edit_booking = isset($_GET['edit_booking']) ? absint($_GET['edit_booking']) : 0;

    ob_start(); ?>
    <div class="bh-leader-dashboard">
      <div class="bh-leader-shell">
        <aside class="bh-leader-sidebar">
          <div class="bh-leader-brand"><span class="bh-leader-brand-main">BubbaHub</span><span class="bh-leader-brand-sub">Leader</span></div>
          <nav class="bh-leader-nav" aria-label="Leader dashboard">
            <a class="is-active" href="<?php echo esc_url($dashboard_url); ?>"><span>⌂</span> Dashboard</a>
            <a href="#listings"><span>▦</span> My Listings</a>
            <a href="#venues"><span>⌂</span> My Venues</a>
            <a href="#sessions"><span>◷</span> Sessions &amp; Dates</a>
            <a href="#bookings"><span>▣</span> Bookings</a>
            <a href="#payments"><span>£</span> Payments</a>
            <a href="#earnings"><span>↗</span> Earnings</a>
            <a href="#account"><span>⚙</span> Account</a>
          </nav>
          <div class="bh-leader-sidebar-footer"><a href="<?php echo esc_url($logout_url); ?>">Log out</a></div>
        </aside>

        <main class="bh-leader-main">
          <header class="bh-leader-header">
            <div><p class="bh-leader-eyebrow">Leader area</p><h1>Welcome back, <?php echo esc_html($name); ?></h1><p>Manage your BubbaHub listings, venues, sessions and bookings from one place.</p></div>
            <a class="bh-leader-primary" href="#listings">Manage my listings <span aria-hidden="true">→</span></a>
          </header>

          <section class="bh-leader-stats" aria-label="Dashboard overview">
            <article class="bh-leader-stat"><span class="bh-stat-icon">▦</span><div><strong><?php echo count($groups); ?></strong><span>My Listings</span></div></article>
            <article class="bh-leader-stat"><span class="bh-stat-icon">⌂</span><div><strong><?php echo count($venues); ?></strong><span>My Venues</span></div></article>
            <article class="bh-leader-stat"><span class="bh-stat-icon">▣</span><div><strong><?php echo count($booking_ids); ?></strong><span>Bookings</span></div></article>
            <article class="bh-leader-stat"><span class="bh-stat-icon">£</span><div><strong>£0.00</strong><span>Earnings</span></div></article>
          </section>

          <section class="bh-leader-content-grid">
            <article class="bh-leader-panel bh-leader-panel-wide" id="listings">
              <div class="bh-panel-heading"><div><p>Listings</p><h2>My Listings</h2></div><span class="bh-panel-count"><?php echo count($published_groups); ?> live</span></div>
              <div class="bh-management-toolbar"><a class="bh-leader-button" href="<?php echo esc_url(add_query_arg('new_listing','1',$dashboard_url)); ?>#listings">+ Add listing</a></div>
              <?php if($edit_listing && bubbahub_leader_owned_post($edit_listing,'group')): ?><div class="bh-management-form"><h3>Edit listing</h3><?php bubbahub_leader_listing_form($edit_listing); ?></div>
              <?php elseif(isset($_GET['new_listing'])): ?><div class="bh-management-form"><h3>Add listing</h3><?php bubbahub_leader_listing_form(); ?></div>
              <?php endif; ?>
              <?php if($groups): ?><div class="bh-group-list"><?php foreach(array_slice($groups,0,10) as $id): ?><div class="bh-group-row"><div class="bh-group-avatar"><?php echo esc_html(strtoupper(substr(get_the_title($id),0,1))); ?></div><div class="bh-group-info"><strong><?php echo esc_html(get_the_title($id)); ?></strong><span><?php echo esc_html(ucfirst(get_post_status($id))); ?></span></div><a href="<?php echo esc_url(add_query_arg('edit_listing',$id,$dashboard_url)); ?>#listings">Edit <span>→</span></a></div><?php endforeach; ?></div><?php else: ?><div class="bh-empty-dashboard"><div class="bh-empty-icon">+</div><h3>Create your first listing</h3><p>Add your first group listing from the button above.</p></div><?php endif; ?>
            </article>

            <article class="bh-leader-panel bh-leader-panel-wide" id="venues">
              <div class="bh-panel-heading"><div><p>Locations</p><h2>My Venues</h2></div><span class="bh-panel-count"><?php echo count($published_venues); ?> live</span></div>
              <div class="bh-management-toolbar"><a class="bh-leader-button" href="<?php echo esc_url(add_query_arg('new_venue','1',$dashboard_url)); ?>#venues">+ Add venue</a></div>
              <?php if($edit_venue && bubbahub_leader_owned_post($edit_venue,'venue')): ?><div class="bh-management-form"><h3>Edit venue</h3><?php bubbahub_leader_venue_form($edit_venue); ?></div>
              <?php elseif(isset($_GET['new_venue'])): ?><div class="bh-management-form"><h3>Add venue</h3><?php bubbahub_leader_venue_form(); ?></div>
              <?php endif; ?>
              <?php if($venues): ?><div class="bh-group-list"><?php foreach(array_slice($venues,0,10) as $id): ?><div class="bh-group-row"><div class="bh-group-avatar">⌂</div><div class="bh-group-info"><strong><?php echo esc_html(get_the_title($id)); ?></strong><span><?php echo esc_html(ucfirst(get_post_status($id))); ?></span></div><a href="<?php echo esc_url(add_query_arg('edit_venue',$id,$dashboard_url)); ?>#venues">Edit <span>→</span></a></div><?php endforeach; ?></div><?php else: ?><div class="bh-empty-dashboard"><div class="bh-empty-icon">+</div><h3>Add your first venue</h3><p>Venues you own will appear here.</p></div><?php endif; ?>
            </article>

            <article class="bh-leader-panel" id="sessions"><div class="bh-panel-heading"><div><p>Schedule</p><h2>Sessions &amp; Dates</h2></div></div><div class="bh-coming"><span>◷</span><div><strong>Session management</strong><p>Your dates, availability, capacity and ticket settings will appear here.</p></div></div></article>

            <article class="bh-leader-panel" id="bookings">
              <div class="bh-panel-heading"><div><p>Customers</p><h2>Bookings</h2></div><span class="bh-panel-count"><?php echo count($booking_ids); ?></span></div>
              <div class="bh-management-toolbar"><a class="bh-leader-button" href="<?php echo esc_url(add_query_arg('new_booking','1',$dashboard_url)); ?>#bookings">+ Add booking</a></div>
              <?php if($edit_booking && in_array($edit_booking,$booking_ids,true)): ?><div class="bh-management-form"><h3>Edit booking</h3><?php bubbahub_leader_booking_form($edit_booking); ?></div><?php elseif(isset($_GET['new_booking'])): ?><div class="bh-management-form"><h3>Add booking</h3><?php bubbahub_leader_booking_form(); ?></div><?php endif; ?>
              <?php if($booking_ids): ?><div class="bh-group-list"><?php foreach(array_slice($booking_ids,0,10) as $bid): $title=get_the_title($bid); ?><div class="bh-group-row"><div class="bh-group-avatar">▣</div><div class="bh-group-info"><strong><?php echo esc_html($title); ?></strong><span><?php echo esc_html(ucfirst(get_post_meta($bid,'_bh_status',true) ?: 'pending')); ?></span></div><a href="<?php echo esc_url(add_query_arg('edit_booking',$bid,$dashboard_url)); ?>#bookings">Edit <span>→</span></a></div><?php endforeach; ?></div><?php else: ?><div class="bh-coming"><span>▣</span><div><strong>No bookings yet</strong><p>Book Now and Reserve Spot bookings will appear here.</p></div></div><?php endif; ?>
            </article>

            <article class="bh-leader-panel" id="payments"><div class="bh-panel-heading"><div><p>Payments</p><h2>Payment Accounts</h2></div></div><div class="bh-payment-card"><span class="bh-payment-logo">S</span><div><strong>Stripe</strong><span>Connect your Stripe account</span></div><span class="bh-payment-status">Next stage</span></div><div class="bh-payment-card"><span class="bh-payment-logo">P</span><div><strong>PayPal</strong><span>Connect your PayPal account</span></div><span class="bh-payment-status">Next stage</span></div><p class="bh-panel-note">Your own Stripe or PayPal account will be connected here.</p></article>
            <article class="bh-leader-panel" id="earnings"><div class="bh-panel-heading"><div><p>Finance</p><h2>Earnings</h2></div></div><div class="bh-coming"><span>£</span><div><strong>Leader earnings</strong><p>Wallet balance, transactions, fees and transfers will be added here.</p></div></div></article>
            <article class="bh-leader-panel" id="account"><div class="bh-panel-heading"><div><p>Your profile</p><h2>Account</h2></div></div><div class="bh-coming"><span>⚙</span><div><strong>Account settings</strong><p>Your leader profile and account settings will be managed here.</p></div></div></article>
          </section>
        </main>
      </div>
    </div>
    <?php return ob_get_clean();
}
