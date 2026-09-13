<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * ACF Pro must initialise its frontend form handling before the page header.
 * This enables the same ACF field groups used in wp-admin to be rendered and
 * saved safely from /leader/.
 */
add_action( 'template_redirect', function() {
    if ( ! is_page( 'leader' ) ) return;
    if ( function_exists( 'acf_form_head' ) ) acf_form_head();
}, 1 );

require_once __DIR__ . '/leader-listings.php';
require_once __DIR__ . '/leader-venues.php';
require_once __DIR__ . '/leader-bookings.php';
