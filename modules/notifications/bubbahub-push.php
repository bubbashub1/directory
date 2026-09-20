<?php
/**
 * Bubba Hub Web Push / Firebase Cloud Messaging integration.
 *
 * Uses the browser Push API through Firebase Cloud Messaging so the installed
 * Bubba Hub PWA can receive notification alerts on supported devices.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BUBBAHUB_PUSH_VERSION' ) ) define( 'BUBBAHUB_PUSH_VERSION', '1.0.0' );
if ( ! defined( 'BUBBAHUB_FIREBASE_JS_VERSION' ) ) define( 'BUBBAHUB_FIREBASE_JS_VERSION', '12.19.0' );

function bubbahub_push_config() {
    $saved = get_option( 'bubbahub_push_settings', array() );
    if ( ! is_array( $saved ) ) $saved = array();
    return array(
        'apiKey'            => isset( $saved['api_key'] ) ? trim( $saved['api_key'] ) : '',
        'authDomain'        => isset( $saved['auth_domain'] ) ? trim( $saved['auth_domain'] ) : '',
        'projectId'         => isset( $saved['project_id'] ) ? trim( $saved['project_id'] ) : '',
        'storageBucket'     => isset( $saved['storage_bucket'] ) ? trim( $saved['storage_bucket'] ) : '',
        'messagingSenderId' => isset( $saved['messaging_sender_id'] ) ? trim( $saved['messaging_sender_id'] ) : '',
        'appId'             => isset( $saved['app_id'] ) ? trim( $saved['app_id'] ) : '',
        'vapidKey'          => isset( $saved['vapid_key'] ) ? trim( $saved['vapid_key'] ) : '',
        'enabled'           => ! empty( $saved['enabled'] ),
    );
}

function bubbahub_push_is_configured() {
    $c = bubbahub_push_config();
    return $c['enabled'] && $c['apiKey'] && $c['projectId'] && $c['messagingSenderId'] && $c['appId'] && $c['vapidKey'] && bubbahub_push_service_account();
}

function bubbahub_push_service_account() {
    if ( defined( 'BUBBAHUB_FCM_SERVICE_ACCOUNT_JSON' ) && BUBBAHUB_FCM_SERVICE_ACCOUNT_JSON ) {
        $json = BUBBAHUB_FCM_SERVICE_ACCOUNT_JSON;
    } else {
        $json = get_option( 'bubbahub_fcm_service_account_json', '' );
    }
    if ( is_array( $json ) ) return $json;
    if ( ! is_string( $json ) || ! $json ) return array();
    $decoded = json_decode( $json, true );
    return is_array( $decoded ) ? $decoded : array();
}

function bubbahub_push_enqueue_assets() {
    if ( ! is_user_logged_in() || ! bubbahub_push_config()['enabled'] ) return;
    $c = bubbahub_push_config();
    if ( ! $c['apiKey'] || ! $c['projectId'] || ! $c['messagingSenderId'] || ! $c['appId'] || ! $c['vapidKey'] ) return;

    wp_enqueue_script( 'firebase-app-compat', 'https://www.gstatic.com/firebasejs/' . BUBBAHUB_FIREBASE_JS_VERSION . '/firebase-app-compat.js', array(), BUBBAHUB_FIREBASE_JS_VERSION, true );
    wp_enqueue_script( 'firebase-messaging-compat', 'https://www.gstatic.com/firebasejs/' . BUBBAHUB_FIREBASE_JS_VERSION . '/firebase-messaging-compat.js', array( 'firebase-app-compat' ), BUBBAHUB_FIREBASE_JS_VERSION, true );
    wp_enqueue_script( 'bubbahub-push', BUBBAHUB_DIRECTORY_URL . 'assets/bubbahub-push.js', array( 'firebase-messaging-compat' ), BUBBAHUB_PUSH_VERSION, true );
    wp_localize_script( 'bubbahub-push', 'BubbaHubPush', array(
        'firebase' => array(
            'apiKey' => $c['apiKey'],
            'authDomain' => $c['authDomain'],
            'projectId' => $c['projectId'],
            'storageBucket' => $c['storageBucket'],
            'messagingSenderId' => $c['messagingSenderId'],
            'appId' => $c['appId'],
        ),
        'vapidKey' => $c['vapidKey'],
        'swUrl' => rest_url( 'bubbahub/v1/push-sw.js' ),
        'registerUrl' => rest_url( 'bubbahub/v1/push/register' ),
        'unregisterUrl' => rest_url( 'bubbahub/v1/push/unregister' ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
        'enabled' => bubbahub_push_is_configured(),
        'userEnabled' => (bool) get_user_meta( get_current_user_id(), 'bubbahub_push_enabled', true ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'bubbahub_push_enqueue_assets', 30 );

function bubbahub_push_register_routes() {
    register_rest_route( 'bubbahub/v1', '/push-sw\.js', array(
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'callback' => 'bubbahub_push_service_worker',
    ) );
    register_rest_route( 'bubbahub/v1', '/push/register', array(
        'methods' => 'POST',
        'permission_callback' => function() { return is_user_logged_in(); },
        'callback' => 'bubbahub_push_register_device',
    ) );
    register_rest_route( 'bubbahub/v1', '/push/unregister', array(
        'methods' => 'POST',
        'permission_callback' => function() { return is_user_logged_in(); },
        'callback' => 'bubbahub_push_unregister_device',
    ) );
}
add_action( 'rest_api_init', 'bubbahub_push_register_routes' );

function bubbahub_push_service_worker() {
    $c = bubbahub_push_config();
    $config = wp_json_encode( array(
        'apiKey' => $c['apiKey'],
        'authDomain' => $c['authDomain'],
        'projectId' => $c['projectId'],
        'storageBucket' => $c['storageBucket'],
        'messagingSenderId' => $c['messagingSenderId'],
        'appId' => $c['appId'],
    ) );
    $home = wp_json_encode( home_url( '/' ) );
    $sdk = esc_url_raw( 'https://www.gstatic.com/firebasejs/' . BUBBAHUB_FIREBASE_JS_VERSION . '/firebase-app-compat.js' );
    $messaging = esc_url_raw( 'https://www.gstatic.com/firebasejs/' . BUBBAHUB_FIREBASE_JS_VERSION . '/firebase-messaging-compat.js' );
    $js = "importScripts(" . wp_json_encode( $sdk ) . ");\n";
    $js .= "importScripts(" . wp_json_encode( $messaging ) . ");\n";
    $js .= "firebase.initializeApp(" . $config . ");\n";
    $js .= "const messaging = firebase.messaging();\n";
    $js .= "messaging.onBackgroundMessage(function(payload){\n";
    $js .= "  const n = payload.notification || {}; const d = payload.data || {};\n";
    $js .= "  const title = n.title || d.title || 'Bubba Hub';\n";
    $js .= "  const options = { body: n.body || d.body || '', icon: n.icon || '" . esc_js( get_site_icon_url( 192 ) ) . "', badge: n.badge || '" . esc_js( get_site_icon_url( 192 ) ) . "', data: { url: d.url || " . $home . " }, tag: d.tag || 'bubbahub-notification' };\n";
    $js .= "  return self.registration.showNotification(title, options);\n";
    $js .= "});\n";
    $js .= "self.addEventListener('notificationclick', function(event){ event.notification.close(); const url = (event.notification.data && event.notification.data.url) || " . $home . "; event.waitUntil(clients.matchAll({type:'window',includeUncontrolled:true}).then(function(list){ for (const client of list) { if ('focus' in client) { client.navigate(url); return client.focus(); } } if (clients.openWindow) return clients.openWindow(url); })); });\n";
    return new WP_REST_Response( $js, 200, array( 'Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'no-store' ) );
}

function bubbahub_push_register_device( WP_REST_Request $request ) {
    if ( ! bubbahub_push_is_configured() ) return new WP_Error( 'push_not_configured', 'Push notifications are not configured yet.', array( 'status' => 503 ) );
    $token = sanitize_text_field( (string) $request->get_param( 'token' ) );
    if ( ! $token || strlen( $token ) < 20 || strlen( $token ) > 4096 ) return new WP_Error( 'invalid_token', 'Invalid push registration.', array( 'status' => 400 ) );
    $user_id = get_current_user_id();
    $devices = get_user_meta( $user_id, 'bubbahub_push_devices', true );
    if ( ! is_array( $devices ) ) $devices = array();
    $hash = hash( 'sha256', $token );
    $devices[ $hash ] = array(
        'token' => $token,
        'last_seen' => current_time( 'mysql', true ),
        'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
    );
    if ( count( $devices ) > 10 ) {
        uasort( $devices, function( $a, $b ) { return strcmp( $a['last_seen'] ?? '', $b['last_seen'] ?? '' ); } );
        while ( count( $devices ) > 10 ) array_shift( $devices );
    }
    update_user_meta( $user_id, 'bubbahub_push_devices', $devices );
    update_user_meta( $user_id, 'bubbahub_push_enabled', 1 );
    return array( 'success' => true );
}

function bubbahub_push_unregister_device( WP_REST_Request $request ) {
    $token = sanitize_text_field( (string) $request->get_param( 'token' ) );
    $user_id = get_current_user_id();
    $devices = get_user_meta( $user_id, 'bubbahub_push_devices', true );
    if ( is_array( $devices ) && $token ) unset( $devices[ hash( 'sha256', $token ) ] );
    update_user_meta( $user_id, 'bubbahub_push_devices', is_array( $devices ) ? $devices : array() );
    if ( empty( $devices ) ) update_user_meta( $user_id, 'bubbahub_push_enabled', 0 );
    return array( 'success' => true );
}

function bubbahub_push_get_access_token() {
    $cached = get_transient( 'bubbahub_fcm_access_token' );
    if ( $cached ) return $cached;
    $sa = bubbahub_push_service_account();
    if ( empty( $sa['client_email'] ) || empty( $sa['private_key'] ) ) return '';
    $now = time();
    $header = rtrim( strtr( base64_encode( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ), '+/', '-_' ), '=' );
    $claim = rtrim( strtr( base64_encode( wp_json_encode( array( 'iss' => $sa['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600 ) ) ), '+/', '-_' ), '=' );
    $unsigned = $header . '.' . $claim;
    if ( ! openssl_sign( $unsigned, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256 ) ) return '';
    $jwt = $unsigned . '.' . rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );
    $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
        'timeout' => 15,
        'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
        'body' => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt ),
    ) );
    if ( is_wp_error( $response ) ) return '';
    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( empty( $body['access_token'] ) ) return '';
    set_transient( 'bubbahub_fcm_access_token', $body['access_token'], max( 300, min( 3500, absint( $body['expires_in'] ?? 3600 ) - 60 ) ) );
    return $body['access_token'];
}

function bubbahub_push_send( $user_id, $title, $message, $url = '', $data = array() ) {
    if ( ! bubbahub_push_is_configured() || ! bubbahub_notification_channel_enabled( $user_id, 'booking', 'push' ) && empty( $data['force'] ) ) return false;
    $c = bubbahub_push_config();
    $token = bubbahub_push_get_access_token();
    if ( ! $token ) return false;
    $devices = get_user_meta( absint( $user_id ), 'bubbahub_push_devices', true );
    if ( ! is_array( $devices ) || empty( $devices ) ) return false;
    $ok = false;
    foreach ( $devices as $hash => $device ) {
        $device_token = isset( $device['token'] ) ? $device['token'] : '';
        if ( ! $device_token ) continue;
        $payload = array(
            'message' => array(
                'token' => $device_token,
                'notification' => array( 'title' => wp_strip_all_tags( $title ), 'body' => wp_strip_all_tags( $message ) ),
                'data' => array_merge( array( 'url' => $url ? esc_url_raw( $url ) : home_url( '/my-hub/' ), 'title' => wp_strip_all_tags( $title ), 'body' => wp_strip_all_tags( $message ) ), array_map( 'strval', $data ) ),
                'webpush' => array( 'fcm_options' => array( 'link' => $url ? esc_url_raw( $url ) : home_url( '/my-hub/' ) ) ),
            ),
        );
        $response = wp_remote_post( 'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $c['projectId'] ) . '/messages:send', array(
            'timeout' => 15,
            'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json; UTF-8' ),
            'body' => wp_json_encode( $payload ),
        ) );
        if ( is_wp_error( $response ) ) continue;
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $code >= 200 && $code < 300 ) { $ok = true; $devices[ $hash ]['last_seen'] = current_time( 'mysql', true ); continue; }
        if ( $code === 404 || ( $code === 400 && strpos( $body, 'UNREGISTERED' ) !== false ) ) unset( $devices[ $hash ] );
    }
    update_user_meta( absint( $user_id ), 'bubbahub_push_devices', $devices );
    return $ok;
}

function bubbahub_push_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $c = bubbahub_push_config();
    if ( isset( $_POST['bubbahub_push_save'] ) && check_admin_referer( 'bubbahub_push_settings' ) ) {
        $keys = array( 'api_key','auth_domain','project_id','storage_bucket','messaging_sender_id','app_id','vapid_key' );
        $new = array();
        foreach ( $keys as $key ) $new[ $key ] = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
        $new['enabled'] = ! empty( $_POST['enabled'] ) ? 1 : 0;
        update_option( 'bubbahub_push_settings', $new, false );
        if ( ! empty( $_POST['service_account_json'] ) ) update_option( 'bubbahub_fcm_service_account_json', trim( wp_unslash( $_POST['service_account_json'] ) ), false );
        $c = bubbahub_push_config();
        echo '<div class="notice notice-success"><p>Bubba Hub push settings saved.</p></div>';
    }
    ?>
    <div class="wrap">
      <h1>Bubba Hub Push Notifications</h1>
      <p>Configure Firebase Cloud Messaging for the installed Bubba Hub PWA. Do not publish service-account credentials in the plugin repository.</p>
      <form method="post">
        <?php wp_nonce_field( 'bubbahub_push_settings' ); ?>
        <table class="form-table" role="presentation">
        <tr><th>Enable push</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $c['enabled'] ); ?>> Enable Bubba Hub push registration</label></td></tr>
        <tr><th>Firebase API key</th><td><input class="regular-text" name="api_key" value="<?php echo esc_attr( $c['apiKey'] ); ?>"></td></tr>
        <tr><th>Auth domain</th><td><input class="regular-text" name="auth_domain" value="<?php echo esc_attr( $c['authDomain'] ); ?>"></td></tr>
        <tr><th>Project ID</th><td><input class="regular-text" name="project_id" value="<?php echo esc_attr( $c['projectId'] ); ?>"></td></tr>
        <tr><th>Storage bucket</th><td><input class="regular-text" name="storage_bucket" value="<?php echo esc_attr( $c['storageBucket'] ); ?>"></td></tr>
        <tr><th>Messaging sender ID</th><td><input class="regular-text" name="messaging_sender_id" value="<?php echo esc_attr( $c['messagingSenderId'] ); ?>"></td></tr>
        <tr><th>App ID</th><td><input class="regular-text" name="app_id" value="<?php echo esc_attr( $c['appId'] ); ?>"></td></tr>
        <tr><th>Web Push VAPID public key</th><td><input class="large-text" name="vapid_key" value="<?php echo esc_attr( $c['vapidKey'] ); ?>"></td></tr>
        <tr><th>Firebase service account JSON</th><td><textarea class="large-text code" rows="8" name="service_account_json" placeholder="Paste the Firebase service-account JSON here. Prefer the BUBBAHUB_FCM_SERVICE_ACCOUNT_JSON wp-config.php constant for production."></textarea><p class="description">The server credential is private. Never commit it to GitHub.</p></td></tr>
        </table>
        <p><button type="submit" name="bubbahub_push_save" class="button button-primary">Save push settings</button></p>
      </form>
    </div>
    <?php
}
function bubbahub_push_admin_menu() { add_options_page( 'Bubba Hub Push', 'Bubba Hub Push', 'manage_options', 'bubbahub-push', 'bubbahub_push_settings_page' ); }
add_action( 'admin_menu', 'bubbahub_push_admin_menu' );
