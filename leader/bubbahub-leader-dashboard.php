<?php
/**
 * Plugin Name: BubbaHub Leader Dashboard
 * Description: Front-end dashboard for BubbaHub leaders and leaderpro users.
 * Version: 1.3.2
 * Author: BubbaHub
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BUBBAHUB_LEADER_DASHBOARD_VERSION' ) ) define( 'BUBBAHUB_LEADER_DASHBOARD_VERSION', '1.3.2' );
if ( ! defined( 'BUBBAHUB_LEADER_DASHBOARD_DIR' ) ) define( 'BUBBAHUB_LEADER_DASHBOARD_DIR', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'BUBBAHUB_LEADER_DASHBOARD_URL' ) ) define( 'BUBBAHUB_LEADER_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/leader-dashboard-loader.php';

register_activation_hook( __FILE__, 'bubbahub_leader_dashboard_activate' );
function bubbahub_leader_dashboard_activate() {
    $page = get_page_by_path( 'leader' );
    if ( ! $page ) {
        $page_id = wp_insert_post( array(
            'post_title'   => 'Leader Dashboard',
            'post_name'    => 'leader',
            'post_content' => '[bubbahub_leader_dashboard]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'bubbahub_leader_dashboard_page_id', (int) $page_id );
        }
    } else {
        update_option( 'bubbahub_leader_dashboard_page_id', (int) $page->ID );
    }
    flush_rewrite_rules();
}

add_action( 'wp_enqueue_scripts', 'bubbahub_leader_dashboard_assets' );
function bubbahub_leader_dashboard_assets() {
    $dashboard_id  = (int) get_option( 'bubbahub_leader_dashboard_page_id', 0 );
    $management_ids = function_exists( 'bubbahub_leader_management_page_ids' ) ? bubbahub_leader_management_page_ids() : array();
    $allowed_ids = array_filter( array_merge( array( $dashboard_id ), array_values( $management_ids ) ) );

    // When the Leader Portal is bundled with the main Directory plugin,
    // its standalone activation hook may not have run. The /leader/ slug
    // is therefore also treated as a dashboard page.
    $is_leader_page = is_page( 'leader' );
    if ( ! $is_leader_page && ( ! $allowed_ids || ! is_page( $allowed_ids ) ) ) return;

    $css = BUBBAHUB_LEADER_DASHBOARD_DIR . 'leader-dashboard.css';
    if ( file_exists( $css ) ) {
        wp_enqueue_style( 'bubbahub-leader-dashboard', BUBBAHUB_LEADER_DASHBOARD_URL . 'leader-dashboard.css', array(), BUBBAHUB_LEADER_DASHBOARD_VERSION );
    }
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
    $listings_url = function_exists( 'bubbahub_leader_management_url' ) ? bubbahub_leader_management_url( 'listings' ) : home_url( '/leader/listings/' );
    $venues_url = function_exists( 'bubbahub_leader_management_url' ) ? bubbahub_leader_management_url( 'venues' ) : home_url( '/leader/venues/' );
    $bookings_url = function_exists( 'bubbahub_leader_management_url' ) ? bubbahub_leader_management_url( 'bookings' ) : home_url( '/leader/bookings/' );

    ob_start(); ?>
    <div class="bh-leader-dashboard">
      <div class="bh-leader-shell">
        <aside class="bh-leader-sidebar">
          <div class="bh-leader-brand"><span class="bh-leader-brand-main">BubbaHub</span><span class="bh-leader-brand-sub">Leader space</span></div>
          <div class="bh-leader-user-mini"><div class="bh-leader-user-avatar"><?php echo esc_html( strtoupper( substr( $name, 0, 1 ) ) ); ?></div><div><strong><?php echo esc_html( $name ); ?></strong><span>Leader account</span></div></div>
          <nav class="bh-leader-nav" aria-label="Leader dashboard">
            <a class="is-active" href="<?php echo esc_url($dashboard_url); ?>"><span>⌂</span> Home</a>
            <a href="<?php echo esc_url($listings_url); ?>"><span>▦</span> My listings</a>
            <a href="<?php echo esc_url($venues_url); ?>"><span>⌖</span> My venues</a>
            <a href="<?php echo esc_url($bookings_url); ?>"><span>▣</span> Bookings<?php if ( $booking_ids ) : ?><b><?php echo esc_html( count($booking_ids) ); ?></b><?php endif; ?></a>
          </nav>
          <div class="bh-leader-nav-divider"></div>
          <nav class="bh-leader-nav bh-leader-nav-secondary" aria-label="Account">
            <a href="#payments"><span>£</span> Payments</a><a href="#earnings"><span>↗</span> Earnings</a><a href="<?php echo esc_url($profile_url = get_edit_profile_url($user->ID)); ?>"><span>⚙</span> My account</a>
          </nav>
          <div class="bh-leader-sidebar-footer"><a href="<?php echo esc_url($logout_url); ?>">Log out</a></div>
        </aside>
        <main class="bh-leader-main">
          <header class="bh-leader-header bh-leader-welcome"><div><p class="bh-leader-eyebrow">Your BubbaHub space</p><h1>Hello <?php echo esc_html($name); ?> <span aria-hidden="true">👋</span></h1><p>Everything you need to run your BubbaHub activities, all in one place.</p></div><div class="bh-leader-header-actions"><a class="bh-secondary-button" href="<?php echo esc_url($bookings_url); ?>">View bookings</a><a class="bh-leader-primary" href="<?php echo esc_url(add_query_arg('new','1',$listings_url)); ?>">+ Add listing</a></div></header>
          <section class="bh-leader-stats" aria-label="Your overview">
            <a class="bh-leader-stat" href="<?php echo esc_url($listings_url); ?>"><span class="bh-stat-icon">▦</span><div><strong><?php echo esc_html(count($groups)); ?></strong><span>Listings</span><small><?php echo esc_html(count($published_groups)); ?> live</small></div><em>→</em></a>
            <a class="bh-leader-stat" href="<?php echo esc_url($venues_url); ?>"><span class="bh-stat-icon">⌖</span><div><strong><?php echo esc_html(count($venues)); ?></strong><span>Venues</span><small><?php echo esc_html(count($published_venues)); ?> live</small></div><em>→</em></a>
            <a class="bh-leader-stat" href="<?php echo esc_url($bookings_url); ?>"><span class="bh-stat-icon">▣</span><div><strong><?php echo esc_html(count($booking_ids)); ?></strong><span>Bookings</span><small>All bookings</small></div><em>→</em></a>
            <a class="bh-leader-stat" href="#earnings"><span class="bh-stat-icon">£</span><div><strong>£0.00</strong><span>Earnings</span><small>Current balance</small></div><em>→</em></a>
          </section>
          <section class="bh-leader-quick-actions"><div class="bh-section-intro"><p class="bh-leader-eyebrow">Quick actions</p><h2>What would you like to do?</h2></div><div class="bh-quick-grid"><a href="<?php echo esc_url(add_query_arg('new','1',$listings_url)); ?>" class="bh-quick-card"><span>▦</span><div><strong>Add a listing</strong><small>Create or update a group activity</small></div><b>→</b></a><a href="<?php echo esc_url(add_query_arg('new','1',$venues_url)); ?>" class="bh-quick-card"><span>⌖</span><div><strong>Add a venue</strong><small>Add a location for your activities</small></div><b>→</b></a><a href="<?php echo esc_url($bookings_url); ?>" class="bh-quick-card"><span>▣</span><div><strong>Check bookings</strong><small>See and manage your bookings</small></div><b>→</b></a><a href="#payments" class="bh-quick-card"><span>£</span><div><strong>Set up payments</strong><small>Payment connections and payouts</small></div><b>→</b></a></div></section>
          <section class="bh-leader-content-grid">
            <article class="bh-leader-panel bh-leader-panel-wide" id="listings"><div class="bh-panel-heading"><div><p>Your activities</p><h2>My listings</h2></div><a class="bh-panel-link" href="<?php echo esc_url($listings_url); ?>">View all →</a></div><?php if($groups): ?><div class="bh-group-list"><?php foreach(array_slice($groups,0,5) as $id): $status=get_post_status($id); ?><div class="bh-group-row"><div class="bh-group-avatar"><?php echo esc_html(strtoupper(substr(get_the_title($id),0,1))); ?></div><div class="bh-group-info"><strong><?php echo esc_html(get_the_title($id)); ?></strong><span><?php echo esc_html(ucfirst($status)); ?></span></div><a href="<?php echo esc_url(add_query_arg('edit',$id,$listings_url)); ?>">Edit <span>→</span></a></div><?php endforeach; ?></div><?php else: ?><div class="bh-empty-dashboard"><div class="bh-empty-icon">+</div><h3>Your first listing starts here</h3><p>Tell local families about your group, class or activity.</p><a class="bh-leader-button" href="<?php echo esc_url(add_query_arg('new','1',$listings_url)); ?>">Create a listing</a></div><?php endif; ?></article>
            <article class="bh-leader-panel" id="bookings"><div class="bh-panel-heading"><div><p>Customers</p><h2>Recent bookings</h2></div><a class="bh-panel-link" href="<?php echo esc_url($bookings_url); ?>">View all →</a></div><?php if($booking_ids): ?><div class="bh-group-list"><?php foreach(array_slice($booking_ids,0,5) as $bid): $status=get_post_meta($bid,'_bh_status',true)?:'pending'; ?><div class="bh-group-row"><div class="bh-group-avatar">▣</div><div class="bh-group-info"><strong><?php echo esc_html(get_the_title($bid)); ?></strong><span><?php echo esc_html(ucfirst($status)); ?></span></div><a href="<?php echo esc_url(add_query_arg('edit',$bid,$bookings_url)); ?>">Open <span>→</span></a></div><?php endforeach; ?></div><?php else: ?><div class="bh-coming"><span>▣</span><div><strong>No bookings yet</strong><p>When families book your activities, they will appear here.</p></div></div><?php endif; ?></article>
            <article class="bh-leader-panel" id="venues"><div class="bh-panel-heading"><div><p>Your locations</p><h2>My venues</h2></div><a class="bh-panel-link" href="<?php echo esc_url($venues_url); ?>">View all →</a></div><?php if($venues): ?><div class="bh-group-list"><?php foreach(array_slice($venues,0,4) as $id): ?><div class="bh-group-row"><div class="bh-group-avatar">⌖</div><div class="bh-group-info"><strong><?php echo esc_html(get_the_title($id)); ?></strong><span><?php echo esc_html(ucfirst(get_post_status($id))); ?></span></div><a href="<?php echo esc_url(add_query_arg('edit',$id,$venues_url)); ?>">Edit <span>→</span></a></div><?php endforeach; ?></div><?php else: ?><div class="bh-empty-dashboard"><div class="bh-empty-icon">⌖</div><h3>Add a venue</h3><p>Give families a clear place to find your activities.</p><a class="bh-secondary-button" href="<?php echo esc_url(add_query_arg('new','1',$venues_url)); ?>">Add venue</a></div><?php endif; ?></article>
            <article class="bh-leader-panel" id="payments"><div class="bh-panel-heading"><div><p>Getting paid</p><h2>Payment accounts</h2></div></div><div class="bh-payment-card"><span class="bh-payment-logo">S</span><div><strong>Stripe</strong><span>Connect your Stripe account</span></div><span class="bh-payment-status">To set up</span></div><div class="bh-payment-card"><span class="bh-payment-logo">P</span><div><strong>PayPal</strong><span>Connect your PayPal account</span></div><span class="bh-payment-status">To set up</span></div><p class="bh-panel-note">Payment connections will let you receive money from bookings.</p></article>
            <article class="bh-leader-panel" id="earnings"><div class="bh-panel-heading"><div><p>Your money</p><h2>Earnings</h2></div></div><div class="bh-earnings-card"><strong>£0.00</strong><span>Available balance</span><p>Your earnings and payout history will appear here.</p></div></article>
          </section>
        </main>
      </div>
    </div>
    <?php return ob_get_clean();
}
