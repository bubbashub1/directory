<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_leader_management_page_ids() {
    return array(
        'listings' => (int) get_option( 'bubbahub_leader_listings_page_id', 0 ),
        'venues'   => (int) get_option( 'bubbahub_leader_venues_page_id', 0 ),
        'bookings' => (int) get_option( 'bubbahub_leader_bookings_page_id', 0 ),
        'schedule' => (int) get_option( 'bubbahub_leader_schedule_page_id', 0 ),
    );
}

function bubbahub_leader_ensure_management_pages() {
    $parent = get_page_by_path( 'leader' );
    if ( ! $parent ) return;
    $pages = array(
        'listings' => array( 'title'=>'My Listings', 'shortcode'=>'[bubbahub_leader_manage type="listing"]', 'option'=>'bubbahub_leader_listings_page_id' ),
        'venues'   => array( 'title'=>'My Venues', 'shortcode'=>'[bubbahub_leader_manage type="venue"]', 'option'=>'bubbahub_leader_venues_page_id' ),
        'bookings' => array( 'title'=>'Bookings', 'shortcode'=>'[bubbahub_leader_manage type="booking"]', 'option'=>'bubbahub_leader_bookings_page_id' ),
        'schedule' => array( 'title'=>'My Sessions', 'shortcode'=>'[bubbahub_leader_schedule]', 'option'=>'bubbahub_leader_schedule_page_id' ),
    );
    foreach ( $pages as $slug => $page ) {
        $existing_id = (int) get_option( $page['option'], 0 );
        if ( $existing_id && 'publish' === get_post_status( $existing_id ) ) continue;
        $existing = get_page_by_path( 'leader/' . $slug );
        if ( ! $existing ) $existing = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $existing && 'publish' === get_post_status( $existing->ID ) ) {
            update_option( $page['option'], (int) $existing->ID );
            continue;
        }
        $page_id = wp_insert_post( array(
            'post_title' => $page['title'], 'post_name' => $slug, 'post_content' => $page['shortcode'],
            'post_status' => 'publish', 'post_type' => 'page', 'post_parent' => (int) $parent->ID,
        ) );
        if ( $page_id && ! is_wp_error( $page_id ) ) update_option( $page['option'], (int) $page_id );
    }
}
add_action( 'init', 'bubbahub_leader_ensure_management_pages', 20 );

function bubbahub_leader_management_url( $type ) {
    $ids = bubbahub_leader_management_page_ids();
    $id = isset( $ids[$type] ) ? $ids[$type] : 0;
    return $id ? get_permalink( $id ) : home_url( '/leader/' . $type . '/' );
}

