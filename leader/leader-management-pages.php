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
        'bookings' => array( 'title'=>'My Bookings', 'shortcode'=>'[bubbahub_leader_manage type="booking"]', 'option'=>'bubbahub_leader_bookings_page_id' ),
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

function bubbahub_leader_manage_card_address( $post_id ) {
    $address = get_post_meta( $post_id, 'address', true );
    if ( function_exists( 'get_field' ) ) {
        $acf = get_field( 'address', $post_id );
        if ( $acf ) $address = $acf;
    }
    if ( is_array( $address ) ) {
        $parts = array();
        foreach ( array( 'address', 'street', 'street2', 'city', 'town', 'region', 'postcode', 'zip' ) as $key ) {
            if ( ! empty( $address[ $key ] ) ) $parts[] = $address[ $key ];
        }
        $address = implode( ', ', $parts );
    }
    return is_scalar( $address ) ? trim( (string) $address ) : '';
}

function bubbahub_leader_card_map( $post_id ) {
    $map = function_exists( 'bubbahub_directory_get_field' ) ? bubbahub_directory_get_field( $post_id, 'map', '' ) : get_post_meta( $post_id, 'map', true );
    if ( function_exists( 'bubbahub_directory_normalise_map' ) ) return bubbahub_directory_normalise_map( $map );
    return null;
}

