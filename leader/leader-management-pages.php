<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_leader_management_page_ids() {
    return array(
        'listings' => (int) get_option( 'bubbahub_leader_listings_page_id', 0 ),
        'venues'   => (int) get_option( 'bubbahub_leader_venues_page_id', 0 ),
        'bookings' => (int) get_option( 'bubbahub_leader_bookings_page_id', 0 ),
    );
}

function bubbahub_leader_ensure_management_pages() {
    $parent = get_page_by_path( 'leader' );
    if ( ! $parent ) return;

    $pages = array(
        'listings' => array( 'title' => 'My Listings', 'shortcode' => '[bubbahub_leader_manage type="listing"]', 'option' => 'bubbahub_leader_listings_page_id' ),
        'venues'   => array( 'title' => 'My Venues', 'shortcode' => '[bubbahub_leader_manage type="venue"]', 'option' => 'bubbahub_leader_venues_page_id' ),
        'bookings' => array( 'title' => 'Bookings', 'shortcode' => '[bubbahub_leader_manage type="booking"]', 'option' => 'bubbahub_leader_bookings_page_id' ),
    );

    foreach ( $pages as $slug => $page ) {
        $existing_id = (int) get_option( $page['option'], 0 );
        if ( $existing_id && 'publish' === get_post_status( $existing_id ) ) continue;

        $existing = get_page_by_path( 'leader/' . $slug );
        if ( ! $existing ) {
            $existing = get_page_by_path( $slug, OBJECT, 'page' );
        }

        if ( $existing && 'publish' === get_post_status( $existing->ID ) ) {
            update_option( $page['option'], (int) $existing->ID );
            continue;
        }

        $page_id = wp_insert_post( array(
            'post_title'   => $page['title'],
            'post_name'    => $slug,
            'post_content' => $page['shortcode'],
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_parent'  => (int) $parent->ID,
        ) );

        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( $page['option'], (int) $page_id );
        }
    }
}
add_action( 'init', 'bubbahub_leader_ensure_management_pages', 20 );

function bubbahub_leader_management_url( $type ) {
    $ids = bubbahub_leader_management_page_ids();
    $id  = isset( $ids[ $type ] ) ? $ids[ $type ] : 0;
    return $id ? get_permalink( $id ) : home_url( '/leader/' . $type . '/' );
}

