<?php
/**
 * Bubba Hub SMS safety, quota and leader warning policy.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BUBBAHUB_SMS_POLICY_VERSION' ) ) define( 'BUBBAHUB_SMS_POLICY_VERSION', '1.0.0' );

function bubbahub_sms_policy_defaults() {
    return array(
        'daily_limit'       => 40,
        'monthly_limit'     => 280,
        'user_daily_limit'  => 3,
        'user_monthly_limit'=> 10,
        'warning_daily'     => 35,
        'warning_monthly'   => 250,
    );
}

function bubbahub_sms_policy_settings() {
    $d = bubbahub_sms_policy_defaults();
    return array(
        'daily_limit'        => max( 1, (int) get_option( 'bubbahub_sms_daily_limit', $d['daily_limit'] ) ),
        'monthly_limit'      => max( 1, (int) get_option( 'bubbahub_sms_monthly_limit', $d['monthly_limit'] ) ),
        'user_daily_limit'   => max( 1, (int) get_option( 'bubbahub_sms_user_daily_limit', $d['user_daily_limit'] ) ),
        'user_monthly_limit' => max( 1, (int) get_option( 'bubbahub_sms_user_monthly_limit', $d['user_monthly_limit'] ) ),
    );
}

function bubbahub_sms_policy_log() {
    $log = get_option( 'bubbahub_sms_usage_log', array() );
    return is_array( $log ) ? $log : array();
}

function bubbahub_sms_policy_prune_log() {
    $cutoff = time() - ( 31 * DAY_IN_SECONDS );
    $log = array_values( array_filter( bubbahub_sms_policy_log(), function( $row ) use ( $cutoff ) {
        return is_array( $row ) && ! empty( $row['time'] ) && (int) $row['time'] >= $cutoff;
    } ) );
    if ( count( $log ) > 500 ) $log = array_slice( $log, -500 );
    update_option( 'bubbahub_sms_usage_log', $log, false );
    return $log;
}

function bubbahub_sms_policy_usage() {
    $log = bubbahub_sms_policy_prune_log();
    $now = current_time( 'timestamp' );
    $day_start = strtotime( wp_date( 'Y-m-d 00:00:00', $now ) );
    $month_start = strtotime( wp_date( 'Y-m-01 00:00:00', $now ) );
    $today = 0; $month = 0;
    foreach ( $log as $row ) {
        $t = isset( $row['time'] ) ? (int) $row['time'] : 0;
        if ( $t >= $month_start ) $month++;
        if ( $t >= $day_start ) $today++;
    }
    return array( 'today' => $today, 'month' => $month );
}

function bubbahub_sms_policy_user_usage( $user_id ) {
    $user_id = absint( $user_id );
    $log = bubbahub_sms_policy_prune_log();
    $now = current_time( 'timestamp' );
    $day_start = strtotime( wp_date( 'Y-m-d 00:00:00', $now ) );
    $month_start = strtotime( wp_date( 'Y-m-d 00:00:00', $now ) ) - ( 30 * DAY_IN_SECONDS );
    $today = 0; $month = 0;
    foreach ( $log as $row ) {
        if ( absint( $row['user_id'] ?? 0 ) !== $user_id ) continue;
        $t = (int) ( $row['time'] ?? 0 );
        if ( $t >= $day_start ) $today++;
        if ( $t >= $month_start ) $month++;
    }
    return array( 'today' => $today, 'month' => $month );
}

function bubbahub_sms_policy_is_critical( $type, $title, $options = array() ) {
    if ( ! empty( $options['sms_emergency'] ) ) return true;
    $haystack = strtolower( $type . ' ' . $title );
    foreach ( array( 'cancel', 'cancelled', 'cancellation', 'emergency', 'venue changed', 'time changed', 'date changed', 'session changed', 'class change', 'class changed' ) as $needle ) {
        if ( false !== strpos( $haystack, $needle ) ) return true;
    }
    return false;
}

function bubbahub_sms_policy_can_send( $user_id, $type = '', $title = '', $options = array() ) {
    $settings = bubbahub_sms_policy_settings();
    $usage = bubbahub_sms_policy_usage();
    $user_usage = bubbahub_sms_policy_user_usage( $user_id );

    if ( $usage['today'] >= $settings['daily_limit'] || $usage['month'] >= $settings['monthly_limit'] ) return false;
    if ( $user_usage['today'] >= $settings['user_daily_limit'] || $user_usage['month'] >= $settings['user_monthly_limit'] ) return false;

    /* SMS is intentionally restricted to urgent/emergency notifications. */
    if ( ! bubbahub_sms_policy_is_critical( $type, $title, $options ) ) return false;
    return true;
}

