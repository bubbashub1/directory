<?php
/**
 * BubbaHub Booking Engine — Booking Session admin UI.
 * Internal include loaded by the Booking Engine plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'add_meta_boxes_bh_session', 'bubbahub_booking_add_session_meta_box' );

function bubbahub_booking_add_session_meta_box( $post ) {
    add_meta_box(
        'bubbahub_booking_session_details',
        'Booking Session Details',
        'bubbahub_booking_render_session_meta_box',
        'bh_session',
        'normal',
        'high'
    );
}

function bubbahub_booking_render_session_meta_box( $post ) {
    wp_nonce_field( 'bubbahub_booking_save_session', 'bubbahub_booking_session_nonce' );

    $group_id        = absint( bubbahub_booking_meta( $post->ID, '_bh_group_id', 0 ) );
    $venue_id        = absint( bubbahub_booking_meta( $post->ID, '_bh_venue_id', 0 ) );
    $date            = bubbahub_booking_meta( $post->ID, '_bh_date', '' );
    $start_time      = bubbahub_booking_meta( $post->ID, '_bh_start_time', '' );
    $end_time        = bubbahub_booking_meta( $post->ID, '_bh_end_time', '' );
    $price           = bubbahub_booking_meta( $post->ID, '_bh_price', '' );
    $booking_method  = bubbahub_booking_meta( $post->ID, '_bh_booking_method', 'form' );
    $external_url    = bubbahub_booking_meta( $post->ID, '_bh_external_url', '' );
    $reserve_enabled = (bool) bubbahub_booking_meta( $post->ID, '_bh_reserve_enabled', false );
    $capacity        = absint( bubbahub_booking_meta( $post->ID, '_bh_capacity', 0 ) );
    $session_status  = bubbahub_booking_meta( $post->ID, '_bh_session_status', 'open' );

    $groups = get_posts( array(
        'post_type'      => 'group',
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    $venues = get_posts( array(
        'post_type'      => 'venue',
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );
    ?>
    <div class="bh-session-admin-fields">
        <p><strong>Use this panel to define one actual bookable occurrence.</strong><br>
        The Booking Engine uses these values for date selection, availability, capacity and booking actions.</p>
        <table class="form-table" role="presentation">
            <tr><th><label for="bh_group_id">Group</label></th><td>
                <select name="bh_group_id" id="bh_group_id" class="regular-text" required>
                    <option value="">Select a group</option>
                    <?php foreach ( $groups as $group ) : ?>
                        <option value="<?php echo esc_attr( $group->ID ); ?>" <?php selected( $group_id, $group->ID ); ?>><?php echo esc_html( $group->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td></tr>
            <tr><th><label for="bh_venue_id">Venue</label></th><td>
                <select name="bh_venue_id" id="bh_venue_id" class="regular-text">
                    <option value="0">No specific venue</option>
                    <?php foreach ( $venues as $venue ) : ?>
                        <option value="<?php echo esc_attr( $venue->ID ); ?>" <?php selected( $venue_id, $venue->ID ); ?>><?php echo esc_html( $venue->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td></tr>
            <tr><th><label for="bh_date">Date</label></th><td><input type="date" name="bh_date" id="bh_date" value="<?php echo esc_attr( $date ); ?>" required></td></tr>
            <tr><th><label for="bh_start_time">Start time</label></th><td><input type="time" name="bh_start_time" id="bh_start_time" value="<?php echo esc_attr( $start_time ); ?>" required></td></tr>
            <tr><th><label for="bh_end_time">End time</label></th><td><input type="time" name="bh_end_time" id="bh_end_time" value="<?php echo esc_attr( $end_time ); ?>" required></td></tr>
            <tr><th><label for="bh_price">Price</label></th><td><input type="text" name="bh_price" id="bh_price" value="<?php echo esc_attr( $price ); ?>" class="regular-text" placeholder="e.g. 5 or Free"><p class="description">Display value only for now.</p></td></tr>
            <tr><th><label for="bh_capacity">Capacity</label></th><td><input type="number" name="bh_capacity" id="bh_capacity" value="<?php echo esc_attr( $capacity ); ?>" min="0" step="1"><p class="description">Use 0 for unlimited capacity.</p></td></tr>
            <tr><th><label for="bh_booking_method">Booking method</label></th><td>
                <select name="bh_booking_method" id="bh_booking_method">
                    <option value="form" <?php selected( $booking_method, 'form' ); ?>>Bubba Hub booking form</option>
                    <option value="external" <?php selected( $booking_method, 'external' ); ?>>External booking link</option>
                    <option value="none" <?php selected( $booking_method, 'none' ); ?>>No online booking</option>
                </select>
            </td></tr>
            <tr id="bh-external-url-row"><th><label for="bh_external_url">External booking URL</label></th><td><input type="url" name="bh_external_url" id="bh_external_url" value="<?php echo esc_attr( $external_url ); ?>" class="large-text" placeholder="https://"></td></tr>
            <tr><th>Reserve Spot</th><td><label><input type="checkbox" name="bh_reserve_enabled" value="1" <?php checked( $reserve_enabled, true ); ?>> Allow users to reserve a place without immediate payment</label></td></tr>
            <tr><th><label for="bh_session_status">Session status</label></th><td>
                <select name="bh_session_status" id="bh_session_status">
                    <option value="open" <?php selected( $session_status, 'open' ); ?>>Open</option>
                    <option value="closed" <?php selected( $session_status, 'closed' ); ?>>Closed</option>
                </select>
            </td></tr>
        </table>
        <p class="description">A session must be Open, published, linked to a Group and have a valid date to appear in availability.</p>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var method = document.getElementById('bh_booking_method');
        var row = document.getElementById('bh-external-url-row');
        function toggleExternalUrl() {
            if (!method || !row) return;
            row.style.display = method.value === 'external' ? '' : 'none';
        }
        if (method) {
            method.addEventListener('change', toggleExternalUrl);
            toggleExternalUrl();
        }
    });
    </script>
    <?php
}

add_action( 'save_post_bh_session', 'bubbahub_booking_save_session_meta', 10, 3 );

function bubbahub_booking_save_session_meta( $post_id, $post, $update ) {
    if ( ! isset( $_POST['bubbahub_booking_session_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bubbahub_booking_session_nonce'] ) ), 'bubbahub_booking_save_session' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $group_id = isset( $_POST['bh_group_id'] ) ? absint( $_POST['bh_group_id'] ) : 0;
    if ( $group_id && get_post_type( $group_id ) !== 'group' ) {
        $group_id = 0;
    }
    $venue_id = isset( $_POST['bh_venue_id'] ) ? absint( $_POST['bh_venue_id'] ) : 0;
    if ( $venue_id && get_post_type( $venue_id ) !== 'venue' ) {
        $venue_id = 0;
    }

    $date       = isset( $_POST['bh_date'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_date'] ) ) : '';
    $start_time = isset( $_POST['bh_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_start_time'] ) ) : '';
    $end_time   = isset( $_POST['bh_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_end_time'] ) ) : '';
    $price      = isset( $_POST['bh_price'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_price'] ) ) : '';
    $capacity   = isset( $_POST['bh_capacity'] ) ? absint( $_POST['bh_capacity'] ) : 0;

    $booking_method = isset( $_POST['bh_booking_method'] ) ? sanitize_key( $_POST['bh_booking_method'] ) : 'form';
    if ( ! in_array( $booking_method, array( 'form', 'external', 'none' ), true ) ) {
        $booking_method = 'form';
    }

    $external_url = isset( $_POST['bh_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['bh_external_url'] ) ) : '';
    if ( 'external' !== $booking_method ) {
        $external_url = '';
    }

    $session_status = isset( $_POST['bh_session_status'] ) ? sanitize_key( $_POST['bh_session_status'] ) : 'open';
    if ( ! in_array( $session_status, array( 'open', 'closed' ), true ) ) {
        $session_status = 'open';
    }

    update_post_meta( $post_id, '_bh_group_id', $group_id );
    update_post_meta( $post_id, '_bh_venue_id', $venue_id );
    update_post_meta( $post_id, '_bh_date', $date );
    update_post_meta( $post_id, '_bh_start_time', $start_time );
    update_post_meta( $post_id, '_bh_end_time', $end_time );
    update_post_meta( $post_id, '_bh_price', $price );
    update_post_meta( $post_id, '_bh_booking_method', $booking_method );
    update_post_meta( $post_id, '_bh_external_url', $external_url );
    update_post_meta( $post_id, '_bh_reserve_enabled', isset( $_POST['bh_reserve_enabled'] ) ? 1 : 0 );
    update_post_meta( $post_id, '_bh_capacity', $capacity );
    update_post_meta( $post_id, '_bh_session_status', $session_status );

    $datetime_sort = '';
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        $datetime_sort = $date . ' ' . ( $start_time ? $start_time : '00:00' );
    }
    update_post_meta( $post_id, '_bh_datetime_sort', $datetime_sort );
}