function bubbahub_leader_manage_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'type' => 'listing' ), $atts, 'bubbahub_leader_manage' );
    $type = sanitize_key( $atts['type'] );

    if ( ! function_exists( 'bubbahub_leader_dashboard_is_allowed' ) || ! bubbahub_leader_dashboard_is_allowed() ) {
        return '<div class="bh-leader-message"><div class="bh-leader-message-card"><span class="bh-leader-message-icon">🔒</span><h2>Leader area</h2><p>You need an approved leader account to access this page.</p><a class="bh-leader-button" href="' . esc_url( home_url( '/leader/' ) ) . '">Back to Dashboard</a></div></div>';
    }

    $dashboard_url = home_url( '/leader/' );
    $edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
    $is_new  = isset( $_GET['new'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['new'] ) );

    ob_start();
    ?>
    <div class="bh-leader-management-page">
        <div class="bh-leader-management-shell">
            <a class="bh-back-dashboard" href="<?php echo esc_url( $dashboard_url ); ?>">← Back to Dashboard</a>

            <?php if ( 'listing' === $type ) : ?>
                <div class="bh-management-page-header">
                    <div><p class="bh-leader-eyebrow">Leader area</p><h1><?php echo $edit_id ? 'Edit Listing' : 'Add Listing'; ?></h1><p>Use the same BubbaHub fields you use in the WordPress dashboard to manage your group listing.</p></div>
                    <span class="bh-management-page-icon">▦</span>
                </div>
                <div class="bh-management-page-card">
                    <?php
                    if ( $edit_id ) {
                        if ( function_exists( 'bubbahub_leader_owned_post' ) && bubbahub_leader_owned_post( $edit_id, 'group' ) ) bubbahub_leader_listing_form( $edit_id );
                        else echo '<div class="bh-form-warning">You cannot edit this listing.</div>';
                    } else {
                        bubbahub_leader_listing_form();
                    }
                    ?>
                </div>
            <?php elseif ( 'venue' === $type ) : ?>
                <div class="bh-management-page-header">
                    <div><p class="bh-leader-eyebrow">Leader area</p><h1><?php echo $edit_id ? 'Edit Venue' : 'Add Venue'; ?></h1><p>Manage your venue using the same ACF location fields used in the WordPress dashboard.</p></div>
                    <span class="bh-management-page-icon">⌂</span>
                </div>
                <div class="bh-management-page-card">
                    <?php
                    if ( $edit_id ) {
                        if ( function_exists( 'bubbahub_leader_owned_post' ) && bubbahub_leader_owned_post( $edit_id, 'venue' ) ) bubbahub_leader_venue_form( $edit_id );
                        else echo '<div class="bh-form-warning">You cannot edit this venue.</div>';
                    } else {
                        bubbahub_leader_venue_form();
                    }
                    ?>
                </div>
            <?php elseif ( 'booking' === $type ) : ?>
                <div class="bh-management-page-header">
                    <div><p class="bh-leader-eyebrow">Leader area</p><h1><?php echo $edit_id ? 'Edit Booking' : 'Add Booking'; ?></h1><p>Create or update a booking for one of your own group listings.</p></div>
                    <span class="bh-management-page-icon">▣</span>
                </div>
                <div class="bh-management-page-card">
                    <?php
                    $allowed = function_exists( 'bubbahub_leader_booking_ids' ) ? bubbahub_leader_booking_ids() : array();
                    if ( $edit_id && ! in_array( $edit_id, $allowed, true ) ) {
                        echo '<div class="bh-form-warning">You cannot edit this booking.</div>';
                    } else {
                        bubbahub_leader_booking_form( $edit_id );
                    }
                    ?>
                </div>
            <?php else : ?>
                <div class="bh-management-page-card"><div class="bh-form-warning">Management page not found.</div></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'bubbahub_leader_manage', 'bubbahub_leader_manage_shortcode' );

/*
 * Keep the ACF frontend form visually close to wp-admin. The main dashboard
 * stylesheet is intentionally not allowed to restyle individual ACF fields.
 * This override is scoped to the standalone management card only.
 */
add_action( 'wp_head', function() {
    if ( ! function_exists( 'bubbahub_leader_management_page_ids' ) ) return;
    $ids = array_filter( array_values( bubbahub_leader_management_page_ids() ) );
    if ( ! $ids || ! is_page( $ids ) ) return;
    ?>
    <style id="bh-acf-management-native-reset">
        .bh-management-page-card .bh-acf-fields{display:block;width:100%;margin:0;padding:0}
        .bh-management-page-card .bh-acf-fields .acf-field{display:block;float:none;width:100%;clear:both;box-sizing:border-box;margin:0 0 18px;padding:0!important;border:0!important;border-radius:0!important;background:transparent!important}
        .bh-management-page-card .bh-acf-fields .acf-label{display:block;width:100%;margin:0 0 7px;padding:0}
        .bh-management-page-card .bh-acf-fields .acf-label label{display:block;font-weight:700}
        .bh-management-page-card .bh-acf-fields .acf-input{display:block;width:100%;padding:0}
        .bh-management-page-card .bh-acf-fields .acf-input input:not([type="checkbox"]):not([type="radio"]),
        .bh-management-page-card .bh-acf-fields .acf-input textarea,
        .bh-management-page-card .bh-acf-fields .acf-input select{width:100%;max-width:100%;box-sizing:border-box}
        .bh-management-page-card .bh-acf-fields .acf-field-repeater,
        .bh-management-page-card .bh-acf-fields .acf-field-group{width:100%;clear:both}
        .bh-management-page-card .bh-acf-fields .acf-repeater{width:100%;clear:both}
        .bh-management-page-card .bh-acf-submit{width:100%;clear:both;margin-top:8px}
    </style>
    <?php
}, 99 );
