<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_leader_booking_ids() {
    if ( ! post_type_exists( 'bh_booking' ) ) return array();
    $groups = get_posts( array( 'post_type' => 'group', 'post_status' => 'any', 'author' => get_current_user_id(), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
    if ( ! $groups ) return array();
    $q = new WP_Query( array( 'post_type' => 'bh_booking', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => array( array( 'key' => '_bh_group_id', 'value' => $groups, 'compare' => 'IN' ) ) ) );
    return $q->posts;
}

function bubbahub_leader_booking_form( $booking_id = 0 ) {
    $meta = function( $key ) use ( $booking_id ) { return $booking_id ? get_post_meta( $booking_id, $key, true ) : ''; };
    $groups = get_posts( array( 'post_type' => 'group', 'post_status' => array( 'publish','draft','pending','private' ), 'author' => get_current_user_id(), 'posts_per_page' => -1, 'fields' => 'ids' ) );
    ?>
    <form class="bh-leader-form" method="post">
        <input type="hidden" name="bh_leader_action" value="save_booking"><input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking_id ); ?>">
        <?php wp_nonce_field( 'bh_leader_save_booking', 'bh_leader_booking_nonce' ); ?>
        <p><label>Listing</label><select name="group_id" required><option value="">Select listing</option><?php foreach ( $groups as $gid ) : ?><option value="<?php echo esc_attr( $gid ); ?>" <?php selected( $meta('_bh_group_id'), $gid ); ?>><?php echo esc_html( get_the_title( $gid ) ); ?></option><?php endforeach; ?></select></p>
        <p><label>Customer first name</label><input type="text" name="first_name" value="<?php echo esc_attr( $meta('_bh_first_name') ); ?>" required></p>
        <p><label>Customer last name</label><input type="text" name="last_name" value="<?php echo esc_attr( $meta('_bh_last_name') ); ?>" required></p>
        <p><label>Customer email</label><input type="email" name="email" value="<?php echo esc_attr( $meta('_bh_email') ); ?>" required></p>
        <p><label>Booking date</label><input type="date" name="booking_date" value="<?php echo esc_attr( $meta('_bh_booking_date') ); ?>"></p>
        <p><label>Places</label><input type="number" min="1" name="total_places" value="<?php echo esc_attr( $meta('_bh_total_places') ?: 1 ); ?>"></p>
        <p><label>Total price</label><input type="number" min="0" step="0.01" name="total_price" value="<?php echo esc_attr( $meta('_bh_total_price') ); ?>"></p>
        <p><label>Status</label><select name="booking_status"><?php foreach ( array( 'pending'=>'Pending','confirmed'=>'Confirmed','cancelled'=>'Cancelled','completed'=>'Completed' ) as $v => $label ) : ?><option value="<?php echo esc_attr( $v ); ?>" <?php selected( $meta('_bh_status') ?: 'pending', $v ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></p>
        <button class="bh-leader-button" type="submit"><?php echo $booking_id ? 'Save booking' : 'Add booking'; ?></button>
    </form>
    <?php
}

function bubbahub_leader_handle_booking_save() {
    if ( empty( $_POST['bh_leader_action'] ) || 'save_booking' !== $_POST['bh_leader_action'] || ! is_user_logged_in() ) return;
    if ( empty( $_POST['bh_leader_booking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_leader_booking_nonce'] ) ), 'bh_leader_save_booking' ) ) return;

    if ( ! function_exists( 'bubbahub_leader_dashboard_is_allowed' ) || ! bubbahub_leader_dashboard_is_allowed() ) wp_die( 'You are not authorised to manage leader bookings.' );

    $booking_id = absint( $_POST['booking_id'] ?? 0 );
    $group_id   = absint( $_POST['group_id'] ?? 0 );
    if ( ! bubbahub_leader_owned_post( $group_id, 'group' ) ) wp_die( 'You cannot create a booking for this listing.' );
    if ( $booking_id && ! in_array( $booking_id, bubbahub_leader_booking_ids(), true ) ) wp_die( 'You cannot edit this booking.' );

    $first = sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) );
    $last  = sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) );
    $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    if ( ! $first || ! $last || ! is_email( $email ) ) wp_die( 'Please provide a valid customer name and email address.' );
    $data = array( 'post_type' => 'bh_booking', 'post_status' => 'publish', 'post_title' => trim( $first . ' ' . $last ) );

    if ( $booking_id ) {
        $data['ID'] = $booking_id;
        $saved = wp_update_post( wp_slash( $data ), true );
    } else {
        $data['post_author'] = get_current_user_id();
        $saved = wp_insert_post( wp_slash( $data ), true );
    }
    if ( is_wp_error( $saved ) ) return;

    $price = isset( $_POST['total_price'] ) ? (float) $_POST['total_price'] : 0;
    $map = array(
        '_bh_group_id' => $group_id,
        '_bh_first_name' => $first,
        '_bh_last_name' => $last,
        '_bh_email' => $email,
        '_bh_booking_date' => sanitize_text_field( wp_unslash( $_POST['booking_date'] ?? '' ) ),
        '_bh_total_places' => max( 1, absint( $_POST['total_places'] ?? 1 ) ),
        '_bh_total_price' => number_format( max( 0, $price ), 2, '.', '' ),
        '_bh_status' => sanitize_key( $_POST['booking_status'] ?? 'pending' ),
        '_bh_payment_status' => 'pending',
    );
    foreach ( $map as $key => $value ) update_post_meta( $saved, $key, $value );

    wp_safe_redirect( add_query_arg( 'booking_saved', '1', home_url( '/leader/#bookings' ) ) );
    exit;
}
add_action( 'template_redirect', 'bubbahub_leader_handle_booking_save' );