function bubbahub_leader_management_overview( $type, $items, $management_url ) {
    $labels = array(
        'listing' => array( 'eyebrow'=>'Your activities', 'title'=>'My Listings', 'description'=>'Manage the groups and classes you share with local families.', 'add'=>'Add Listing', 'empty'=>'No listings added', 'empty_text'=>'Add your first group or class to start building your BubbaHub presence.', 'icon'=>'▦' ),
        'venue'   => array( 'eyebrow'=>'Your locations', 'title'=>'My Venues', 'description'=>'Keep the places where your activities happen organised in one place.', 'add'=>'Add Venue', 'empty'=>'No venues added', 'empty_text'=>'Add a venue now so you can connect it to your listings and bookings.', 'icon'=>'⌖' ),
        'booking' => array( 'eyebrow'=>'Customers', 'title'=>'My Bookings', 'description'=>'View and manage reservations for your BubbaHub activities.', 'add'=>'Create Booking', 'empty'=>'No bookings yet', 'empty_text'=>'When families book your activities, their reservations will appear here.', 'icon'=>'▣' ),
    );
    $l = $labels[ $type ];
    ob_start();
    ?>
    <div class="bh-leader-overview">
        <div class="bh-management-page-header bh-overview-header">
            <div><p class="bh-leader-eyebrow"><?php echo esc_html( $l['eyebrow'] ); ?></p><h1><?php echo esc_html( $l['title'] ); ?></h1><p><?php echo esc_html( $l['description'] ); ?></p></div>
            <div class="bh-overview-header-actions"><a class="bh-leader-primary" href="<?php echo esc_url( add_query_arg( 'new', '1', $management_url ) ); ?>">+ <?php echo esc_html( $l['add'] ); ?></a></div>
        </div>
        <?php if ( ! $items ) : ?>
            <div class="bh-management-page-card bh-management-empty-state">
                <div class="bh-empty-icon"><?php echo esc_html( $l['icon'] ); ?></div>
                <h2><?php echo esc_html( $l['empty'] ); ?></h2>
                <p><?php echo esc_html( $l['empty_text'] ); ?></p>
                <a class="bh-leader-primary" href="<?php echo esc_url( add_query_arg( 'new', '1', $management_url ) ); ?>"><?php echo esc_html( $l['add'] ); ?> now</a>
            </div>
        <?php else : ?>
            <div class="bh-management-card-grid bh-management-card-grid-<?php echo esc_attr( $type ); ?>">
            <?php foreach ( $items as $id ) :
                $title = get_the_title( $id );
                $status = get_post_status( $id );
                if ( 'listing' === $type ) :
                    $image = function_exists( 'bubbahub_directory_image_url' ) ? bubbahub_directory_image_url( $id ) : get_the_post_thumbnail_url( $id, 'medium' );
                    $address = bubbahub_leader_manage_card_address( $id );
                    $region = function_exists( 'bubbahub_directory_region' ) ? bubbahub_directory_region( $id ) : '';
                    $schedule = function_exists( 'bubbahub_directory_get_field' ) ? bubbahub_directory_get_field( $id, 'business_hours', '' ) : get_post_meta( $id, 'business_hours', true );
                    if ( ! $schedule && function_exists( 'bubbahub_directory_get_field' ) ) $schedule = bubbahub_directory_get_field( $id, 'schedule', '' );
                    $schedule = function_exists( 'bubbahub_directory_format_value' ) ? bubbahub_directory_format_value( $schedule ) : ( is_scalar( $schedule ) ? $schedule : '' );
                    ?>
                    <article class="bh-management-card bh-management-listing-card">
                        <div class="bh-management-card-media"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy"><?php else : ?><div class="bh-management-media-placeholder">BubbaHub</div><?php endif; ?><span class="bh-management-status"><?php echo esc_html( ucfirst( $status ) ); ?></span></div>
                        <div class="bh-management-card-body"><h2><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( $title ); ?></a></h2><?php if ( $region || $address ) : ?><p class="bh-management-location">⌖ <?php echo esc_html( $region ? ( $region . ( $address ? ' · ' . $address : '' ) ) : $address ); ?></p><?php endif; ?><?php if ( $schedule ) : ?><p class="bh-management-schedule">◷ <?php echo esc_html( $schedule ); ?></p><?php endif; ?><a class="bh-card-edit-link" href="<?php echo esc_url( add_query_arg( 'edit', $id, $management_url ) ); ?>">Edit listing <span>→</span></a></div>
                    </article>
                <?php elseif ( 'venue' === $type ) :
                    $address = bubbahub_leader_manage_card_address( $id );
                    $map = bubbahub_leader_card_map( $id );
                    $groups = get_posts( array( 'post_type'=>'group', 'post_status'=>array('publish','draft','pending','private'), 'author'=>get_current_user_id(), 'posts_per_page'=>-1, 'fields'=>'ids', 'no_found_rows'=>true ) );
                    $linked = array();
                    foreach ( $groups as $gid ) {
                        $venue = function_exists( 'bubbahub_directory_get_field' ) ? bubbahub_directory_get_field( $gid, 'venue', 0 ) : get_post_meta( $gid, 'venue', true );
                        if ( is_array( $venue ) ) $venue = isset($venue['ID']) ? $venue['ID'] : (isset($venue[0]) ? $venue[0] : 0);
                        if ( is_object( $venue ) ) $venue = isset($venue->ID) ? $venue->ID : 0;
                        if ( (int) $venue === (int) $id ) $linked[] = get_the_title( $gid );
                    }
                    ?>
                    <article class="bh-management-card bh-management-venue-card">
                        <div class="bh-management-map-preview"><?php if ( $map ) : ?><iframe title="<?php echo esc_attr( 'Map showing ' . $title ); ?>" loading="lazy" src="https://www.openstreetmap.org/export/embed.html?bbox=<?php echo esc_attr( $map['lng']-0.008 ); ?>%2C<?php echo esc_attr( $map['lat']-0.005 ); ?>%2C<?php echo esc_attr( $map['lng']+0.008 ); ?>%2C<?php echo esc_attr( $map['lat']+0.005 ); ?>&layer=mapnik&marker=<?php echo esc_attr( $map['lat'] ); ?>%2C<?php echo esc_attr( $map['lng'] ); ?>"></iframe><a class="bh-map-overlay-link" href="https://www.openstreetmap.org/?mlat=<?php echo rawurlencode($map['lat']); ?>&mlon=<?php echo rawurlencode($map['lng']); ?>" target="_blank" rel="noopener">Open map ↗</a><?php else : ?><div class="bh-map-no-location">⌖<span>No map location yet</span></div><?php endif; ?></div>
                        <div class="bh-management-card-body"><div class="bh-management-card-title-row"><h2><?php echo esc_html( $title ); ?></h2><span class="bh-management-status"><?php echo esc_html( ucfirst( $status ) ); ?></span></div><?php if ( $address ) : ?><p class="bh-management-location">⌖ <?php echo esc_html( $address ); ?></p><?php endif; ?><p class="bh-management-linked"><?php echo esc_html( count($linked) ); ?> linked <?php echo 1 === count($linked) ? 'listing' : 'listings'; ?></p><?php if ( $linked ) : ?><div class="bh-management-linked-list"><?php foreach ( array_slice($linked,0,3) as $linked_title ) : ?><span><?php echo esc_html($linked_title); ?></span><?php endforeach; ?><?php if ( count($linked) > 3 ) : ?><span>+<?php echo esc_html(count($linked)-3); ?> more</span><?php endif; ?></div><?php endif; ?><a class="bh-card-edit-link" href="<?php echo esc_url( add_query_arg( 'edit', $id, $management_url ) ); ?>">Edit venue <span>→</span></a></div>
                    </article>
                <?php else :
                    $group_id = get_post_meta( $id, '_bh_group_id', true );
                    $booking_date = get_post_meta( $id, '_bh_booking_date', true );
                    $first = get_post_meta( $id, '_bh_first_name', true );
                    $last = get_post_meta( $id, '_bh_last_name', true );
                    $email = get_post_meta( $id, '_bh_email', true );
                    $places = get_post_meta( $id, '_bh_total_places', true ) ?: 1;
                    $price = get_post_meta( $id, '_bh_total_price', true );
                    $booking_status = get_post_meta( $id, '_bh_status', true ) ?: 'pending';
                    ?>
                    <article class="bh-management-card bh-management-booking-card"><div class="bh-booking-card-top"><span class="bh-booking-icon">▣</span><span class="bh-management-status bh-status-<?php echo esc_attr($booking_status); ?>"><?php echo esc_html( ucfirst($booking_status) ); ?></span></div><div class="bh-management-card-body"><p class="bh-booking-class-label">Class / listing</p><h2><?php echo esc_html( $group_id ? get_the_title($group_id) : get_the_title($id) ); ?></h2><div class="bh-booking-details"><span>👤 <?php echo esc_html(trim($first.' '.$last)); ?></span><?php if($booking_date): ?><span>◷ <?php echo esc_html($booking_date); ?></span><?php endif; ?><span><?php echo esc_html($places); ?> <?php echo 1 === (int)$places ? 'place' : 'places'; ?></span><?php if($email): ?><span><?php echo esc_html($email); ?></span><?php endif; ?></div><?php if($price!==''): ?><strong class="bh-booking-price">£<?php echo esc_html(number_format((float)$price,2)); ?></strong><?php endif; ?><a class="bh-card-edit-link" href="<?php echo esc_url( add_query_arg( 'edit', $id, $management_url ) ); ?>">Open booking <span>→</span></a></div></article>
                <?php endif; endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function bubbahub_leader_manage_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'type' => 'listing' ), $atts, 'bubbahub_leader_manage' );
    $type = sanitize_key( $atts['type'] );
    if ( ! function_exists( 'bubbahub_leader_dashboard_is_allowed' ) || ! bubbahub_leader_dashboard_is_allowed() ) {
        return '<div class="bh-leader-message"><div class="bh-leader-message-card"><span class="bh-leader-message-icon">🔒</span><h2>Leader area</h2><p>You need an approved leader account to access this page.</p><a class="bh-leader-button" href="' . esc_url( home_url('/leader/') ) . '">Back to Dashboard</a></div></div>';
    }
    $dashboard_url = home_url( '/leader/' );
    $management_url = bubbahub_leader_management_url( $type );
    $edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;
    $new = isset( $_GET['new'] ) && '1' === sanitize_key( wp_unslash( $_GET['new'] ) );

    ob_start(); ?>
    <div class="bh-leader-management-page"><div class="bh-leader-management-shell">
    <a class="bh-back-dashboard" href="<?php echo esc_url($dashboard_url); ?>">← Back to Dashboard</a>
    <?php if ( $edit_id || $new ): ?>
      <?php if ( 'listing' === $type ): ?>
        <div class="bh-management-page-header"><div><p class="bh-leader-eyebrow">Leader area</p><h1><?php echo $edit_id ? 'Edit Listing' : 'Add Listing'; ?></h1><p>Use the same BubbaHub fields you use in the WordPress dashboard to manage your group listing.</p></div><span class="bh-management-page-icon">▦</span></div>
        <div class="bh-management-page-card"><?php if ( $edit_id ) { if ( function_exists('bubbahub_leader_owned_post') && bubbahub_leader_owned_post($edit_id,'group') ) bubbahub_leader_listing_form($edit_id); else echo '<div class="bh-form-warning">You cannot edit this listing.</div>'; } else bubbahub_leader_listing_form(); ?></div>
      <?php elseif ( 'venue' === $type ): ?>
        <div class="bh-management-page-header"><div><p class="bh-leader-eyebrow">Leader area</p><h1><?php echo $edit_id ? 'Edit Venue' : 'Add Venue'; ?></h1><p>Manage your venue using the same ACF location fields used in the WordPress dashboard.</p></div><span class="bh-management-page-icon">⌂</span></div>
        <div class="bh-management-page-card"><?php if ( $edit_id ) { if ( function_exists('bubbahub_leader_owned_post') && bubbahub_leader_owned_post($edit_id,'venue') ) bubbahub_leader_venue_form($edit_id); else echo '<div class="bh-form-warning">You cannot edit this venue.</div>'; } else bubbahub_leader_venue_form(); ?></div>
      <?php elseif ( 'booking' === $type ): ?>
        <div class="bh-management-page-header"><div><p class="bh-leader-eyebrow">Leader area</p><h1><?php echo $edit_id ? 'Edit Booking' : 'Create Booking'; ?></h1><p>Manage customer reservations and create reusable booking pages for terms, courses and programmes.</p></div><span class="bh-management-page-icon">▣</span></div>
        <?php if ( class_exists('BubbaHub_Generic_Booking_Pages') ): ?><div class="bh-management-page-card bh-generic-booking-callout"><div><p class="bh-leader-eyebrow">NEW</p><h2>Reusable booking page</h2><p>Create a customer-facing page such as <strong>Autumn Term</strong>, <strong>Spring Term</strong> or <strong>Summer Programme</strong>.</p></div><a class="bh-leader-button" href="<?php echo esc_url(add_query_arg('generic','new',bubbahub_leader_management_url('booking'))); ?>">Create booking page</a></div><?php endif; ?>
        <?php if ( isset($_GET['generic']) && 'new' === sanitize_key(wp_unslash($_GET['generic'])) && class_exists('BubbaHub_Generic_Booking_Pages') ): ?><div class="bh-management-page-card"><?php $bh_generic_booking_instance = new BubbaHub_Generic_Booking_Pages(); $bh_generic_booking_instance->form(); ?></div><?php elseif ( $edit_id ): ?><div class="bh-management-page-card"><?php $allowed=function_exists('bubbahub_leader_booking_ids')?bubbahub_leader_booking_ids():array(); if(!in_array($edit_id,$allowed,true)) echo '<div class="bh-form-warning">You cannot edit this booking.</div>'; else bubbahub_leader_booking_form($edit_id); ?></div><?php else: ?><div class="bh-management-page-card"><div class="bh-form-warning">Your customer reservations will appear here. Use <strong>Create booking</strong> above when you want to add one manually.</div></div><?php endif; ?>
      <?php endif; ?>
    <?php else:
        if ( 'booking' === $type ) {
            $items = function_exists('bubbahub_leader_booking_ids') ? bubbahub_leader_booking_ids() : array();
        } elseif ( 'venue' === $type ) {
            $items = post_type_exists('venue') ? get_posts( array( 'post_type'=>'venue', 'post_status'=>array('publish','draft','pending','private'), 'author'=>get_current_user_id(), 'posts_per_page'=>-1, 'fields'=>'ids', 'orderby'=>'title', 'order'=>'ASC', 'no_found_rows'=>true ) ) : array();
        } else {
            $items = get_posts( array( 'post_type'=>'group', 'post_status'=>array('publish','draft','pending','private'), 'author'=>get_current_user_id(), 'posts_per_page'=>-1, 'fields'=>'ids', 'orderby'=>'title', 'order'=>'ASC', 'no_found_rows'=>true ) );
        }
        echo bubbahub_leader_management_overview( $type, $items, $management_url );
    endif; ?>
    </div></div>
    <?php return ob_get_clean();
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