function bubbahub_leader_manage_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'type' => 'listing' ), $atts, 'bubbahub_leader_manage' );
    $type = sanitize_key( $atts['type'] );
    if ( ! function_exists( 'bubbahub_leader_dashboard_is_allowed' ) || ! bubbahub_leader_dashboard_is_allowed() ) {
        return '<div class="bh-leader-message"><div class="bh-leader-message-card"><span class="bh-leader-message-icon">🔒</span><h2>Leader area</h2><p>You need an approved leader account to access this page.</p><a class="bh-leader-button" href="' . esc_url( home_url('/leader/') ) . '">Back to Dashboard</a></div></div>';
    }
    $dashboard_url = home_url( '/leader/' );
    $edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
    ob_start(); ?>
    <div class="bh-leader-management-page"><div class="bh-leader-management-shell">
    <a class="bh-back-dashboard" href="<?php echo esc_url($dashboard_url); ?>">← Back to Dashboard</a>
    <?php if ( 'listing' === $type ): ?>
      <div class="bh-management-page-header"><div><p class="bh-leader-eyebrow">Leader area</p><h1><?php echo $edit_id ? 'Edit Listing' : 'Add Listing'; ?></h1><p>Use the same BubbaHub fields you use in the WordPress dashboard to manage your group listing.</p></div><span class="bh-management-page-icon">▦</span></div>
      <div class="bh-management-page-card"><?php if ( $edit_id ) { if ( function_exists('bubbahub_leader_owned_post') && bubbahub_leader_owned_post($edit_id,'group') ) bubbahub_leader_listing_form($edit_id); else echo '<div class="bh-form-warning">You cannot edit this listing.</div>'; } else bubbahub_leader_listing_form(); ?></div>
    <?php elseif ( 'venue' === $type ): ?>
      <div class="bh-management-page-header"><div><p class="bh-leader-eyebrow">Leader area</p><h1><?php echo $edit_id ? 'Edit Venue' : 'Add Venue'; ?></h1><p>Manage your venue using the same ACF location fields used in the WordPress dashboard.</p></div><span class="bh-management-page-icon">⌂</span></div>
      <div class="bh-management-page-card"><?php if ( $edit_id ) { if ( function_exists('bubbahub_leader_owned_post') && bubbahub_leader_owned_post($edit_id,'venue') ) bubbahub_leader_venue_form($edit_id); else echo '<div class="bh-form-warning">You cannot edit this venue.</div>'; } else bubbahub_leader_venue_form(); ?></div>
    <?php elseif ( 'booking' === $type ): ?>
      <div class="bh-management-page-header"><div><p class="bh-leader-eyebrow">Leader area</p><h1><?php echo $edit_id ? 'Edit Booking' : 'My Bookings'; ?></h1><p>Manage customer reservations and create reusable booking pages for terms, courses and programmes.</p></div><span class="bh-management-page-icon">▣</span></div>
      <?php if ( class_exists('BubbaHub_Generic_Booking_Pages') ): ?><div class="bh-management-page-card bh-generic-booking-callout"><div><p class="bh-leader-eyebrow">NEW</p><h2>Reusable booking page</h2><p>Create a customer-facing page such as <strong>Autumn Term</strong>, <strong>Spring Term</strong> or <strong>Summer Programme</strong>. It can use a custom label, a date range, both, or no date at all.</p></div><a class="bh-leader-button" href="<?php echo esc_url(add_query_arg('generic','new',bubbahub_leader_management_url('bookings'))); ?>">Create booking page</a></div><?php endif; ?>
      <?php if ( isset($_GET['generic']) && 'new' === sanitize_key(wp_unslash($_GET['generic'])) && class_exists('BubbaHub_Generic_Booking_Pages') ): ?><div class="bh-management-page-card"><div class="bh-form-section"><p class="bh-leader-eyebrow">Booking pages</p><h2>Create reusable booking page</h2><p>Use this for a term or programme rather than a single date.</p></div><?php $bh_generic_booking_instance = new BubbaHub_Generic_Booking_Pages(); $bh_generic_booking_instance->form(); ?></div><?php elseif ( $edit_id ): ?><div class="bh-management-page-card"><?php $allowed=function_exists('bubbahub_leader_booking_ids')?bubbahub_leader_booking_ids():array(); if(!in_array($edit_id,$allowed,true)) echo '<div class="bh-form-warning">You cannot edit this booking.</div>'; else bubbahub_leader_booking_form($edit_id); ?></div><?php else: ?><div class="bh-management-page-card"><div class="bh-form-warning">Your customer reservations will appear here. Use <strong>Create booking page</strong> above when you want a reusable term or programme page.</div></div><?php endif; ?>
    <?php else: ?><div class="bh-management-page-card"><div class="bh-form-warning">Management page not found.</div></div><?php endif; ?>
    </div></div><?php return ob_get_clean();
}
add_shortcode( 'bubbahub_leader_manage', 'bubbahub_leader_manage_shortcode' );

function bubbahub_leader_schedule_admin_notice() {
    if ( ! function_exists('bubbahub_leader_dashboard_is_allowed') || ! bubbahub_leader_dashboard_is_allowed() ) return;
    if ( ! is_page( (int) get_option('bubbahub_leader_schedule_page_id',0) ) ) return;
    if ( isset($_GET['schedule_saved']) ) echo '<div class="bh-schedule-notice" role="status">Schedule saved successfully. Your timetable has been updated.</div>';
    if ( isset($_GET['schedule_deleted']) ) echo '<div class="bh-schedule-notice" role="status">The session and its generated occurrences have been removed.</div>';
}
add_action( 'wp_footer', 'bubbahub_leader_schedule_admin_notice', 5 );

