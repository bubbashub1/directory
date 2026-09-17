<?php
/**
 * Plugin Name: Bubba Hub Internet & Group Monitor
 * Description: Monitors Bubba Hub group listings, external websites, Google results and discovers potential new or inactive groups.
 * Version: 1.0.0
 * Author: Bubba Hub
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) exit;

final class BubbaHub_Internet_Group_Monitor {
    const OPT = 'bh_igm_settings';
    const TABLE = 'bh_igm_events';
    const CRON = 'bh_igm_scan';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_init', [ $this, 'settings' ] );
        add_action( self::CRON, [ $this, 'scan' ] );
        add_action( 'admin_post_bh_igm_scan', [ $this, 'manual_scan' ] );
        add_action( 'admin_post_bh_igm_status', [ $this, 'set_status' ] );
    }

    public static function activate() {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint unsigned NULL,
            type varchar(30) NOT NULL,
            title text NOT NULL,
            url text NULL,
            region varchar(190) NULL,
            details longtext NULL,
            fingerprint char(64) NULL,
            status varchar(30) NOT NULL DEFAULT 'open',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY post_type (post_id,type),
            KEY status (status)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        if ( ! wp_next_scheduled( self::CRON ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
    }

    public static function deactivate() { wp_clear_scheduled_hook( self::CRON ); }

    private function defaults() {
        return [
            'enabled' => 1,
            'email' => get_option( 'admin_email' ),
            'google_key' => '',
            'google_cx' => '',
            'inactive_days' => 120,
            'regions' => 'East Cornwall,East Devon,Exeter,Mid Cornwall,Mid Devon,North Cornwall,North Devon,Plymouth,South Cornwall,South Hams,Teignbridge,Torbay,West Cornwall,West Devon',
            'keywords' => 'baby group,toddler group,baby and toddler,baby class,parent and baby,pregnancy group,antenatal,postnatal,forest school,sensory class,music class,dance class,parent group',
        ];
    }
    private function settings_data() { return wp_parse_args( get_option( self::OPT, [] ), $this->defaults() ); }

    public function settings() { register_setting( 'bh_igm', self::OPT, [ $this, 'sanitize' ] ); }
    public function sanitize( $in ) {
        $d = $this->defaults();
        return [
            'enabled' => empty( $in['enabled'] ) ? 0 : 1,
            'email' => sanitize_email( $in['email'] ?? $d['email'] ),
            'google_key' => sanitize_text_field( $in['google_key'] ?? '' ),
            'google_cx' => sanitize_text_field( $in['google_cx'] ?? '' ),
            'inactive_days' => max( 30, min( 730, (int) ( $in['inactive_days'] ?? 120 ) ) ),
            'regions' => sanitize_text_field( $in['regions'] ?? $d['regions'] ),
            'keywords' => sanitize_text_field( $in['keywords'] ?? $d['keywords'] ),
        ];
    }

    public function menu() {
        add_menu_page( 'Bubba Hub Monitor', 'Bubba Hub Monitor', 'manage_options', 'bh-igm', [ $this, 'page' ], 'dashicons-search', 58 );
    }

    public function page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        global $wpdb;
        $s = $this->settings_data();
        $table = $wpdb->prefix . self::TABLE;
        $open = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='open'" );
        $new = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE type='new_group' AND status='open'" );
        $inactive = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE type='inactive' AND status='open'" );
        $changes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE type='change' AND status='open'" );
        ?>
        <div class="wrap">
            <h1>Bubba Hub Internet & Group Monitor</h1>
            <p>Automatic directory maintenance: website changes, contact changes, Google discoveries and possible inactive groups.</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin:20px 0">
                <?php foreach ( [ 'Open alerts' => $open, 'New groups' => $new, 'Possibly inactive' => $inactive, 'Changes' => $changes ] as $label => $count ) : ?>
                    <div style="background:#fff;border:1px solid #ddd;padding:18px;min-width:160px"><strong><?php echo esc_html( $label ); ?></strong><div style="font-size:28px"><?php echo esc_html( $count ); ?></div></div>
                <?php endforeach; ?>
            </div>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="bh_igm_scan"><?php wp_nonce_field( 'bh_igm_scan' ); ?>
                <button class="button button-primary">Scan Now</button>
            </form>
            <h2>Open items</h2>
            <table class="widefat striped"><thead><tr><th>Type</th><th>Group</th><th>Region</th><th>Details</th><th>Detected</th><th></th></tr></thead><tbody>
            <?php $rows = $wpdb->get_results( "SELECT * FROM $table WHERE status='open' ORDER BY updated_at DESC LIMIT 100" ); foreach ( $rows as $row ) : ?>
                <tr><td><?php echo esc_html( ucwords( str_replace( '_', ' ', $row->type ) ) ); ?></td><td><strong><?php echo esc_html( $row->title ); ?></strong><?php if ( $row->url ) : ?><br><a href="<?php echo esc_url( $row->url ); ?>" target="_blank" rel="noopener">Open source</a><?php endif; ?></td><td><?php echo esc_html( $row->region ); ?></td><td><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $row->details ), 28 ) ); ?></td><td><?php echo esc_html( $row->updated_at ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="bh_igm_status"><input type="hidden" name="id" value="<?php echo (int) $row->id; ?>"><input type="hidden" name="status" value="resolved"><?php wp_nonce_field( 'bh_igm_status' ); ?><button class="button">Resolve</button></form></td></tr>
            <?php endforeach; if ( ! $rows ) : ?><tr><td colspan="6">No open items.</td></tr><?php endif; ?></tbody></table>
            <hr><h2>Settings</h2>
            <form method="post" action="options.php"><?php settings_fields( 'bh_igm' ); ?>
                <table class="form-table">
                    <tr><th>Monitoring</th><td><label><input type="checkbox" name="<?php echo self::OPT; ?>[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?>> Enabled</label></td></tr>
                    <tr><th>Alert email</th><td><input class="regular-text" type="email" name="<?php echo self::OPT; ?>[email]" value="<?php echo esc_attr( $s['email'] ); ?>"></td></tr>
                    <tr><th>Google API key</th><td><input class="regular-text" type="password" name="<?php echo self::OPT; ?>[google_key]" value="<?php echo esc_attr( $s['google_key'] ); ?>"></td></tr>
                    <tr><th>Google Search Engine ID</th><td><input class="regular-text" name="<?php echo self::OPT; ?>[google_cx]" value="<?php echo esc_attr( $s['google_cx'] ); ?>"></td></tr>
                    <tr><th>Inactive threshold</th><td><input type="number" min="30" max="730" name="<?php echo self::OPT; ?>[inactive_days]" value="<?php echo (int) $s['inactive_days']; ?>"> days without a useful activity signal</td></tr>
                    <tr><th>Regions</th><td><textarea class="large-text" name="<?php echo self::OPT; ?>[regions]" rows="3"><?php echo esc_textarea( $s['regions'] ); ?></textarea><p class="description">Comma-separated Devon & Cornwall regions.</p></td></tr>
                    <tr><th>Discovery keywords</th><td><textarea class="large-text" name="<?php echo self::OPT; ?>[keywords]" rows="4"><?php echo esc_textarea( $s['keywords'] ); ?></textarea></td></tr>
                </table><?php submit_button( 'Save Settings' ); ?></form>
        </div><?php
    }

    public function manual_scan() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised' );
        check_admin_referer( 'bh_igm_scan' );
        $this->scan( true );
        wp_safe_redirect( admin_url( 'admin.php?page=bh-igm&scan=done' ) ); exit;
    }
    public function set_status() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised' );
        check_admin_referer( 'bh_igm_status' );
        global $wpdb; $wpdb->update( $wpdb->prefix . self::TABLE, [ 'status' => sanitize_key( $_POST['status'] ?? 'resolved' ) ], [ 'id' => (int) $_POST['id'] ], [ '%s' ], [ '%d' ] );
        wp_safe_redirect( admin_url( 'admin.php?page=bh-igm' ) ); exit;
    }

    public function scan( $manual = false ) {
        $s = $this->settings_data();
        if ( ! $manual && empty( $s['enabled'] ) ) return;
        $events = [];
        $posts = get_posts( [ 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => -1 ] );
        foreach ( $posts as $post ) {
            $location = $this->location( $post->ID );
            $urls = $this->urls( $post );
            $identity = [ 'title' => get_the_title( $post ), 'location' => $location, 'urls' => $urls ];
            $this->event_if_changed( $post->ID, 'change', get_the_title( $post ), get_permalink( $post ), $location, wp_json_encode( $identity ), $events );
            foreach ( $urls as $url ) {
                $page = $this->fetch( $url );
                if ( ! $page ) { $this->event_if_changed( $post->ID, 'inactive', get_the_title( $post ), $url, $location, 'External website is unavailable or returned an error.', $events, 'unavailable' ); continue; }
                $this->event_if_changed( $post->ID, 'change', get_the_title( $post ), $url, $location, wp_json_encode( $page ), $events );
                if ( $this->looks_closed( $page['text'] ) ) $this->event_if_changed( $post->ID, 'inactive', get_the_title( $post ), $url, $location, 'Source contains wording that may indicate closure or that the group is no longer running.', $events, 'closure-signal' );
            }
        }
        if ( ! empty( $s['google_key'] ) && ! empty( $s['google_cx'] ) ) $this->discover( $s, $events );
        if ( $events ) $this->email( $events );
    }

    private function location( $post_id ) {
        $parts = [];
        foreach ( [ 'address','street','city','region','zip','location' ] as $key ) {
            $v = function_exists( 'get_field' ) ? get_field( $key, $post_id ) : get_post_meta( $post_id, $key, true );
            if ( is_scalar( $v ) && trim( (string) $v ) !== '' ) $parts[] = trim( wp_strip_all_tags( (string) $v ) );
        }
        $terms = get_the_terms( $post_id, 'region' );
        if ( ! is_wp_error( $terms ) && $terms ) foreach ( $terms as $term ) $parts[] = $term->name;
        return implode( ', ', array_values( array_unique( $parts ) ) );
    }

    private function urls( $post ) {
        $urls = [];
        $meta = get_post_meta( $post->ID );
        $haystack = $post->post_content;
        foreach ( $meta as $vals ) foreach ( (array) $vals as $v ) if ( is_string( $v ) ) $haystack .= ' ' . $v;
        preg_match_all( '~https?://[^\s"\'<>]+~i', $haystack, $m );
        foreach ( (array) ( $m[0] ?? [] ) as $url ) {
            $url = esc_url_raw( rtrim( $url, '.,;)' ) );
            if ( $url && wp_http_validate_url( $url ) ) {
                $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
                if ( ! in_array( $host, [ 'bubbahub.co.uk', 'www.bubbahub.co.uk' ], true ) ) $urls[] = $url;
            }
        }
        return array_values( array_unique( array_slice( $urls, 0, 10 ) ) );
    }

    private function fetch( $url ) {
        $r = wp_safe_remote_get( $url, [ 'timeout' => 12, 'redirection' => 4, 'limit_response_size' => 800000, 'headers' => [ 'User-Agent' => 'BubbaHub-Monitor/1.0' ] ] );
        if ( is_wp_error( $r ) ) return false;
        $code = wp_remote_retrieve_response_code( $r ); $body = wp_remote_retrieve_body( $r );
        if ( $code < 200 || $code >= 400 || ! $body ) return false;
        $text = preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $body ) );
        $title = ''; if ( preg_match( '~<title[^>]*>(.*?)</title>~is', $body, $m ) ) $title = trim( wp_strip_all_tags( $m[1] ) );
        return [ 'status' => $code, 'title' => $title, 'hash' => hash( 'sha256', substr( $text, 0, 300000 ) ), 'text' => substr( $text, 0, 30000 ) ];
    }

    private function looks_closed( $text ) {
        $patterns = [ 'no longer running', 'no longer operating', 'group has closed', 'we have closed', 'permanently closed', 'group is closed', 'classes have ended', 'ceased trading', 'closing permanently' ];
        foreach ( $patterns as $p ) if ( stripos( $text, $p ) !== false ) return true;
        return false;
    }

    private function discover( $s, &$events ) {
        $regions = array_filter( array_map( 'trim', explode( ',', $s['regions'] ) ) );
        $keywords = array_filter( array_map( 'trim', explode( ',', $s['keywords'] ) ) );
        foreach ( $regions as $region ) foreach ( $keywords as $keyword ) {
            $q = $keyword . ' ' . $region . ' Devon Cornwall';
            $results = $this->google( $q, $s );
            if ( $results === false ) continue;
            foreach ( $results as $item ) {
                $title = $item['title']; $url = $item['url'];
                if ( ! $title || ! $url || $this->is_own_listing( $url ) ) continue;
                if ( $this->matches_existing( $title, $url ) ) continue;
                $this->event_if_changed( null, 'new_group', $title, $url, $region, 'Potential new group discovered by Google search for: ' . $q, $events, hash( 'sha256', $url ) );
            }
        }
    }

    private function google( $query, $s ) {
        $url = add_query_arg( [ 'key' => $s['google_key'], 'cx' => $s['google_cx'], 'q' => $query, 'num' => 10 ], 'https://www.googleapis.com/customsearch/v1' );
        $r = wp_safe_remote_get( $url, [ 'timeout' => 15, 'headers' => [ 'User-Agent' => 'BubbaHub-Monitor/1.0' ] ] );
        if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) return false;
        $data = json_decode( wp_remote_retrieve_body( $r ), true ); if ( ! is_array( $data ) ) return false;
        $out = []; foreach ( (array) ( $data['items'] ?? [] ) as $item ) $out[] = [ 'title' => sanitize_text_field( $item['title'] ?? '' ), 'url' => esc_url_raw( $item['link'] ?? '' ), 'snippet' => sanitize_text_field( $item['snippet'] ?? '' ) ];
        return $out;
    }

    private function is_own_listing( $url ) { $host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ); return in_array( $host, [ 'bubbahub.co.uk', 'www.bubbahub.co.uk' ], true ); }
    private function matches_existing( $title, $url ) {
        $posts = get_posts( [ 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ] );
        $needle = strtolower( preg_replace( '/[^a-z0-9]+/i', ' ', $title ) );
        foreach ( $posts as $id ) {
            $existing = strtolower( preg_replace( '/[^a-z0-9]+/i', ' ', get_the_title( $id ) ) );
            if ( $existing && ( strpos( $needle, $existing ) !== false || strpos( $existing, $needle ) !== false ) ) return true;
            if ( strpos( strtolower( (string) get_post_meta( $id, 'website', true ) ), strtolower( $url ) ) !== false ) return true;
        }
        return false;
    }

    private function event_if_changed( $post_id, $type, $title, $url, $region, $details, &$events, $fingerprint = '' ) {
        global $wpdb; $table = $wpdb->prefix . self::TABLE;
        $fingerprint = $fingerprint ?: hash( 'sha256', $type . '|' . $post_id . '|' . $url . '|' . $details );
        $old = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE type=%s AND (post_id=%d OR (post_id IS NULL AND url=%s)) ORDER BY id DESC LIMIT 1", $type, (int) $post_id, $url ) );
        if ( $old && $old->fingerprint === $fingerprint ) return;
        $now = current_time( 'mysql' );
        $wpdb->insert( $table, [ 'post_id' => $post_id ? (int) $post_id : null, 'type' => $type, 'title' => $title, 'url' => $url, 'region' => $region, 'details' => $details, 'fingerprint' => $fingerprint, 'status' => 'open', 'created_at' => $now, 'updated_at' => $now ], [ '%d','%s','%s','%s','%s','%s','%s','%s','%s' ] );
        $events[] = [ 'type' => $type, 'title' => $title, 'url' => $url, 'region' => $region, 'details' => $details ];
    }

    private function email( $events ) {
        $s = $this->settings_data(); if ( empty( $s['email'] ) ) return;
        $subject = 'Bubba Hub Monitor: ' . count( $events ) . ' item(s) need review';
        $body = "Bubba Hub Internet & Group Monitor found the following items:\n\n";
        foreach ( $events as $e ) $body .= strtoupper( str_replace( '_', ' ', $e['type'] ) ) . "\n{$e['title']}\n{$e['region']}\n{$e['url']}\n{$e['details']}\n\n";
        wp_mail( $s['email'], $subject, $body );
    }
}

register_activation_hook( __FILE__, [ 'BubbaHub_Internet_Group_Monitor', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BubbaHub_Internet_Group_Monitor', 'deactivate' ] );
new BubbaHub_Internet_Group_Monitor();
