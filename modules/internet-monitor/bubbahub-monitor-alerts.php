<?php
/**
 * Bubba Hub Monitor Alerts UI.
 * Provides the WordPress dashboard summary and a dedicated review inbox.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'BubbaHub_Monitor_Alerts_UI' ) ) return;

final class BubbaHub_Monitor_Alerts_UI {
    const PAGE = 'bh-igm-alerts';
    const TABLE = 'bh_igm_events';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'menu' ], 20 );
        add_action( 'wp_dashboard_setup', [ $this, 'dashboard_widget' ] );
        add_action( 'admin_post_bh_igm_alert_action', [ $this, 'action' ] );
        add_action( 'admin_notices', [ $this, 'admin_notice' ] );
        add_action( 'admin_head', [ $this, 'styles' ] );
    }

    private function table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    private function counts() {
        global $wpdb;
        $table = $this->table();
        return [
            'open' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='open'" ),
            'new_group' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='open' AND type='new_group'" ),
            'change' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='open' AND type='change'" ),
            'inactive' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status='open' AND type='inactive'" ),
        ];
    }

    public function menu() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        add_submenu_page(
            'bh-igm',
            'Monitor Alerts',
            'Alerts',
            'manage_options',
            self::PAGE,
            [ $this, 'page' ]
        );
    }

    public function dashboard_widget() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        wp_add_dashboard_widget( 'bubbahub_monitor_dashboard', 'Bubba Hub Monitor', [ $this, 'widget' ] );
    }

    public function widget() {
        $c = $this->counts();
        $url = admin_url( 'admin.php?page=' . self::PAGE );
        ?>
        <div class="bh-monitor-widget">
            <p class="bh-monitor-lead">Your directory is being checked for website changes, inactive groups and newly discovered groups.</p>
            <div class="bh-monitor-counts">
                <a href="<?php echo esc_url( add_query_arg( 'status', 'open', $url ) ); ?>"><strong><?php echo esc_html( $c['open'] ); ?></strong><span>Open alerts</span></a>
                <a href="<?php echo esc_url( add_query_arg( 'type', 'new_group', $url ) ); ?>"><strong><?php echo esc_html( $c['new_group'] ); ?></strong><span>New groups</span></a>
                <a href="<?php echo esc_url( add_query_arg( 'type', 'change', $url ) ); ?>"><strong><?php echo esc_html( $c['change'] ); ?></strong><span>Changes</span></a>
                <a href="<?php echo esc_url( add_query_arg( 'type', 'inactive', $url ) ); ?>"><strong><?php echo esc_html( $c['inactive'] ); ?></strong><span>Inactive</span></a>
            </div>
            <p><a class="button button-primary" href="<?php echo esc_url( $url ); ?>">Open Monitor Alerts</a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bh-igm' ) ); ?>">Monitor settings</a></p>
        </div>
        <?php
    }

    public function page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        global $wpdb;
        $table = $this->table();
        $type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
        $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'open';
        $allowed_types = [ '', 'new_group', 'change', 'inactive' ];
        $allowed_statuses = [ 'open', 'reviewed', 'active', 'dismissed', 'resolved', '' ];
        if ( ! in_array( $type, $allowed_types, true ) ) $type = '';
        if ( ! in_array( $status, $allowed_statuses, true ) ) $status = 'open';

        $where = [ '1=1' ];
        $args = [];
        if ( $type ) { $where[] = 'type=%s'; $args[] = $type; }
        if ( $status ) { $where[] = 'status=%s'; $args[] = $status; }
        $sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC LIMIT 200';
        $rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql );
        $counts = $this->counts();
        $base = admin_url( 'admin.php?page=' . self::PAGE );
        ?>
        <div class="wrap bh-monitor-alerts">
            <h1>Bubba Hub Monitor Alerts</h1>
            <p class="description">Review changes and possible group activity before making any directory changes. Nothing here automatically deletes or unpublishes a listing.</p>

            <div class="bh-alert-cards">
                <a class="bh-alert-card <?php echo 'open' === $status && ! $type ? 'is-current' : ''; ?>" href="<?php echo esc_url( $base ); ?>"><span>Open alerts</span><strong><?php echo esc_html( $counts['open'] ); ?></strong></a>
                <a class="bh-alert-card <?php echo 'new_group' === $type ? 'is-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'type', 'new_group', $base ) ); ?>"><span>New groups</span><strong><?php echo esc_html( $counts['new_group'] ); ?></strong></a>
                <a class="bh-alert-card <?php echo 'change' === $type ? 'is-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'type', 'change', $base ) ); ?>"><span>Website/contact changes</span><strong><?php echo esc_html( $counts['change'] ); ?></strong></a>
                <a class="bh-alert-card <?php echo 'inactive' === $type ? 'is-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'type', 'inactive', $base ) ); ?>"><span>Possible inactivity</span><strong><?php echo esc_html( $counts['inactive'] ); ?></strong></a>
            </div>

            <div class="bh-alert-toolbar">
                <div class="bh-alert-filters">
                    <?php foreach ( [ 'open' => 'Open', 'reviewed' => 'Reviewed', 'active' => 'Confirmed active', 'dismissed' => 'Dismissed', '' => 'All statuses' ] as $key => $label ) : ?>
                        <a class="button <?php echo $status === $key ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'status' => $key, 'type' => $type ], $base ) ); ?>"><?php echo esc_html( $label ); ?></a>
                    <?php endforeach; ?>
                </div>
                <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=bh-igm' ) ); ?>">← Monitor</a>
            </div>

            <?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Alert updated.</p></div><?php endif; ?>

            <div class="bh-alert-list">
                <?php if ( ! $rows ) : ?>
                    <div class="bh-alert-empty"><span class="dashicons dashicons-yes-alt"></span><h2>No alerts in this view</h2><p>Everything is clear for the selected filter.</p></div>
                <?php else : foreach ( $rows as $row ) :
                    $label = ucwords( str_replace( '_', ' ', $row->type ) );
                    $status_label = ucwords( str_replace( '_', ' ', $row->status ) );
                    $edit_url = $row->post_id ? get_edit_post_link( (int) $row->post_id, '' ) : '';
                    ?>
                    <article class="bh-alert-item">
                        <div class="bh-alert-main">
                            <div class="bh-alert-topline"><span class="bh-alert-type bh-type-<?php echo esc_attr( $row->type ); ?>"><?php echo esc_html( $label ); ?></span><span class="bh-alert-status"><?php echo esc_html( $status_label ); ?></span></div>
                            <h2><?php echo esc_html( $row->title ); ?></h2>
                            <?php if ( $row->region ) : ?><p class="bh-alert-location"><span class="dashicons dashicons-location"></span><?php echo esc_html( $row->region ); ?></p><?php endif; ?>
                            <div class="bh-alert-details"><?php echo wp_kses_post( nl2br( esc_html( wp_strip_all_tags( $row->details ) ) ) ); ?></div>
                            <p class="bh-alert-meta">Detected <?php echo esc_html( $row->created_at ); ?> · Last updated <?php echo esc_html( $row->updated_at ); ?></p>
                            <div class="bh-alert-links">
                                <?php if ( $row->url ) : ?><a href="<?php echo esc_url( $row->url ); ?>" target="_blank" rel="noopener">Open source ↗</a><?php endif; ?>
                                <?php if ( $edit_url ) : ?><a href="<?php echo esc_url( $edit_url ); ?>">View/edit listing</a><?php endif; ?>
                            </div>
                        </div>
                        <div class="bh-alert-actions">
                            <?php if ( 'open' === $row->status ) : ?>
                                <?php $this->action_form( $row->id, 'reviewed', 'Review' ); ?>
                                <?php if ( $row->post_id ) $this->action_form( $row->id, 'active', 'Confirm active', 'button button-secondary' ); ?>
                                <?php if ( $row->post_id ) : ?><a class="button" href="<?php echo esc_url( $edit_url ); ?>">Update listing</a><?php endif; ?>
                                <?php $this->action_form( $row->id, 'dismissed', 'Dismiss', 'button-link-delete' ); ?>
                            <?php else : ?>
                                <?php $this->action_form( $row->id, 'open', 'Re-open', 'button' ); ?>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <?php
    }

    private function action_form( $id, $status, $label, $class = 'button' ) {
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bh-alert-action-form">
            <input type="hidden" name="action" value="bh_igm_alert_action">
            <input type="hidden" name="id" value="<?php echo (int) $id; ?>">
            <input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
            <?php wp_nonce_field( 'bh_igm_alert_action_' . (int) $id ); ?>
            <button class="<?php echo esc_attr( $class ); ?>" type="submit"><?php echo esc_html( $label ); ?></button>
        </form>
        <?php
    }

    public function action() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorised' );
        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        check_admin_referer( 'bh_igm_alert_action_' . $id );
        $status = sanitize_key( $_POST['status'] ?? 'open' );
        $allowed = [ 'open', 'reviewed', 'active', 'dismissed', 'resolved' ];
        if ( ! in_array( $status, $allowed, true ) || ! $id ) wp_die( 'Invalid alert action.' );
        global $wpdb;
        $wpdb->update( $this->table(), [ 'status' => $status, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => $id ], [ '%s', '%s' ], [ '%d' ] );
        wp_safe_redirect( add_query_arg( 'updated', '1', wp_get_referer() ?: admin_url( 'admin.php?page=' . self::PAGE ) ) );
        exit;
    }

    public function admin_notice() {
        if ( ! current_user_can( 'manage_options' ) || ! is_admin() ) return;
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'dashboard' !== $screen->id ) return;
        $c = $this->counts();
        if ( ! $c['open'] ) return;
        ?>
        <div class="notice notice-warning bh-monitor-notice">
            <p><strong>Bubba Hub Monitor:</strong> <?php echo esc_html( $c['open'] ); ?> alert<?php echo 1 === $c['open'] ? '' : 's'; ?> need review. <a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ); ?>">Open Monitor Alerts</a></p>
        </div>
        <?php
    }

    public function styles() {
        if ( ! is_admin() ) return;
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( ( ! $screen || 'dashboard' !== $screen->id ) && self::PAGE !== $page ) return;
        ?>
        <style>
            .bh-monitor-widget .bh-monitor-lead{margin-top:0}.bh-monitor-counts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:14px 0}.bh-monitor-counts a{display:flex;flex-direction:column;gap:3px;text-decoration:none;border:1px solid #dcdcde;border-radius:8px;padding:12px;background:#fff}.bh-monitor-counts strong{font-size:25px;line-height:1}.bh-monitor-counts span{font-size:12px;color:#50575e}.bh-monitor-alerts{max-width:1400px}.bh-alert-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:22px 0}.bh-alert-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:18px;text-decoration:none;display:flex;flex-direction:column;gap:7px;box-shadow:0 1px 2px rgba(0,0,0,.03)}.bh-alert-card:hover,.bh-alert-card.is-current{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}.bh-alert-card span{font-size:13px;color:#50575e}.bh-alert-card strong{font-size:30px;line-height:1;color:#1d2327}.bh-alert-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:18px 0}.bh-alert-filters{display:flex;gap:7px;flex-wrap:wrap}.bh-alert-list{display:grid;gap:14px}.bh-alert-item{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:24px}.bh-alert-topline{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.bh-alert-type,.bh-alert-status{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.03em}.bh-alert-type{background:#f0f0f1;color:#3c434a}.bh-type-new_group{background:#e7f6ec;color:#166534}.bh-type-inactive{background:#fff3cd;color:#7a4b00}.bh-type-change{background:#e8f1fb;color:#135e96}.bh-alert-status{background:#f6f7f7;color:#50575e}.bh-alert-main h2{font-size:18px;margin:10px 0 5px}.bh-alert-location{color:#50575e;margin:0 0 12px}.bh-alert-location .dashicons{font-size:17px;vertical-align:-3px;margin-right:3px}.bh-alert-details{background:#f6f7f7;border-radius:7px;padding:12px 14px;line-height:1.55;word-break:break-word}.bh-alert-meta{font-size:12px;color:#646970;margin:10px 0}.bh-alert-links{display:flex;gap:14px;flex-wrap:wrap}.bh-alert-actions{display:flex;flex-direction:column;align-items:stretch;gap:7px;min-width:145px}.bh-alert-action-form{margin:0}.bh-alert-actions .button{width:100%;text-align:center;box-sizing:border-box}.bh-alert-empty{text-align:center;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:45px 20px}.bh-alert-empty .dashicons{font-size:42px;width:42px;height:42px;color:#46b450}.bh-alert-empty h2{margin:12px 0 5px}.bh-alert-empty p{margin:0;color:#646970}.bh-monitor-notice{margin-top:12px}
            @media(max-width:900px){.bh-alert-cards{grid-template-columns:repeat(2,minmax(0,1fr))}.bh-alert-item{grid-template-columns:1fr}.bh-alert-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));min-width:0}.bh-alert-actions .button{width:auto}.bh-monitor-counts{grid-template-columns:repeat(2,minmax(0,1fr))}}
            @media(max-width:600px){.bh-alert-cards{grid-template-columns:1fr}.bh-alert-item{padding:15px}.bh-alert-actions{grid-template-columns:1fr}.bh-alert-toolbar{align-items:stretch}.bh-alert-filters{display:grid;grid-template-columns:1fr 1fr}.bh-alert-filters .button{width:100%;text-align:center}.bh-monitor-counts{grid-template-columns:1fr 1fr}}
            @media(max-width:420px){.bh-monitor-counts{grid-template-columns:1fr}.bh-alert-filters{grid-template-columns:1fr}.bh-alert-details{font-size:13px}}
        </style>
        <?php
    }
}

new BubbaHub_Monitor_Alerts_UI();
