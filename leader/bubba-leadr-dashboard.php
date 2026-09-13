<?php
/**
 * Plugin Name: BubbaHub Leader Dashboard
 * Description: Front-end dashboard foundation for BubbaHub leaders and leaderpro users.
 * Version: 1.0.0
 * Author: BubbaHub
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BUBBAHUB_LEADER_DASHBOARD_VERSION', '1.0.0' );
define( 'BUBBAHUB_LEADER_DASHBOARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUBBAHUB_LEADER_DASHBOARD_URL', plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, 'bubbahub_leader_dashboard_activate' );

function bubbahub_leader_dashboard_activate() {
	$page = get_page_by_path( 'leader' );

	if ( ! $page ) {
		$page_id = wp_insert_post(
			array(
				'post_title'   => 'Leader Dashboard',
				'post_name'    => 'leader',
				'post_content' => '[bubbahub_leader_dashboard]',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

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
	if ( ! is_page( 'leader' ) ) {
		return;
	}

	$css_file = BUBBAHUB_LEADER_DASHBOARD_DIR . 'leader-dashboard.css';

	if ( file_exists( $css_file ) ) {
		wp_enqueue_style(
			'bubbahub-leader-dashboard',
			BUBBAHUB_LEADER_DASHBOARD_URL . 'leader-dashboard.css',
			array(),
			BUBBAHUB_LEADER_DASHBOARD_VERSION
		);
	}
}

add_shortcode( 'bubbahub_leader_dashboard', 'bubbahub_leader_dashboard_shortcode' );

function bubbahub_leader_dashboard_is_allowed() {
	if ( ! is_user_logged_in() ) {
		return false;
	}

	$user = wp_get_current_user();

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	return (bool) array_intersect(
		array( 'leader', 'leaderpro' ),
		(array) $user->roles
	);
}

function bubbahub_leader_dashboard_shortcode() {
	if ( ! is_user_logged_in() ) {
		$login_url = wp_login_url( home_url( '/leader/' ) );

		return '<div class="bh-leader-message"><div class="bh-leader-message-card">'
			. '<span class="bh-leader-message-icon">🔐</span>'
			. '<h2>Leader Dashboard</h2>'
			. '<p>Please log in to access your BubbaHub leader dashboard.</p>'
			. '<a class="bh-leader-button" href="' . esc_url( $login_url ) . '">Log in</a>'
			. '</div></div>';
	}

	if ( ! bubbahub_leader_dashboard_is_allowed() ) {
		return '<div class="bh-leader-message"><div class="bh-leader-message-card">'
			. '<span class="bh-leader-message-icon">🔒</span>'
			. '<h2>Leader Dashboard</h2>'
			. '<p>This area is available to approved BubbaHub leaders.</p>'
			. '<a class="bh-leader-button" href="' . esc_url( home_url( '/' ) ) . '">Return to BubbaHub</a>'
			. '</div></div>';
	}

	$user = wp_get_current_user();
	$name = $user->first_name ? $user->first_name : $user->display_name;

	$groups = get_posts(
		array(
			'post_type'      => 'group',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'author'         => $user->ID,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	$group_count = count( $groups );

	$published_groups = get_posts(
		array(
			'post_type'      => 'group',
			'post_status'    => 'publish',
			'author'         => $user->ID,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	$published_count = count( $published_groups );
	$booking_count = 0;

	if ( post_type_exists( 'bh_booking' ) && $group_count ) {
		$booking_query = new WP_Query(
			array(
				'post_type'      => 'bh_booking',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'meta_query'     => array(
					array(
						'key'     => '_bh_group_id',
						'value'   => $groups,
						'compare' => 'IN',
					),
				),
			)
		);

		$booking_count = (int) $booking_query->found_posts;
		wp_reset_postdata();
	}

	$dashboard_url = home_url( '/leader/' );
	$logout_url    = wp_logout_url( $dashboard_url );

	ob_start();
	?>
	<div class="bh-leader-dashboard">
		<div class="bh-leader-shell">
			<aside class="bh-leader-sidebar">
				<div class="bh-leader-brand">
					<span class="bh-leader-brand-main">BubbaHub</span>
					<span class="bh-leader-brand-sub">Leader</span>
				</div>
				<nav class="bh-leader-nav" aria-label="Leader dashboard">
					<a class="is-active" href="<?php echo esc_url( $dashboard_url ); ?>"><span>⌂</span> Dashboard</a>
					<a href="#groups"><span>▦</span> My Groups</a>
					<a href="#sessions"><span>◷</span> Sessions &amp; Dates</a>
					<a href="#bookings"><span>▣</span> Bookings</a>
					<a href="#payments"><span>£</span> Payments</a>
					<a href="#earnings"><span>↗</span> Earnings</a>
					<a href="#account"><span>⚙</span> Account</a>
				</nav>
				<div class="bh-leader-sidebar-footer">
					<a href="<?php echo esc_url( $logout_url ); ?>">Log out</a>
				</div>
			</aside>

			<main class="bh-leader-main">
				<header class="bh-leader-header">
					<div>
						<p class="bh-leader-eyebrow">Leader area</p>
						<h1>Welcome back, <?php echo esc_html( $name ); ?></h1>
						<p>Manage your BubbaHub groups, sessions and bookings from one place.</p>
					</div>
					<a class="bh-leader-primary" href="#groups">Manage my groups <span aria-hidden="true">→</span></a>
				</header>

				<section class="bh-leader-stats" aria-label="Dashboard overview">
					<article class="bh-leader-stat"><span class="bh-stat-icon">▦</span><div><strong><?php echo esc_html( $group_count ); ?></strong><span>My Groups</span></div></article>
					<article class="bh-leader-stat"><span class="bh-stat-icon">◷</span><div><strong>0</strong><span>Upcoming Sessions</span></div></article>
					<article class="bh-leader-stat"><span class="bh-stat-icon">▣</span><div><strong><?php echo esc_html( $booking_count ); ?></strong><span>Bookings</span></div></article>
					<article class="bh-leader-stat"><span class="bh-stat-icon">£</span><div><strong>£0.00</strong><span>Earnings</span></div></article>
				</section>

				<section class="bh-leader-content-grid">
					<article class="bh-leader-panel bh-leader-panel-wide" id="groups">
						<div class="bh-panel-heading"><div><p>Listings</p><h2>My Groups</h2></div><span class="bh-panel-count"><?php echo esc_html( $published_count ); ?> live</span></div>
						<?php if ( $group_count ) : ?>
							<div class="bh-group-list">
								<?php foreach ( array_slice( $groups, 0, 6 ) as $group_id ) : ?>
									<?php
									$title  = get_the_title( $group_id );
									$status = get_post_status( $group_id );
									$letter = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 1 ) : substr( $title, 0, 1 );
									?>
									<div class="bh-group-row">
										<div class="bh-group-avatar"><?php echo esc_html( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $letter ) : strtoupper( $letter ) ); ?></div>
										<div class="bh-group-info"><strong><?php echo esc_html( $title ); ?></strong><span><?php echo esc_html( ucfirst( $status ) ); ?></span></div>
										<a href="<?php echo esc_url( get_permalink( $group_id ) ); ?>">View <span aria-hidden="true">→</span></a>
									</div>
								<?php endforeach; ?>
							</div>
							<?php if ( $group_count > 6 ) : ?><p class="bh-list-note">Showing your 6 most recent groups.</p><?php endif; ?>
						<?php else : ?>
							<div class="bh-empty-dashboard">
								<div class="bh-empty-icon">+</div><h3>Create your first group</h3>
								<p>Your BubbaHub group listings will appear here once you create them.</p>
								<a class="bh-leader-button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=group' ) ); ?>">Create a group</a>
							</div>
						<?php endif; ?>
					</article>

					<article class="bh-leader-panel" id="payments">
						<div class="bh-panel-heading"><div><p>Payments</p><h2>Payment Accounts</h2></div></div>
						<div class="bh-payment-card"><span class="bh-payment-logo">S</span><div><strong>Stripe</strong><span>Connect your Stripe account</span></div><span class="bh-payment-status">Next stage</span></div>
						<div class="bh-payment-card"><span class="bh-payment-logo">P</span><div><strong>PayPal</strong><span>Connect your PayPal account</span></div><span class="bh-payment-status">Next stage</span></div>
						<p class="bh-panel-note">Your own Stripe or PayPal account will be connected in the payment stage.</p>
					</article>

					<article class="bh-leader-panel" id="sessions">
						<div class="bh-panel-heading"><div><p>Schedule</p><h2>Sessions &amp; Dates</h2></div></div>
						<div class="bh-coming"><span>◷</span><div><strong>Session management</strong><p>Your dates, availability, capacity and ticket settings will appear here.</p></div></div>
					</article>

					<article class="bh-leader-panel" id="bookings">
						<div class="bh-panel-heading"><div><p>Customers</p><h2>Bookings</h2></div></div>
						<div class="bh-coming"><span>▣</span><div><strong>Booking management</strong><p>Book Now and Reserve Spot bookings will be managed here.</p></div></div>
					</article>

					<article class="bh-leader-panel" id="earnings">
						<div class="bh-panel-heading"><div><p>Finance</p><h2>Earnings</h2></div></div>
						<div class="bh-coming"><span>£</span><div><strong>Leader earnings</strong><p>Wallet balance, transactions, fees and transfers will be added here.</p></div></div>
					</article>

					<article class="bh-leader-panel" id="account">
						<div class="bh-panel-heading"><div><p>Your profile</p><h2>Account</h2></div></div>
						<div class="bh-coming"><span>⚙</span><div><strong>Account settings</strong><p>Your leader profile and account settings will be managed here.</p></div></div>
					</article>
				</section>
			</main>
		</div>
	</div>
	<?php
	return ob_get_clean();
}