add_action( 'wp_head', function(){
    if ( ! function_exists('bubbahub_leader_management_page_ids') ) return;
    $ids = array_filter( array_values( bubbahub_leader_management_page_ids() ) );
    if ( ! $ids || ! is_page($ids) ) return;
    ?><style id="bh-acf-management-native-reset">
    .bh-management-page-card .bh-acf-fields{display:block;width:100%;margin:0;padding:0}.bh-management-page-card .bh-acf-fields .acf-field{display:block;float:none;width:100%;clear:both;box-sizing:border-box;margin:0 0 18px;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important}.bh-management-page-card .bh-acf-fields .acf-label{display:block;width:100%;margin:0 0 7px;padding:0}.bh-management-page-card .bh-acf-fields .acf-label label{display:block;font-weight:700}.bh-management-page-card .bh-acf-fields .acf-input{display:block;width:100%;padding:0}.bh-management-page-card .bh-acf-fields .acf-input input:not([type="checkbox"]):not([type="radio"]),.bh-management-page-card .bh-acf-fields .acf-input textarea,.bh-management-page-card .bh-acf-fields .acf-input select{width:100%;max-width:100%;box-sizing:border-box}.bh-management-page-card .bh-acf-fields .acf-field-repeater,.bh-management-page-card .bh-acf-fields .acf-field-group{width:100%;clear:both}.bh-management-page-card .bh-acf-fields .acf-repeater{width:100%;clear:both}.bh-management-page-card .bh-acf-submit{width:100%;clear:both;margin-top:8px}.bh-generic-booking-callout{display:flex;justify-content:space-between;align-items:center;gap:24px}.bh-generic-booking-callout h2{margin:0 0 8px}.bh-generic-booking-callout p{margin:0}.bh-generic-booking-form .bh-form-section{margin:0 0 18px}.bh-generic-booking-form .bh-form-section h3{margin:0 0 6px}.bh-schedule-notice{max-width:1100px;margin:18px auto;padding:14px 18px;border-radius:12px;background:#eef8ee;border:1px solid #b9dfbd;font-weight:700}.bh-schedule-nav-card{display:flex;justify-content:space-between;align-items:center;gap:18px;margin-bottom:18px;padding:18px 20px;border-radius:16px;background:#f8f8fb;border:1px solid #e5e5ec}.bh-schedule-nav-card h3{margin:0 0 4px}.bh-schedule-nav-card p{margin:0}.bh-schedule-actions{display:flex;gap:10px;flex-wrap:wrap}.bh-schedule-actions a,.bh-schedule-actions button{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:8px 14px;border-radius:10px;border:1px solid #ddd;background:#fff;text-decoration:none;cursor:pointer;font-weight:700}.bh-schedule-actions .bh-danger{border-color:#e1bcbc;background:#fff7f7}.bh-schedule-list{display:grid;gap:12px}.bh-schedule-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:center;padding:18px 20px;border:1px solid #e6e6eb;border-radius:16px;background:#fff}.bh-schedule-row-main{min-width:0}.bh-schedule-row-title{font-size:18px;font-weight:800;margin:0 0 5px}.bh-schedule-row-meta{display:flex;flex-wrap:wrap;gap:7px;font-size:14px}.bh-schedule-pill{display:inline-flex;padding:5px 9px;border-radius:999px;background:#f2f2f6}.bh-schedule-empty{padding:28px;border:1px dashed #d5d5df;border-radius:16px;text-align:center}.bh-schedule-empty p{margin-bottom:14px}.bh-schedule-form .bh-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.bh-schedule-form label{display:flex;flex-direction:column;gap:7px;font-weight:700}.bh-schedule-form input,.bh-schedule-form select{width:100%;box-sizing:border-box;min-height:44px;padding:9px 11px;border:1px solid #d8d8df;border-radius:10px;background:#fff}.bh-schedule-form small{font-weight:400;opacity:.75}.bh-schedule-form .bh-form-full{grid-column:1/-1}.bh-schedule-form .bh-checkbox{flex-direction:row;align-items:center;grid-column:1/-1}.bh-schedule-form .bh-checkbox input{width:auto;min-height:auto}.bh-schedule-intro{margin-bottom:20px}.bh-schedule-intro h2{margin:0 0 6px}.bh-schedule-intro p:last-child{margin:0}.bh-schedule-manager .bh-leader-primary{margin-top:18px}.bh-schedule-recurrence{font-weight:700}.bh-schedule-source{opacity:.7}@media(max-width:700px){.bh-generic-booking-callout,.bh-schedule-nav-card{display:block}.bh-generic-booking-callout .bh-leader-button,.bh-schedule-nav-card .bh-leader-button{display:block;width:100%;margin-top:16px;text-align:center}.bh-form-grid,.bh-schedule-form .bh-form-grid{grid-template-columns:1fr}.bh-schedule-row{grid-template-columns:1fr}.bh-schedule-actions a,.bh-schedule-actions button{width:100%}}
    </style><?php
},99);
