<?php
/**
 * Bubba Hub TextBee SMS integration.
 *
 * Connects the notification centre to textbee.dev using the site's
 * existing bubbahub_send_sms_notification action.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BUBBAHUB_TEXTBEE_VERSION' ) ) define( 'BUBBAHUB_TEXTBEE_VERSION', '1.0.0' );

function bubbahub_textbee_settings() {
    return array(
        'enabled'   => (bool) get_option( 'bubbahub_textbee_enabled', false ),
        'api_key'   => (string) get_option( 'bubbahub_textbee_api_key', '' ),
        'device_id' => (string) get_option( 'bubbahub_textbee_device_id', '' ),
    );
}

function bubbahub_textbee_normalise_phone( $phone ) {
    $phone = trim( (string) $phone );
    if ( preg_match( '/^07\d{9}$/', preg_replace( '/\D+/', '', $phone ) ) ) {
        return '+44' . substr( preg_replace( '/\D+/', '', $phone ), 1 );
    }
    $phone = preg_replace( '/[^0-9+]/', '', $phone );
    return $phone;
}

function bubbahub_textbee_request( $method, $path, $body = null ) {
    $settings = bubbahub_textbee_settings();
    if ( empty( $settings['api_key'] ) ) {
        return new WP_Error( 'textbee_missing_key', 'TextBee API key has not been configured.' );
    }

    $args = array(
        'method'  => strtoupper( $method ),
        'timeout' => 20,
        'headers' => array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
            'x-api-key'    => $settings['api_key'],
        ),
    );

    if ( null !== $body ) {
        $args['body'] = wp_json_encode( $body );
    }

    return wp_remote_request( 'https://api.textbee.dev/api/v1' . $path, $args );
}

function bubbahub_textbee_send_sms( $phone, $title, $message, $user_id = 0 ) {
    $settings = bubbahub_textbee_settings();
    if ( function_exists( 'bubbahub_sms_policy_can_send' ) && ! bubbahub_sms_policy_can_send( $user_id, 'sms', $title ) ) return false;
    if ( empty( $settings['enabled'] ) ) return false;

    $recipient = bubbahub_textbee_normalise_phone( $phone );
    if ( ! preg_match( '/^\+[1-9]\d{7,14}$/', $recipient ) ) {
        return false;
    }

    $sms = trim( (string) $message );
    if ( $title ) $sms = trim( $title . ': ' . $sms );

    $body = array(
        'recipients' => array( $recipient ),
        'message'    => $sms,
    );
    if ( ! empty( $settings['device_id'] ) ) {
        $body['deviceId'] = sanitize_text_field( $settings['device_id'] );
    }

    $response = bubbahub_textbee_request( 'POST', '/gateway/send-sms', $body );
    if ( is_wp_error( $response ) ) return false;

    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $code >= 200 && $code < 300 ) {
        if ( function_exists( 'bubbahub_sms_policy_record_success' ) ) bubbahub_sms_policy_record_success( $user_id, 'sms', $title );
        do_action( 'bubbahub_textbee_sms_accepted', $recipient, $data, $user_id );
        return true;
    }

    do_action( 'bubbahub_textbee_sms_failed', $recipient, $code, $data, $user_id );
    return false;
}
add_action( 'bubbahub_send_sms_notification', 'bubbahub_textbee_send_sms', 10, 4 );

function bubbahub_textbee_admin_menu() {
    add_submenu_page(
        'options-general.php',
        'Bubba Hub SMS',
        'Bubba Hub SMS',
        'manage_options',
        'bubbahub-sms',
        'bubbahub_textbee_admin_page'
    );
}
add_action( 'admin_menu', 'bubbahub_textbee_admin_menu' );

function bubbahub_textbee_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $notice = '';
    $notice_type = 'success';

    if ( ! empty( $_POST['bh_textbee_save'] ) && check_admin_referer( 'bubbahub_textbee_settings', 'bh_textbee_nonce' ) ) {
        update_option( 'bubbahub_textbee_enabled', ! empty( $_POST['enabled'] ) );
        update_option( 'bubbahub_textbee_api_key', sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ) );
        update_option( 'bubbahub_textbee_device_id', sanitize_text_field( wp_unslash( $_POST['device_id'] ?? '' ) ) );
        $notice = 'TextBee settings saved.';
    }

    if ( ! empty( $_POST['bh_textbee_test'] ) && check_admin_referer( 'bubbahub_textbee_test', 'bh_textbee_test_nonce' ) ) {
        $phone = bubbahub_textbee_normalise_phone( sanitize_text_field( wp_unslash( $_POST['test_phone'] ?? '' ) ) );
        $response = bubbahub_textbee_request( 'POST', '/gateway/send-sms', array(
            'recipients' => array( $phone ),
            'message'    => 'Bubba Hub SMS test: your TextBee connection is working.',
        ) );

        if ( is_wp_error( $response ) ) {
            $notice_type = 'error';
            $notice = $response->get_error_message();
        } else {
            $code = (int) wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( $code >= 200 && $code < 300 ) {
                $notice = 'Test SMS accepted by TextBee. Check the connected Android phone and recipient.';
            } else {
                $notice_type = 'error';
                $detail = ! empty( $data['message'] ) ? $data['message'] : wp_remote_retrieve_body( $response );
                $notice = 'TextBee rejected the test SMS (HTTP ' . $code . '): ' . wp_strip_all_tags( (string) $detail );
            }
        }
    }

    $settings = bubbahub_textbee_settings();

    if ( function_exists( 'bubbahub_sms_policy_usage' ) ) {
        $bh_sms_usage = bubbahub_sms_policy_usage();
        $bh_sms_limits = bubbahub_sms_policy_settings();
        $bh_sms_daily_left = max( 0, $bh_sms_limits['daily_limit'] - $bh_sms_usage['today'] );
        $bh_sms_monthly_left = max( 0, $bh_sms_limits['monthly_limit'] - $bh_sms_usage['month'] );
    }
    if ( ! empty( $_POST['bh_textbee_notify_leaders'] ) && check_admin_referer( 'bubbahub_textbee_leader_notice', 'bh_textbee_leader_notice_nonce' ) ) {
        $count = function_exists( 'bubbahub_sms_notify_all_leaders' ) ? bubbahub_sms_notify_all_leaders( true, true ) : 0;
        $notice = 'SMS emergency-only warning sent to ' . absint( $count ) . ' leader account(s).';
    }

    $devices = array();
    $device_error = '';
    if ( ! empty( $settings['api_key'] ) ) {
        $response = bubbahub_textbee_request( 'GET', '/gateway/devices' );
        if ( is_wp_error( $response ) ) {
            $device_error = $response->get_error_message();
        } else {
            $code = (int) wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( $code >= 200 && $code < 300 && ! empty( $data['data'] ) && is_array( $data['data'] ) ) {
                $devices = $data['data'];
            } elseif ( $code ) {
                $device_error = 'TextBee returned HTTP ' . $code . '.';
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Bubba Hub SMS — TextBee</h1>
        <p>Send urgent Bubba Hub SMS through your own Android phone and SIM using TextBee. SMS is deliberately restricted by Bubba Hub safety limits.</p>

        <?php if ( $notice ) : ?>
            <div class="notice notice-<?php echo esc_attr( $notice_type ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
        <?php endif; ?>

        <?php if ( function_exists( 'bubbahub_sms_policy_usage' ) ) : ?>
            <div style="max-width:900px;background:#fff8e7;border:1px solid #f0c36d;border-radius:12px;padding:20px;margin-top:20px;">
                <h2 style="margin-top:0;">SMS safety status</h2>
                <p><strong><?php echo esc_html( $bh_sms_usage['today'] . '/' . $bh_sms_limits['daily_limit'] ); ?></strong> messages today · <strong><?php echo esc_html( $bh_sms_usage['month'] . '/' . $bh_sms_limits['monthly_limit'] ); ?></strong> this month.</p>
                <p><?php echo ( $bh_sms_daily_left && $bh_sms_monthly_left ) ? esc_html( 'SMS is reserved for urgent/emergency notifications. ' . $bh_sms_daily_left . ' daily and ' . $bh_sms_monthly_left . ' monthly messages remain.' ) : esc_html( 'SMS has reached its Bubba Hub safety limit. Do not send SMS unless it is an emergency.' ); ?></p>
                <form method="post"><?php wp_nonce_field( 'bubbahub_textbee_leader_notice', 'bh_textbee_leader_notice_nonce' ); ?><input type="hidden" name="bh_textbee_notify_leaders" value="1"><?php submit_button( 'Notify all leaders: SMS emergency-only', 'secondary', 'submit', false ); ?></form>
            </div>
        <?php endif; ?>

        <div style="max-width:900px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;margin-top:20px;">
            <h2>1. Connect TextBee</h2>
            <p>TextBee requires an account, an API key and one registered Android phone with a SIM. The free plan currently allows one active device, up to 50 messages/day and up to 300/month.</p>
            <form method="post">
                <?php wp_nonce_field( 'bubbahub_textbee_settings', 'bh_textbee_nonce' ); ?>
                <input type="hidden" name="bh_textbee_save" value="1">
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable SMS sending</th>
                        <td><label><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>> Enable TextBee for Bubba Hub notifications</label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bh-textbee-key">TextBee API key</label></th>
                        <td><input id="bh-textbee-key" class="regular-text" type="password" name="api_key" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="new-password"><p class="description">Keep this key private. It is stored in WordPress options and never displayed in full.</p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bh-textbee-device">Sending device</label></th>
                        <td>
                            <select id="bh-textbee-device" name="device_id">
                                <option value="">Use TextBee default device</option>
                                <?php foreach ( $devices as $device ) : ?>
                                    <?php $id = isset( $device['_id'] ) ? $device['_id'] : ''; ?>
                                    <?php if ( ! $id ) continue; ?>
                                    <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['device_id'], $id ); ?>><?php echo esc_html( ( $device['name'] ?? $device['model'] ?? 'Android device' ) . ( ! empty( $device['isDefault'] ) ? ' — default' : '' ) ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ( $device_error ) : ?><p class="description" style="color:#b32d2e;"><?php echo esc_html( $device_error ); ?></p><?php endif; ?>
                            <?php if ( $settings['api_key'] && ! $devices && ! $device_error ) : ?><p class="description">No registered TextBee device was returned yet.</p><?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save TextBee settings' ); ?>
            </form>
        </div>

        <div style="max-width:900px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:24px;margin-top:20px;">
            <h2>2. Send a test SMS</h2>
            <p>Use international format, for example <code>+447700900123</code>.</p>
            <form method="post">
                <?php wp_nonce_field( 'bubbahub_textbee_test', 'bh_textbee_test_nonce' ); ?>
                <input type="hidden" name="bh_textbee_test" value="1">
                <input class="regular-text" type="tel" name="test_phone" placeholder="+447..." required>
                <?php submit_button( 'Send test SMS', 'secondary', 'submit', false ); ?>
            </form>
        </div>

        <div style="max-width:900px;background:#f6f7f7;border-radius:12px;padding:20px;margin-top:20px;">
            <h2>3. How Bubba Hub uses it</h2>
            <ul>
                <li>Families opt in to <strong>SMS reminders</strong> in their notification preferences.</li>
                <li>Bubba Hub SMS is restricted to urgent/emergency notifications such as cancellations and important class changes.</li>
                <li>Portal notifications remain available even if SMS is unavailable.</li>
                <li>TextBee accepts E.164 phone numbers such as <code>+447...</code>.</li>
            </ul>
        </div>
    </div>
    <?php
}
