<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * ACF Pro must initialise its frontend form handling before the page header.
 * This enables the same ACF field groups used in wp-admin to be rendered and
 * saved safely from the leader dashboard and its standalone management pages.
 */
add_action( 'template_redirect', function() {
    $management_ids = function_exists( 'bubbahub_leader_management_page_ids' ) ? bubbahub_leader_management_page_ids() : array();
    $page_ids = array_filter( array_merge(
        array( (int) get_option( 'bubbahub_leader_dashboard_page_id', 0 ) ),
        array_values( $management_ids )
    ) );

    if ( ! is_page( $page_ids ) ) return;
    if ( function_exists( 'acf_form_head' ) ) acf_form_head();
}, 1 );

require_once __DIR__ . '/leader-listings.php';
require_once __DIR__ . '/leader-venues.php';
require_once __DIR__ . '/leader-bookings.php';
require_once __DIR__ . '/leader-management-pages.php';
