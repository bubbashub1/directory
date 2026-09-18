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
        'class_booking' => 1,
        'email_digest'  => 1,
        'sms_reminders' => 0,
        'community'     => 1,
    );
}

function bubbahub_notification_preferences( $user_id = 0 ) {
    $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
    if ( ! $user_id ) return bubbahub_notification_defaults();

    $saved = get_user_meta( $user_id, 'bubbahub_notification_preferences', true );
    $defaults = bubbahub_notification_defaults();
    if ( ! is_array( $saved ) ) $saved = array();

    return array(
        'class_booking' => isset( $saved['class_booking'] ) ? (int) (bool) $saved['class_booking'] : $defaults['class_booking'],
        'email_digest'  => isset( $saved['email_digest'] ) ? (int) (bool) $saved['email_digest'] : $defaults['email_digest'],
        'sms_reminders' => isset( $saved['sms_reminders'] ) ? (int) (bool) $saved['sms_reminders'] : $defaults['sms_reminders'],
        'community'     => isset( $saved['community'] ) ? (int) (bool) $saved['community'] : $defaults['community'],
    );
}

function bubbahub_notification_save_preferences( $user_id, $preferences ) {
    $defaults = bubbahub_notification_defaults();
    $clean = array();
    foreach ( $defaults as $key => $default ) {
        $clean[ $key ] = ! empty( $preferences[ $key ] ) ? 1 : 0;
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
    if ( in_array( $type, array( 'community', 'support' ), true ) ) return ! empty( $prefs['community'] );
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

    if ( $options['portal'] ) bubbahub_notification_log( $user_id, $type, $title, $message, $url );

    $sent = false;
    if ( $options['email'] && bubbahub_notification_email_enabled( $user_id, $type ) && is_email( $user->user_email ) ) {
        $body = "Hi {$user->display_name},\n\n{$message}\n\n";
        if ( $url ) $body .= "View this in your Bubba Hub account:\n{$url}\n\n";
        $body .= "Bubba Hub";
        $sent = wp_mail( $user->user_email, $options['subject'], $body );
    }

    if ( $options['sms'] && bubbahub_notification_preferences( $user_id )['sms_reminders'] ) {
        $phone = get_user_meta( $user_id, 'bubbahub_notification_phone', true );
        if ( $phone ) {
            do_action( 'bubbahub_send_sms_notification', $phone, $title, $message, $user_id );
        }
    }

    return $sent;
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
                <p>Choose how Bubba Hub keeps you up to date. You can change these settings at any time.</p>
            </div>
        </div>

        <?php if ( ! empty( $_GET['bh_notifications_saved'] ) ) : ?>
            <div class="bh-notification-success">✓ Your notification preferences have been saved.</div>
        <?php endif; ?>

        <form method="post" class="bh-notification-form">
            <?php wp_nonce_field( 'bubbahub_notification_preferences', 'bubbahub_notification_nonce' ); ?>
            <input type="hidden" name="bubbahub_notification_action" value="save">

            <label class="bh-notification-option">
                <span class="bh-notification-icon">📅</span>
                <span class="bh-notification-copy"><strong>Class &amp; booking alerts</strong><small>Receive relevant Bubba Hub updates through this channel.</small></span>
                <input type="checkbox" name="notification[class_booking]" value="1" <?php checked( $prefs['class_booking'], 1 ); ?>>
                <span class="bh-notification-toggle" aria-hidden="true"></span>
            </label>

            <label class="bh-notification-option">
                <span class="bh-notification-icon">✉️</span>
                <span class="bh-notification-copy"><strong>Email digest</strong><small>Receive relevant Bubba Hub updates through this channel.</small></span>
                <input type="checkbox" name="notification[email_digest]" value="1" <?php checked( $prefs['email_digest'], 1 ); ?>>
                <span class="bh-notification-toggle" aria-hidden="true"></span>
            </label>

            <label class="bh-notification-option">
                <span class="bh-notification-icon">📱</span>
                <span class="bh-notification-copy"><strong>SMS reminders</strong><small>Receive relevant Bubba Hub updates through this channel.</small></span>
                <input type="checkbox" name="notification[sms_reminders]" value="1" <?php checked( $prefs['sms_reminders'], 1 ); ?>>
                <span class="bh-notification-toggle" aria-hidden="true"></span>
            </label>

            <?php if ( $prefs['sms_reminders'] ) : ?>
                <div class="bh-notification-phone">
                    <label for="bh-notification-phone">Mobile number for reminders</label>
                    <input id="bh-notification-phone" type="tel" name="notification_phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="+44 7...">
                    <small>SMS sending will use the Bubba Hub SMS provider when connected.</small>
                </div>
            <?php endif; ?>

            <label class="bh-notification-option">
                <span class="bh-notification-icon">🏡</span>
                <span class="bh-notification-copy"><strong>Community alerts</strong><small>Receive relevant Bubba Hub updates through this channel.</small></span>
                <input type="checkbox" name="notification[community]" value="1" <?php checked( $prefs['community'], 1 ); ?>>
                <span class="bh-notification-toggle" aria-hidden="true"></span>
            </label>

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
            <div class="bh-notification-empty">You're all caught up. New relevant updates will appear here.</div>
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
        .bh-notification-option{display:grid;grid-template-columns:44px 1fr 0 44px;gap:14px;align-items:center;padding:17px 0;border-top:1px solid #e8eeea;cursor:pointer;position:relative}.bh-notification-icon{width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:#f1f6f2;font-size:20px}.bh-notification-copy{display:flex;flex-direction:column;gap:4px}.bh-notification-copy strong{color:#23493d;font-size:15px}.bh-notification-copy small{color:#74847e;font-size:12px;line-height:1.45}.bh-notification-option input{position:absolute;opacity:0;pointer-events:none}.bh-notification-toggle{width:42px;height:24px;border-radius:999px;background:#ccd8d2;position:relative;transition:.2s}.bh-notification-toggle:after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.15);transition:.2s}.bh-notification-option input:checked + .bh-notification-toggle{background:#2f6c52}.bh-notification-option input:checked + .bh-notification-toggle:after{transform:translateX(18px)}
        .bh-notification-phone{margin:-4px 0 14px;padding:14px 16px;background:#f7faf8;border-radius:14px}.bh-notification-phone label{display:block;font-weight:800;font-size:12px;color:#31554a;margin-bottom:6px}.bh-notification-phone input{width:100%;box-sizing:border-box;border:1px solid #d7e1db;border-radius:10px;padding:11px 13px;font:inherit}.bh-notification-phone small{display:block;margin-top:6px;color:#7b8984;font-size:11px}
        .bh-notification-save{border:0;border-radius:12px;padding:12px 18px;background:#1e513b;color:#fff;font-weight:800;cursor:pointer;margin-top:18px}.bh-notification-success{padding:12px 14px;border-radius:12px;background:#e8f7e9;color:#245d3f;font-weight:700;margin-bottom:16px}.bh-notification-note{margin-top:16px;padding:12px 14px;background:#f7faf8;border-radius:12px;color:#65766f;font-size:11px;line-height:1.5}
        .bh-notification-feed{margin:30px 0}.bh-notification-feed-head{margin-bottom:14px}.bh-notification-item{display:flex;gap:12px;padding:15px;border:1px solid #e3eae6;border-radius:16px;background:#fff;margin-bottom:10px}.bh-notification-item.is-new{border-left:4px solid #2f6c52}.bh-notification-item-icon{font-size:20px}.bh-notification-item strong{color:#23493d}.bh-notification-item p{margin:4px 0;color:#65766f;font-size:13px}.bh-notification-item small{color:#8a9892;font-size:10px}.bh-notification-item a{display:block;margin-top:7px;color:#2f6c52;font-size:12px;font-weight:800;text-decoration:none}.bh-notification-empty{padding:20px;border:1px dashed #d0ddd6;border-radius:14px;background:#fafcfb;color:#718079}
        @media(max-width:600px){.bh-notification-option{grid-template-columns:40px 1fr 42px;gap:10px}.bh-notification-icon{width:36px;height:36px;font-size:18px}}
    ' );
}
add_action( 'wp_enqueue_scripts', 'bubbahub_notification_assets', 70 );
