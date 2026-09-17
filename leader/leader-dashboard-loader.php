<?php
/**
 * BubbaHub Leader Dashboard loader – stable shortcode registration and theme integration.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * The Directory plugin bundles the Leader Portal. Load all Leader Portal
 * modules here so the dashboard, listings, venues and bookings work even
 * when the standalone Leader Dashboard plugin activation hook has not run.
 * require_once keeps this safe when the standalone plugin is also active.
 */
require_once __DIR__ . '/leader-listings.php';
require_once __DIR__ . '/leader-venues.php';
require_once __DIR__ . '/leader-bookings.php';
require_once __DIR__ . '/leader-management-pages.php';

/*
 * Internet & Group Monitor is bundled with the Directory plugin. Loading it
 * here keeps the feature attached to the existing plugin without duplicating
 * the main Directory loader or conflicting with the older monitor branch.
 */
$bh_monitor_file = dirname( __DIR__ ) . '/modules/internet-monitor/bubbahub-internet-monitor.php';
if ( file_exists( $bh_monitor_file ) ) {
    require_once $bh_monitor_file;

    if ( class_exists( 'BubbaHub_Internet_Group_Monitor' ) ) {
        register_activation_hook( dirname( __DIR__ ) . '/bubbahub-directory.php', [ 'BubbaHub_Internet_Group_Monitor', 'activate' ] );
        register_deactivation_hook( dirname( __DIR__ ) . '/bubbahub-directory.php', [ 'BubbaHub_Internet_Group_Monitor', 'deactivate' ] );
    }
}

/* Dedicated alert inbox + WordPress dashboard monitor widget. */
$bh_monitor_alerts_file = dirname( __DIR__ ) . '/modules/internet-monitor/bubbahub-monitor-alerts.php';
if ( file_exists( $bh_monitor_alerts_file ) ) {
    require_once $bh_monitor_alerts_file;
}

add_action( 'init', 'bubbahub_leader_dashboard_register', 99 );

function bubbahub_leader_dashboard_register() {
    $renderer = '';
    if ( function_exists( 'bubbahub_leader_dashboard_shortcode' ) ) {
        $renderer = 'bubbahub_leader_dashboard_shortcode';
    } elseif ( function_exists( 'bubbahub_leader_dashboard_render' ) ) {
        $renderer = 'bubbahub_leader_dashboard_render';
    }
    if ( ! $renderer ) return;

    remove_shortcode( 'bubbahub_leader_dashboard' );
    remove_shortcode( 'bubbahub-leader-dashboard' );
    add_shortcode( 'bubbahub_leader_dashboard', $renderer );
    add_shortcode( 'bubbahub-leader-dashboard', $renderer );
}

add_action( 'wp_enqueue_scripts', 'bubbahub_leader_theme_overrides', 20 );
function bubbahub_leader_theme_overrides() {
    if ( ! defined( 'BUBBAHUB_LEADER_DASHBOARD_URL' ) || ! defined( 'BUBBAHUB_LEADER_DASHBOARD_VERSION' ) ) return;

    $is_leader_page = is_page( 'leader' );
    $management_ids = function_exists( 'bubbahub_leader_management_page_ids' ) ? bubbahub_leader_management_page_ids() : array();
    $is_management_page = $management_ids && is_page( array_values( $management_ids ) );

    if ( ! $is_leader_page && ! $is_management_page ) return;

    $css = BUBBAHUB_LEADER_DASHBOARD_DIR . 'leader-theme-overrides.css';
    if ( ! file_exists( $css ) ) return;

    wp_enqueue_style(
        'bubbahub-leader-theme-overrides',
        BUBBAHUB_LEADER_DASHBOARD_URL . 'leader-theme-overrides.css',
        array( 'bubbahub-leader-dashboard' ),
        BUBBAHUB_LEADER_DASHBOARD_VERSION . '.1'
    );
}

/* Keep the monitor dashboard usable on phones/tablets as well as desktop. */
add_action( 'admin_head', 'bubbahub_internet_monitor_admin_css' );
function bubbahub_internet_monitor_admin_css() {
    if ( ! isset( $_GET['page'] ) || 'bh-igm' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) return;
    ?>
    <style>
        .toplevel_page_bh-igm .wrap .widefat { width:100%; }
        .toplevel_page_bh-igm .wrap .widefat td,
        .toplevel_page_bh-igm .wrap .widefat th { vertical-align:top; }
        @media (max-width:782px) {
            .toplevel_page_bh-igm .wrap { margin-right:12px; }
            .toplevel_page_bh-igm .wrap > div[style*="display:flex"] { display:grid !important; grid-template-columns:repeat(2,minmax(0,1fr)); }
            .toplevel_page_bh-igm .wrap > div[style*="display:flex"] > div { min-width:0 !important; box-sizing:border-box; }
            .toplevel_page_bh-igm .wrap .widefat { display:block; overflow-x:auto; white-space:normal; }
            .toplevel_page_bh-igm .wrap .widefat th,
            .toplevel_page_bh-igm .wrap .widefat td { min-width:130px; }
            .toplevel_page_bh-igm .wrap .widefat th:nth-child(4),
            .toplevel_page_bh-igm .wrap .widefat td:nth-child(4) { min-width:240px; }
            .toplevel_page_bh-igm .form-table th,
            .toplevel_page_bh-igm .form-table td { display:block; width:auto; padding:10px 0; }
            .toplevel_page_bh-igm .form-table input.regular-text,
            .toplevel_page_bh-igm .form-table textarea,
            .toplevel_page_bh-igm .form-table input[type="number"] { max-width:100%; box-sizing:border-box; }
        }
        @media (max-width:480px) {
            .toplevel_page_bh-igm .wrap > div[style*="display:flex"] { grid-template-columns:1fr; }
        }
    </style>
    <?php
}

bubbahub_leader_dashboard_register();
