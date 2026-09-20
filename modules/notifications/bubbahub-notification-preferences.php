<?php
/**
 * BubbaHub Notification Preferences.
 *
 * User notification channels, portal notification feed and a reusable
 * notification API for class, booking, digest and community updates.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BUBBAHUB_NOTIFICATIONS_VERSION' ) ) define( 'BUBBAHUB_NOTIFICATIONS_VERSION', '1.0.0' );

function bubbahub_notification_defaults() {
    return array(
        'class_booking'       => 1,
        'booking_reminders' => 1,
        'saved_groups'      => 1,
        'planner_reminders' => 1,
        'calendar_reminders' => 1,
        'messages'          => 1,
        'email_digest'      => 1,
        'sms_reminders'     => 0,
        'community'         => 1,
        'new_groups'        => 1,
        'group_updates'    => 1,
        'new_suggestions'  => 1,
        'new_classes'      => 1,
        'whats_on'         => 1,
        'channel_email'    => 1,
        'channel_in_hub'   => 1,
        'channel_push'     => 0,
        'channel_sms'      => 0,
    );
}

function bubbahub_notification_preferences( $user_id = 0 ) {
    $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
    if ( ! $user_id ) return bubbahub_notification_defaults();

    $saved = get_user_meta( $user_id, 'bubbahub_notification_preferences', true );
    $defaults = bubbahub_notification_defaults();
    if ( ! is_array( $saved ) ) $saved = array();

    return array(
        'class_booking'       => isset( $saved['class_booking'] ) ? (int) (bool) $saved['class_booking'] : $defaults['class_booking'],
        'booking_reminders' => isset( $saved['booking_reminders'] ) ? (int) (bool) $saved['booking_reminders'] : $defaults['booking_reminders'],
        'saved_groups'      => isset( $saved['saved_groups'] ) ? (int) (bool) $saved['saved_groups'] : $defaults['saved_groups'],
        'planner_reminders' => isset( $saved['planner_reminders'] ) ? (int) (bool) $saved['planner_reminders'] : $defaults['planner_reminders'],
        'calendar_reminders' => isset( $saved['calendar_reminders'] ) ? (int) (bool) $saved['calendar_reminders'] : $defaults['calendar_reminders'],
        'messages'          => isset( $saved['messages'] ) ? (int) (bool) $saved['messages'] : $defaults['messages'],
        'email_digest'      => isset( $saved['email_digest'] ) ? (int) (bool) $saved['email_digest'] : $defaults['email_digest'],
        'sms_reminders'     => 0,
        'community'         => isset( $saved['community'] ) ? (int) (bool) $saved['community'] : $defaults['community'],
        'new_groups'        => isset( $saved['new_groups'] ) ? (int) (bool) $saved['new_groups'] : $defaults['new_groups'],
        'group_updates'    => isset( $saved['group_updates'] ) ? (int) (bool) $saved['group_updates'] : $defaults['group_updates'],
        'new_suggestions'  => isset( $saved['new_suggestions'] ) ? (int) (bool) $saved['new_suggestions'] : $defaults['new_suggestions'],
        'new_classes'      => isset( $saved['new_classes'] ) ? (int) (bool) $saved['new_classes'] : $defaults['new_classes'],
        'whats_on'         => isset( $saved['whats_on'] ) ? (int) (bool) $saved['whats_on'] : $defaults['whats_on'],
        'channel_email'    => isset( $saved['channel_email'] ) ? (int) (bool) $saved['channel_email'] : $defaults['channel_email'],
        'channel_in_hub'   => isset( $saved['channel_in_hub'] ) ? (int) (bool) $saved['channel_in_hub'] : $defaults['channel_in_hub'],
        'channel_push'     => isset( $saved['channel_push'] ) ? (int) (bool) $saved['channel_push'] : $defaults['channel_push'],
        'channel_sms'      => 0,
    );
}

function bubbahub_notification_save_preferences( $user_id, $preferences ) {
    $defaults = bubbahub_notification_defaults();
    $clean = array();
    foreach ( $defaults as $key => $default ) {
        $clean[ $key ] = ( 'sms_reminders' === $key ) ? 0 : ( ! empty( $preferences[ $key ] ) ? 1 : 0 );
    }
    update_user_meta( absint( $user_id ), 'bubbahub_notification_preferences', $clean );
    return $clean;
}

function bubbahub_notification_handle_preferences() {
    if ( ! is_user_logged_in() || empty( $_POST['bubbahub_notification_action'] ) ) return;

    $nonce = isset( $_POST['bubbahub_notification_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['bubbahub_notification_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'bubbahub_notification_preferences' ) ) return;

    $prefs = bubbahub_notification_save_preferences( get_current_user_id(), isset( $_POST['notification'] ) && is_array( $_POST['notification'] ) ? wp_unslash( $_POST['notification'] ) : array() );

    if ( ! empty( $prefs['sms_reminders'] ) ) {
        $phone = isset( $_POST['notification_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['notification_phone'] ) ) : '';
        if ( $phone ) update_user_meta( get_current_user_id(), 'bubbahub_notification_phone', $phone );
    }

    wp_safe_redirect( add_query_arg( 'bh_notifications_saved', '1', wp_get_referer() ?: home_url( '/account/' ) ) );
    exit;
}
add_action( 'template_redirect', 'bubbahub_notification_handle_preferences' );

function bubbahub_notification_group_matches_user( $group_id, $user_id ) {
    $interests = (array) get_user_meta( $user_id, 'bubbahub_user_interest', true );
    $locations = (array) get_user_meta( $user_id, 'bubbahub_preferred_locations', true );
    if ( ! $interests ) {
        $legacy = get_user_meta( $user_id, 'User_interest', true );
        if ( is_string( $legacy ) ) $interests = array_filter( array_map( 'trim', preg_split( '/[,\\n]+/', $legacy ) ) );
    }
    $haystack = strtolower( get_the_title( $group_id ) . ' ' . wp_strip_all_tags( get_post_field( 'post_content', $group_id ) ) );
    foreach ( array_merge( $interests, $locations ) as $term ) {
        $term = strtolower( trim( (string) $term ) );
        if ( $term && false !== strpos( $haystack, $term ) ) return true;
    }
    $region_terms = wp_get_post_terms( $group_id, 'region', array( 'fields' => 'names' ) );
    if ( ! is_wp_error( $region_terms ) ) {
        foreach ( $locations as $location ) foreach ( $region_terms as $region ) {
            if ( false !== stripos( $region, (string) $location ) || false !== stripos( (string) $location, $region ) ) return true;
        }
    }
    return empty( $interests ) && empty( $locations );
}

function bubbahub_notification_group_family_alert( $group_id, $is_update ) {
    if ( 'group' !== get_post_type( $group_id ) || 'publish' !== get_post_status( $group_id ) ) return;
    if ( wp_is_post_revision( $group_id ) || wp_is_post_autosave( $group_id ) ) return;
    if ( function_exists( 'bubbahub_directory_notification_is_import' ) && bubbahub_directory_notification_is_import() ) return;
    $users = get_users( array( 'fields' => array( 'ID' ), 'role__not_in' => array( 'administrator' ), 'number' => 5000 ) );
    foreach ( $users as $user ) {
        $uid = absint( $user->ID );
        if ( ! bubbahub_notification_group_matches_user( $group_id, $uid ) ) continue;
        $type = $is_update ? 'group_update' : 'new_group';
        $enabled = bubbahub_notification_preferences( $uid );
        if ( $is_update && empty( $enabled['group_updates'] ) ) continue;
        if ( ! $is_update && empty( $enabled['new_groups'] ) ) continue;
        $name = get_the_title( $group_id );
        $url = get_permalink( $group_id );
        $message = $is_update
            ? 'A group that may be relevant to your family has been updated: ' . $name . '.'
            : 'A new group that may be relevant to your family has been added to Bubba Hub: ' . $name . '.';
        bubbahub_notify_user( $uid, $type, $is_update ? 'Group updated – ' . $name : 'New group – ' . $name, $message, $url );
    }
}
add_action( 'transition_post_status', function( $new_status, $old_status, $post ) {
    if ( ! $post || 'group' !== $post->post_type || 'publish' !== $new_status ) return;
    bubbahub_notification_group_family_alert( $post->ID, 'publish' === $old_status );
}, 60, 3 );

add_action( 'save_post_group', function( $post_id, $post, $update ) {
    if ( ! $update || ! $post || 'publish' !== $post->post_status ) return;
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
    bubbahub_notification_group_family_alert( $post_id, true );
}, 100, 3 );

function bubbahub_notification_log( $user_id, $type, $title, $message, $url = '' ) {
    $user_id = absint( $user_id );
    if ( ! $user_id ) return 0;

    $items = get_user_meta( $user_id, 'bubbahub_notification_feed', true );
    if ( ! is_array( $items ) ) $items = array();

    array_unshift( $items, array(
        'id'      => wp_generate_uuid4(),
        'type'    => sanitize_key( $type ),
        'title'   => sanitize_text_field( $title ),
        'message' => wp_strip_all_tags( $message ),
        'url'     => esc_url_raw( $url ),
        'time'    => current_time( 'mysql' ),
        'read'    => 0,
    ) );

    $items = array_slice( $items, 0, 50 );
    update_user_meta( $user_id, 'bubbahub_notification_feed', $items );
    return $items[0]['id'];
}

function bubbahub_notification_email_enabled( $user_id, $type ) {
    $prefs = bubbahub_notification_preferences( $user_id );
    if ( in_array( $type, array( 'class_booking', 'booking' ), true ) ) return ! empty( $prefs['class_booking'] );
    if ( in_array( $type, array( 'booking_reminder', 'reminder' ), true ) ) return ! empty( $prefs['booking_reminders'] );
    if ( in_array( $type, array( 'saved_group', 'group_update' ), true ) ) return ! empty( $prefs['saved_groups'] );
    if ( in_array( $type, array( 'calendar', 'calendar_reminder', 'calendar_event' ), true ) ) return ! empty( $prefs['calendar_reminders'] );
    if ( in_array( $type, array( 'planner', 'planner_reminder' ), true ) ) return ! empty( $prefs['planner_reminders'] );
    if ( in_array( $type, array( 'message', 'support' ), true ) ) return ! empty( $prefs['messages'] );
    if ( 'community' === $type ) return ! empty( $prefs['community'] );
    if ( 'new_group' === $type ) return ! empty( $prefs['new_groups'] );
    if ( 'group_update' === $type ) return ! empty( $prefs['group_updates'] );
    if ( 'new_suggestion' === $type ) return ! empty( $prefs['new_suggestions'] );
    if ( 'new_class' === $type ) return ! empty( $prefs['new_classes'] );
    if ( 'whats_on' === $type ) return ! empty( $prefs['whats_on'] );
    return ! empty( $prefs['email_digest'] );
}

function bubbahub_notify_user( $user_id, $type, $title, $message, $url = '', $options = array() ) {
    $user_id = absint( $user_id );
    $user = $user_id ? get_userdata( $user_id ) : false;
    if ( ! $user ) return false;

    $options = wp_parse_args( $options, array(
        'email' => true,
        'sms'   => false,
        'portal' => true,
        'subject' => $title,
    ) );

    if ( $options['portal'] && bubbahub_notification_preferences( $user_id )['channel_in_hub'] ) bubbahub_notification_log( $user_id, $type, $title, $message, $url );

    $sent = false;
    if ( $options['email'] && bubbahub_notification_preferences( $user_id )['channel_email'] && bubbahub_notification_email_enabled( $user_id, $type ) && is_email( $user->user_email ) ) {
        $body = "Hi {$user->display_name},\n\n{$message}\n\n";
        if ( $url ) $body .= "View this in your Bubba Hub account:\n{$url}\n\n";
        $body .= "Bubba Hub";
        $sent = wp_mail( $user->user_email, $options['subject'], $body );
    }

    // SMS is currently disabled across Bubba Hub. Legacy SMS preferences are retained but sending is blocked.
    if ( false && $options['sms'] && bubbahub_notification_preferences( $user_id )['sms_reminders'] ) {
        $phone = get_user_meta( $user_id, 'bubbahub_notification_phone', true );
        if ( $phone ) {
            do_action( 'bubbahub_send_sms_notification', $phone, $title, $message, $user_id );
        }
    }

    return $sent;
}


function bubbahub_notification_upcoming_reminders() {
    $now = current_time( 'timestamp' );
    $until = $now + ( 7 * DAY_IN_SECONDS );
    $sessions = get_posts( array(
        'post_type' => 'bh_session', 'post_status' => 'publish', 'posts_per_page' => 250,
        'fields' => 'ids', 'no_found_rows' => true,
        'meta_query' => array( array( 'key' => '_bh_date', 'compare' => 'EXISTS' ) ),
    ) );
    foreach ( $sessions as $session_id ) {
        $date = sanitize_text_field( get_post_meta( $session_id, '_bh_date', true ) );
        $start = sanitize_text_field( get_post_meta( $session_id, '_bh_start_time', true ) );
        if ( ! $date || ! $start ) continue;
        $timestamp = strtotime( $date . ' ' . $start );
        if ( ! $timestamp || $timestamp < $now || $timestamp > $until ) continue;

        $bookings = get_posts( array(
            'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => -1,
            'fields' => 'ids', 'no_found_rows' => true,
            'meta_query' => array(
                array( 'key' => '_bh_session_id', 'value' => absint( $session_id ) ),
                array( 'key' => '_bh_status', 'value' => array( 'confirmed', 'reserved' ), 'compare' => 'IN' ),
            ),
        ) );
        foreach ( $bookings as $booking_id ) {
            $user_id = absint( get_post_meta( $booking_id, '_bh_user_id', true ) );
            if ( ! $user_id ) continue;
            $days = $timestamp - $now <= 2 * DAY_IN_SECONDS ? '24-hour' : '7-day';
            $sent_key = '_bh_notification_' . $days . '_reminder_sent';
            if ( get_post_meta( $booking_id, $sent_key, true ) ) continue;

            $group_id = absint( get_post_meta( $session_id, '_bh_group_id', true ) );
            $name = $group_id ? get_the_title( $group_id ) : get_the_title( $session_id );
            $venue_id = absint( get_post_meta( $session_id, '_bh_venue_id', true ) );
            $venue = $venue_id ? get_the_title( $venue_id ) : '';
            $date_text = wp_date( 'l, j F Y', $timestamp );
            $time_text = wp_date( 'g:i A', $timestamp );
            $message = $days === '24-hour'
                ? sprintf( 'Reminder: %s is tomorrow at %s%s.', $name, $time_text, $venue ? ' at ' . $venue : '' )
                : sprintf( 'Your booking for %s is coming up on %s at %s%s.', $name, $date_text, $time_text, $venue ? ' at ' . $venue : '' );
            $url = home_url( '/my-hub/' );
            bubbahub_notify_user( $user_id, 'booking', $name . ' – ' . ( $days === '24-hour' ? 'tomorrow' : 'upcoming class' ), $message, $url, array( 'sms' => true ) );
            update_post_meta( $booking_id, $sent_key, current_time( 'mysql' ) );
        }
    }
}
function bubbahub_notification_booking_status_changed( $meta_id, $booking_id, $meta_key, $meta_value ) {
    if ( '_bh_status' !== $meta_key || 'bh_booking' !== get_post_type( $booking_id ) ) return;
    $user_id = absint( get_post_meta( $booking_id, '_bh_user_id', true ) );
    if ( ! $user_id ) return;
    $status = sanitize_key( $meta_value );
    $group_id = absint( get_post_meta( $booking_id, '_bh_group_id', true ) );
    $name = $group_id ? get_the_title( $group_id ) : 'your class';
    $url = home_url( '/my-hub/' );

    if ( 'cancelled' === $status ) {
        $key = '_bh_notification_cancelled_sent';
        if ( get_post_meta( $booking_id, $key, true ) ) return;
        bubbahub_notify_user( $user_id, 'booking', 'Booking cancelled – ' . $name, 'Your booking for ' . $name . ' has been cancelled. Please check My Hub for the latest details.', $url, array( 'sms' => true ) );
        update_post_meta( $booking_id, $key, current_time( 'mysql' ) );
    } elseif ( 'confirmed' === $status ) {
        $key = '_bh_notification_confirmed_sent';
        if ( get_post_meta( $booking_id, $key, true ) ) return;
        bubbahub_notify_user( $user_id, 'booking', 'Booking confirmed – ' . $name, 'Your booking for ' . $name . ' has been confirmed. You can view the latest details in My Hub.', $url );
        update_post_meta( $booking_id, $key, current_time( 'mysql' ) );
    }
}
add_action( 'added_post_meta', 'bubbahub_notification_booking_status_changed', 30, 4 );
add_action( 'updated_post_meta', 'bubbahub_notification_booking_status_changed', 30, 4 );

function bubbahub_notification_session_changed( $meta_id, $session_id, $meta_key, $meta_value ) {
    if ( 'bh_session' !== get_post_type( $session_id ) || ! in_array( $meta_key, array( '_bh_date', '_bh_start_time', '_bh_end_time', '_bh_venue_id', '_bh_session_status' ), true ) ) return;
    $bookings = get_posts( array(
        'post_type' => 'bh_booking', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_query' => array( array( 'key' => '_bh_session_id', 'value' => absint( $session_id ) ) ),
    ) );
    foreach ( $bookings as $booking_id ) {
        $user_id = absint( get_post_meta( $booking_id, '_bh_user_id', true ) );
        if ( ! $user_id ) continue;
        $last = get_post_meta( $booking_id, '_bh_notification_session_change_' . sanitize_key( $meta_key ), true );
        $stamp = current_time( 'mysql' );
        if ( $last === $stamp ) continue;
        $group_id = absint( get_post_meta( $session_id, '_bh_group_id', true ) );
        $name = $group_id ? get_the_title( $group_id ) : get_the_title( $session_id );
        $message = 'There has been an update to your booked session for ' . $name . '. Please check My Hub for the latest date, time or venue details.';
        bubbahub_notify_user( $user_id, 'booking', 'Update to your booked class – ' . $name, $message, home_url( '/my-hub/' ), array( 'sms' => true, 'sms_emergency' => true ) );
        update_post_meta( $booking_id, '_bh_notification_session_change_' . sanitize_key( $meta_key ), $stamp );
    }
}
add_action( 'updated_post_meta', 'bubbahub_notification_session_changed', 40, 4 );

function bubbahub_notification_schedule_reminders() {
    if ( ! wp_next_scheduled( 'bubbahub_notification_reminder_cron' ) ) {
        wp_schedule_event( time() + 300, 'hourly', 'bubbahub_notification_reminder_cron' );
    }
}
add_action( 'init', 'bubbahub_notification_schedule_reminders' );
add_action( 'bubbahub_notification_reminder_cron', 'bubbahub_notification_upcoming_reminders' );

function bubbahub_notification_booking_created( $new_status, $old_status, $post ) {
    if ( ! $post || 'bh_booking' !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status ) return;
    $user_id = absint( get_post_meta( $post->ID, '_bh_user_id', true ) );
    if ( ! $user_id || get_post_meta( $post->ID, '_bh_notification_created_sent', true ) ) return;
    $group_id = absint( get_post_meta( $post->ID, '_bh_group_id', true ) );
    $session_id = absint( get_post_meta( $post->ID, '_bh_session_id', true ) );
    $name = $group_id ? get_the_title( $group_id ) : get_the_title( $session_id );
    bubbahub_notify_user( $user_id, 'booking', 'Booking confirmed – ' . $name, 'Your Bubba Hub booking has been recorded. You can view your booking and its latest status in My Hub.', home_url( '/my-hub/' ) );
    update_post_meta( $post->ID, '_bh_notification_created_sent', current_time( 'mysql' ) );
}
add_action( 'transition_post_status', 'bubbahub_notification_booking_created', 20, 3 );

function bubbahub_notification_admin_menu() {
    add_submenu_page( 'options-general.php', 'Bubba Hub Notifications', 'Bubba Hub Notifications', 'manage_options', 'bubbahub-notifications', 'bubbahub_notification_admin_page' );
}
add_action( 'admin_menu', 'bubbahub_notification_admin_menu' );

function bubbahub_notification_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! empty( $_POST['bh_notification_admin_action'] ) && check_admin_referer( 'bubbahub_notification_admin', 'bh_notification_admin_nonce' ) ) {
        update_option( 'bubbahub_notification_admin_email_from_name', sanitize_text_field( wp_unslash( $_POST['from_name'] ?? 'Bubba Hub' ) ) );
        update_option( 'bubbahub_notification_digest_frequency', sanitize_key( $_POST['digest_frequency'] ?? 'weekly' ) );
        echo '<div class="notice notice-success is-dismissible"><p>Bubba Hub notification settings saved.</p></div>';
    }
    $from = get_option( 'bubbahub_notification_admin_email_from_name', 'Bubba Hub' );
    $frequency = get_option( 'bubbahub_notification_digest_frequency', 'weekly' );
    ?>
    <div class="wrap">
        <h1>Bubba Hub Notifications</h1>
        <p>Manage the notification framework used by family accounts. Essential transactional emails remain separate from optional preferences.</p>
        <form method="post">
            <?php wp_nonce_field( 'bubbahub_notification_admin', 'bh_notification_admin_nonce' ); ?>
            <input type="hidden" name="bh_notification_admin_action" value="save">
            <table class="form-table">
                <tr><th scope="row"><label for="bh-from-name">Email sender name</label></th><td><input class="regular-text" id="bh-from-name" name="from_name" value="<?php echo esc_attr( $from ); ?>"></td></tr>
                <tr><th scope="row"><label for="bh-digest">Digest frequency</label></th><td><select id="bh-digest" name="digest_frequency"><option value="daily" <?php selected( $frequency, 'daily' ); ?>>Daily</option><option value="weekly" <?php selected( $frequency, 'weekly' ); ?>>Weekly</option></select></td></tr>
            </table>
            <?php submit_button( 'Save notification settings' ); ?>
        </form>
        <h2>Notification types</h2>
        <ul>
            <li>Class &amp; booking alerts — confirmations, changes and reminders</li>
            <li>Support — specialist questions and replies</li>
            <li>Community — relevant local updates</li>
            <li>Email digest — future daily/weekly round-up</li>
            <li>SMS — currently disabled; no SMS notifications are sent</li>
        </ul>
    </div>
    <?php
}

function bubbahub_notification_preferences_shortcode() {
    if ( ! is_user_logged_in() ) return '<p>Please log in to manage your notification preferences.</p>';

    $prefs = bubbahub_notification_preferences();
    $phone = get_user_meta( get_current_user_id(), 'bubbahub_notification_phone', true );

    ob_start(); ?>
    <section class="bh-notification-centre" aria-labelledby="bh-notification-title">
        <div class="bh-notification-header">
            <div>
                <span class="bh-notification-kicker">YOUR NOTIFICATIONS</span>
                <h2 id="bh-notification-title">Notification Preferences</h2>
                <p>Choose the updates you want from Bubba Hub. You can change these settings at any time.</p>
            </div>
        </div>

        <?php if ( ! empty( $_GET['bh_notifications_saved'] ) ) : ?>
            <div class="bh-notification-success">✓ Your notification preferences have been saved.</div>
        <?php endif; ?>

        <form method="post" class="bh-notification-form">
            <?php wp_nonce_field( 'bubbahub_notification_preferences', 'bubbahub_notification_nonce' ); ?>
            <input type="hidden" name="bubbahub_notification_action" value="save">

            <div class="bh-notification-section-heading"><span>📣</span><div><h3>How would you like to hear from us?</h3><p>Choose the ways Bubba Hub can contact you. You can use more than one.</p></div></div>

            <label class="bh-notification-channel">
                <span class="bh-notification-channel-icon">✉️</span>
                <span class="bh-notification-copy"><strong>Email alerts</strong><small>Receive optional Bubba Hub alerts and reminders by email.</small></span>
                <input type="checkbox" name="notification[channel_email]" value="1" <?php checked( $prefs['channel_email'], 1 ); ?>>
                <span class="bh-notification-toggle" aria-hidden="true"></span>
            </label>
            <label class="bh-notification-channel">
                <span class="bh-notification-channel-icon">🔔</span>
                <span class="bh-notification-copy"><strong>Notifications in My Hub</strong><small>Show relevant alerts in your Bubba Hub notification centre.</small></span>
                <input type="checkbox" name="notification[channel_in_hub]" value="1" <?php checked( $prefs['channel_in_hub'], 1 ); ?>>
                <span class="bh-notification-toggle" aria-hidden="true"></span>
            </label>
            <div class="bh-notification-channel bh-notification-channel-disabled">
                <span class="bh-notification-channel-icon">📲</span>
                <span class="bh-notification-copy"><strong>Push notifications</strong><small>Browser/app push notifications will be available when push delivery is enabled for your device.</small></span>
                <span class="bh-notification-status">Coming soon</span>
            </div>
            <div class="bh-notification-channel bh-notification-channel-disabled">
                <span class="bh-notification-channel-icon">💬</span>
                <span class="bh-notification-copy"><strong>SMS alerts</strong><small>SMS reminders will be available when SMS delivery is enabled for your account.</small></span>
                <span class="bh-notification-status">Coming soon</span>
            </div>

            <div class="bh-notification-section-heading bh-notification-section-heading-spaced"><span>🧩</span><div><h3>What would you like to be told about?</h3><p>Turn individual types of optional notification on or off.</p></div></div>

            <label class="bh-notification-option"><span class="bh-notification-icon">📅</span><span class="bh-notification-copy"><strong>Class &amp; booking alerts</strong><small>Updates about bookings, confirmations and relevant class activity.</small></span><input type="checkbox" name="notification[class_booking]" value="1" <?php checked( $prefs['class_booking'], 1 ); ?>><span class="bh-notification-toggle" aria-hidden="true"></span></label>
            <label class="bh-notification-option"><span class="bh-notification-icon">✉️</span><span class="bh-notification-copy"><strong>Email digest</strong><small>Receive a summary of relevant Bubba Hub updates by email.</small></span><input type="checkbox" name="notification[email_digest]" value="1" <?php checked( $prefs['email_digest'], 1 ); ?>><span class="bh-notification-toggle" aria-hidden="true"></span></label>
            <label class="bh-notification-option"><span class="bh-notification-icon">⏰</span><span class="bh-notification-copy"><strong>Booking reminders</strong><small>Get reminders before your upcoming booked classes.</small></span><input type="checkbox" name="notification[booking_reminders]" value="1" <?php checked( $prefs['booking_reminders'], 1 ); ?>><span class="bh-notification-toggle" aria-hidden="true"></span></label>
            <label class="bh-notification-option"><span class="bh-notification-icon">❤️</span><span class="bh-notification-copy"><strong>Saved group updates</strong><small>Hear about changes and useful updates from groups you follow or save.</small></span><input type="checkbox" name="notification[saved_groups]" value="1" <?php checked( $prefs['saved_groups'], 1 ); ?>><span class="bh-notification-toggle" aria-hidden="true"></span></label>
            <label class="bh-notification-option"><span class="bh-notification-icon">🗓️</span><span class="bh-notification-copy"><strong>Planner &amp; calendar reminders</strong><small>Receive reminders for activities and events in your family planner and calendar.</small></span><input type="checkbox" name="notification[planner_reminders]" value="1" <?php checked( $prefs['planner_reminders'], 1 ); ?>><span class="bh-notification-toggle" aria-hidden="true"></span></label>
            <label class="bh-notification-option"><span class="bh-notification-icon">💬</span><span class="bh-notification-copy"><strong>Messages &amp; support</strong><small>Be notified when a specialist or support contact replies to you.</small></span><input type="checkbox" name="notification[messages]" value="1" <?php checked( $prefs['messages'], 1 ); ?>><span class="bh-notification-toggle" aria-hidden="true"></span></label>
            <label class="bh-notification-option"><span class="bh-notification-icon">🏡</span><span class="bh-notification-copy"><strong>Community alerts</strong><small>Receive relevant Bubba Hub community updates.</small></span><input type="checkbox" name="notification[community]" value="1" <?php checked( $prefs['community'], 1 ); ?>><span class="bh-notification-toggle" aria-hidden="true"></span></label>
        <button type="submit" class="bh-notification-save">Save notification preferences</button>
        </form>

        <div class="bh-notification-note"><strong>Important:</strong> Essential account, booking and payment emails may still be sent when needed to complete or manage a transaction.</div>
    </section>
    <?php return ob_get_clean();
}
add_shortcode( 'bubbahub_notification_preferences', 'bubbahub_notification_preferences_shortcode' );

function bubbahub_notification_feed_shortcode() {
    if ( ! is_user_logged_in() ) return '';

    $items = get_user_meta( get_current_user_id(), 'bubbahub_notification_feed', true );
    if ( ! is_array( $items ) ) $items = array();

    ob_start(); ?>
    <section class="bh-notification-feed">
        <div class="bh-notification-feed-head"><div><span class="bh-notification-kicker">BUBBA HUB</span><h2>Your notifications</h2></div></div>
        <?php if ( ! $items ) : ?>
            <div class="bh-notification-empty">You’re all caught up. New relevant updates will appear here.</div>
        <?php else : foreach ( $items as $item ) : ?>
            <article class="bh-notification-item <?php echo empty( $item['read'] ) ? 'is-new' : ''; ?>">
                <div class="bh-notification-item-icon"><?php echo 'booking' === ( $item['type'] ?? '' ) ? '📅' : ( 'community' === ( $item['type'] ?? '' ) ? '🏡' : '🔔' ); ?></div>
                <div><strong><?php echo esc_html( $item['title'] ?? '' ); ?></strong><p><?php echo esc_html( $item['message'] ?? '' ); ?></p><small><?php echo esc_html( $item['time'] ?? '' ); ?></small><?php if ( ! empty( $item['url'] ) ) : ?><a href="<?php echo esc_url( $item['url'] ); ?>">View update →</a><?php endif; ?></div>
            </article>
        <?php endforeach; endif; ?>
    </section>
    <?php return ob_get_clean();
}
add_shortcode( 'bubbahub_notification_feed', 'bubbahub_notification_feed_shortcode' );

function bubbahub_notification_assets() {
    if ( ! is_user_logged_in() ) return;
    wp_register_style( 'bubbahub-notifications', false, array(), BUBBAHUB_NOTIFICATIONS_VERSION );
    wp_enqueue_style( 'bubbahub-notifications' );
    wp_add_inline_style( 'bubbahub-notifications', '
        .bh-notification-centre{margin:30px 0;padding:28px;background:#fff;border:1px solid #e1e9e4;border-radius:24px;box-shadow:0 7px 22px rgba(27,64,52,.06)}
        .bh-notification-header{margin-bottom:20px}.bh-notification-kicker{display:inline-flex;border-radius:999px;padding:5px 10px;background:#dff3df;color:#23583f;font-size:10px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.bh-notification-header h2,.bh-notification-feed h2{margin:7px 0 5px;color:#173f32;font-size:26px}.bh-notification-header p{margin:0;color:#65776f;line-height:1.55}
        .bh-notification-section-heading{display:flex;gap:12px;align-items:flex-start;margin:4px 0 8px;padding:14px 15px;background:#f7faf8;border-radius:16px}.bh-notification-section-heading>span{font-size:21px}.bh-notification-section-heading h3{margin:0 0 3px;color:#23493d;font-size:15px}.bh-notification-section-heading p{margin:0;color:#718079;font-size:11px;line-height:1.45}.bh-notification-section-heading-spaced{margin-top:22px}
        .bh-notification-channel{display:grid;grid-template-columns:44px 1fr auto 42px;gap:14px;align-items:center;padding:15px 0;border-top:1px solid #e8eeea;cursor:pointer;position:relative}.bh-notification-channel-icon{width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:#f1f6f2;font-size:20px}.bh-notification-channel input{position:absolute;opacity:0;pointer-events:none}.bh-notification-channel-disabled{cursor:default;opacity:.72}.bh-notification-status{padding:5px 8px;border-radius:999px;background:#edf1ef;color:#6d7b75;font-size:9px;font-weight:800;white-space:nowrap}
        @media(max-width:600px){.bh-notification-channel{grid-template-columns:40px 1fr 42px;gap:10px}.bh-notification-channel-disabled{grid-template-columns:40px 1fr auto}.bh-notification-channel-icon{width:36px;height:36px;font-size:18px}}
        .bh-notification-option{display:grid;grid-template-columns:44px 1fr 0 44px;gap:14px;align-items:center;padding:17px 0;border-top:1px solid #e8eeea;cursor:pointer;position:relative}.bh-notification-icon{width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:#f1f6f2;font-size:20px}.bh-notification-copy{display:flex;flex-direction:column;gap:4px}.bh-notification-copy strong{color:#23493d;font-size:15px}.bh-notification-copy small{color:#74847e;font-size:12px;line-height:1.45}.bh-notification-option input{position:absolute;opacity:0;pointer-events:none}.bh-notification-toggle{width:42px;height:24px;border-radius:999px;background:#ccd8d2;position:relative;transition:.2s}.bh-notification-toggle:after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.15);transition:.2s}.bh-notification-option input:checked + .bh-notification-toggle{background:#2f6c52}.bh-notification-option input:checked + .bh-notification-toggle:after{transform:translateX(18px)}
        .bh-notification-phone{margin:-4px 0 14px;padding:14px 16px;background:#f7faf8;border-radius:14px}.bh-notification-phone label{display:block;font-weight:800;font-size:12px;color:#31554a;margin-bottom:6px}.bh-notification-phone input{width:100%;box-sizing:border-box;border:1px solid #d7e1db;border-radius:10px;padding:11px 13px;font:inherit}.bh-notification-phone small{display:block;margin-top:6px;color:#7b8984;font-size:11px}
        .bh-notification-save{border:0;border-radius:12px;padding:12px 18px;background:#1e513b;color:#fff;font-weight:800;cursor:pointer;margin-top:18px}.bh-notification-success{padding:12px 14px;border-radius:12px;background:#e8f7e9;color:#245d3f;font-weight:700;margin-bottom:16px}.bh-notification-note{margin-top:16px;padding:12px 14px;background:#f7faf8;border-radius:12px;color:#65766f;font-size:11px;line-height:1.5}
        .bh-notification-feed{margin:30px 0}.bh-notification-feed-head{margin-bottom:14px}.bh-notification-item{display:flex;gap:12px;padding:15px;border:1px solid #e3eae6;border-radius:16px;background:#fff;margin-bottom:10px}.bh-notification-item.is-new{border-left:4px solid #2f6c52}.bh-notification-item-icon{font-size:20px}.bh-notification-item strong{color:#23493d}.bh-notification-item p{margin:4px 0;color:#65766f;font-size:13px}.bh-notification-item small{color:#8a9892;font-size:10px}.bh-notification-item a{display:block;margin-top:7px;color:#2f6c52;font-size:12px;font-weight:800;text-decoration:none}.bh-notification-empty{padding:20px;border:1px dashed #d0ddd6;border-radius:14px;background:#fafcfb;color:#718079}
        @media(max-width:600px){.bh-notification-option{grid-template-columns:40px 1fr 42px;gap:10px}.bh-notification-icon{width:36px;height:36px;font-size:18px}}
    ' );
}
add_action( 'wp_enqueue_scripts', 'bubbahub_notification_assets', 70 );