function bubbahub_sms_policy_record_success( $user_id, $type = '', $title = '' ) {
    $log = bubbahub_sms_policy_prune_log();
    $log[] = array(
        'time'    => current_time( 'timestamp' ),
        'user_id' => absint( $user_id ),
        'type'    => sanitize_key( $type ),
        'title'   => sanitize_text_field( $title ),
    );
    update_option( 'bubbahub_sms_usage_log', array_slice( $log, -500 ), false );
    bubbahub_sms_policy_maybe_warn_leaders();
}

function bubbahub_sms_leader_ids() {
    $ids = array();
    $roles = array( 'leader', 'class_leader', 'group_leader', 'business_owner' );
    foreach ( $roles as $role ) {
        $users = get_users( array( 'role' => $role, 'fields' => 'ID' ) );
        if ( $users ) $ids = array_merge( $ids, array_map( 'absint', $users ) );
    }
    $authors = get_posts( array( 'post_type' => 'group', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
    foreach ( $authors as $group_id ) {
        $author = absint( get_post_field( 'post_author', $group_id ) );
        if ( $author ) $ids[] = $author;
    }
    return array_values( array_unique( array_filter( $ids ) ) );
}

function bubbahub_sms_leader_warning_message( $reached = false ) {
    $settings = bubbahub_sms_policy_settings();
    $usage = bubbahub_sms_policy_usage();
    if ( $reached ) {
        return sprintf(
            'Bubba Hub SMS has reached its safety limit (%d today / %d this month). Please do not send SMS unless it is an emergency. Portal and email notifications remain available.',
            $settings['daily_limit'], $settings['monthly_limit']
        );
    }
    return sprintf(
        'Bubba Hub SMS is approaching its safety limit (%d/%d today, %d/%d this month). Please do not send SMS unless it is an emergency. Portal and email notifications remain available.',
        $usage['today'], $settings['daily_limit'], $usage['month'], $settings['monthly_limit']
    );
}

function bubbahub_sms_notify_all_leaders( $reached = false, $force = false ) {
    $key = $reached ? 'bubbahub_sms_limit_reached_notified' : 'bubbahub_sms_limit_warning_notified';
    $period = $reached ? wp_date( 'Y-m-d' ) : wp_date( 'Y-m' );
    $marker = $period;
    if ( ! $force && get_option( $key, '' ) === $marker ) return 0;

    $message = bubbahub_sms_leader_warning_message( $reached );
    $title = $reached ? 'SMS limit reached — emergency use only' : 'SMS limit approaching — please use only for emergencies';
    $url = home_url( '/my-hub/' );
    $sent = 0;
    foreach ( bubbahub_sms_leader_ids() as $leader_id ) {
        if ( function_exists( 'bubbahub_notification_log' ) ) bubbahub_notification_log( $leader_id, 'sms', $title, $message, $url );
        $leader = get_userdata( $leader_id );
        if ( $leader && is_email( $leader->user_email ) ) {
            wp_mail( $leader->user_email, 'Bubba Hub: SMS limit notice', "Hi {$leader->display_name},\n\n{$message}\n\nBubba Hub" );
        }
        $sent++;
    }
    if ( ! $force ) update_option( $key, $marker, false );
    return $sent;
}

function bubbahub_sms_policy_maybe_warn_leaders() {
    $settings = bubbahub_sms_policy_settings();
    $usage = bubbahub_sms_policy_usage();
    if ( $usage['today'] >= $settings['daily_limit'] || $usage['month'] >= $settings['monthly_limit'] ) {
        bubbahub_sms_notify_all_leaders( true );
    } elseif ( $usage['today'] >= min( $settings['daily_limit'] - 1, 35 ) || $usage['month'] >= min( $settings['monthly_limit'] - 1, 250 ) ) {
        bubbahub_sms_notify_all_leaders( false );
    }
}

function bubbahub_sms_leader_alert_html() {
    if ( ! is_user_logged_in() ) return '';
    $uid = get_current_user_id();
    if ( ! in_array( $uid, bubbahub_sms_leader_ids(), true ) ) return '';
    $settings = bubbahub_sms_policy_settings();
    $usage = bubbahub_sms_policy_usage();
    if ( $usage['today'] < $settings['daily_limit'] && $usage['month'] < $settings['monthly_limit'] && $usage['today'] < min( $settings['daily_limit'] - 1, 35 ) && $usage['month'] < min( $settings['monthly_limit'] - 1, 250 ) ) return '';
    $reached = $usage['today'] >= $settings['daily_limit'] || $usage['month'] >= $settings['monthly_limit'];
    $title = $reached ? '⚠ SMS limit reached' : '⚠ SMS limit nearly reached';
    $message = $reached ? 'Please do not send SMS unless it is an emergency. Portal and email notifications remain available.' : 'Please only use SMS for genuine emergencies. The Bubba Hub SMS allowance is nearly full.';
    return '<div class="bh-sms-leader-alert ' . ( $reached ? 'is-reached' : 'is-warning' ) . '"><strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $message ) . '</span></div>';
}

function bubbahub_sms_leader_alert_footer() {
    $html = bubbahub_sms_leader_alert_html();
    if ( ! $html ) return;
    ?>
    <style>
    .bh-sms-leader-alert{display:flex;gap:12px;align-items:flex-start;margin:0 0 18px;padding:14px 16px;border-radius:16px;border:1px solid #f0c36d;background:#fff8e7;color:#5f4b1d;box-shadow:0 2px 8px rgba(0,0,0,.04)}
    .bh-sms-leader-alert.is-reached{border-color:#d66;background:#fff1f1;color:#7a2525}
    .bh-sms-leader-alert strong{display:block;white-space:nowrap}.bh-sms-leader-alert span{line-height:1.5}
    @media(max-width:600px){.bh-sms-leader-alert{display:block}.bh-sms-leader-alert strong{margin-bottom:4px}}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
      var hub=document.querySelector('.bh-myhub-v3');
      var alert=document.querySelector('.bh-sms-leader-alert');
      if(hub && alert){var hero=hub.querySelector('.bh-myhub-hero');if(hero)hero.insertAdjacentElement('afterend',alert);}
    });
    </script>
    <?php
}
add_action( 'wp_footer', 'bubbahub_sms_leader_alert_footer', 35 );

function bubbahub_sms_policy_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $settings = bubbahub_sms_policy_settings();
    $usage = bubbahub_sms_policy_usage();
    $daily_left = max( 0, $settings['daily_limit'] - $usage['today'] );
    $monthly_left = max( 0, $settings['monthly_limit'] - $usage['month'] );
    $class = ( 0 === $daily_left || 0 === $monthly_left ) ? 'error' : ( $daily_left <= 5 || $monthly_left <= 30 ? 'warning' : 'info' );
    $text = sprintf( 'Bubba Hub SMS: %d/%d today, %d/%d this month. %d daily / %d monthly messages remaining.', $usage['today'], $settings['daily_limit'], $usage['month'], $settings['monthly_limit'], $daily_left, $monthly_left );
    echo '<div class="notice notice-' . esc_attr( $class ) . '"><p><strong>' . esc_html( $text ) . '</strong></p></div>';
}
add_action( 'admin_notices', 'bubbahub_sms_policy_admin_notice' );

function bubbahub_sms_policy_admin_fields() {
    $s = bubbahub_sms_policy_settings();
    ?>
    <h2>SMS safety limits</h2>
    <p>These Bubba Hub limits deliberately sit below TextBee's free-plan allowance. SMS is reserved for urgent/emergency messages.</p>
    <table class="form-table">
        <tr><th>Daily Bubba Hub limit</th><td><input type="number" min="1" name="bh_sms_daily_limit" value="<?php echo esc_attr( $s['daily_limit'] ); ?>"></td></tr>
        <tr><th>Monthly Bubba Hub limit</th><td><input type="number" min="1" name="bh_sms_monthly_limit" value="<?php echo esc_attr( $s['monthly_limit'] ); ?>"></td></tr>
        <tr><th>Per-user daily limit</th><td><input type="number" min="1" name="bh_sms_user_daily_limit" value="<?php echo esc_attr( $s['user_daily_limit'] ); ?>"></td></tr>
        <tr><th>Per-user 30-day limit</th><td><input type="number" min="1" name="bh_sms_user_monthly_limit" value="<?php echo esc_attr( $s['user_monthly_limit'] ); ?>"></td></tr>
    </table>
    <?php
}
