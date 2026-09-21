<?php
/**
 * Bubba Hub Booking Consent
 *
 * Links a configurable consent-form URL and a required acknowledgement to
 * every Bubba Hub booking/reservation. Consent is stored against the booking
 * with a version and timestamp so payment and reservation records remain linked.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( class_exists( 'BubbaHub_Booking_Consent' ) ) return;

final class BubbaHub_Booking_Consent {
    const VERSION = '1.0.0';
    const GROUP_REQUIRED = '_bh_consent_required';
    const GROUP_URL = '_bh_consent_form_url';
    const GROUP_VERSION = '_bh_consent_version';
    const GROUP_TITLE = '_bh_consent_form_title';
    const NONCE = 'bh_booking_consent';

    public function __construct() {
        add_action( 'add_meta_boxes_group', [ $this, 'group_meta_box' ] );
        add_action( 'save_post_group', [ $this, 'save_group_meta' ], 10, 2 );
        add_action( 'add_meta_boxes_bh_booking', [ $this, 'booking_meta_box' ] );
        add_action( 'save_post_bh_booking', [ $this, 'save_booking_consent' ], 20, 2 );
        add_action( 'bubbahub_booking_created', [ $this, 'capture_created_booking_consent' ], 10, 2 );
        add_action( 'wp_ajax_bubbahub_booking_reserve', [ $this, 'validate_ajax_reservation' ], 1 );
        add_action( 'wp_ajax_nopriv_bubbahub_booking_reserve', [ $this, 'validate_ajax_reservation' ], 1 );
        add_action( 'template_redirect', [ $this, 'validate_frontend_reservation' ], 1 );
        add_filter( 'rest_pre_dispatch', [ $this, 'validate_payment_request' ], 1, 3 );
        add_action( 'wp_footer', [ $this, 'frontend_consent_ui' ], 50 );
        add_filter( 'http_request_args', [ $this, 'attach_stripe_consent_metadata' ], 20, 2 );
    }

    public static function activate() {
        // No schema is required: consent is stored as booking post meta.
        if ( ! get_page_by_path( 'booking-consent' ) ) {
            wp_insert_post( array(
                'post_title'   => 'Booking Consent',
                'post_name'    => 'booking-consent',
                'post_content' => '[bubbahub_consent_form]',
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ) );
        }
    }

    public function group_meta_box() {
        add_meta_box( 'bubbahub-consent-settings', 'Bubba Hub Booking Consent', [ $this, 'render_group_meta_box' ], 'group', 'normal', 'default' );
    }

    public function render_group_meta_box( $post ) {
        wp_nonce_field( self::NONCE, self::NONCE . '_group' );
        $required = $this->group_required( $post->ID );
        $url = (string) get_post_meta( $post->ID, self::GROUP_URL, true );
        $version = (string) get_post_meta( $post->ID, self::GROUP_VERSION, true );
        $title = (string) get_post_meta( $post->ID, self::GROUP_TITLE, true );
        if ( '' === $version ) $version = '1.0';
        ?>
        <div class="bh-consent-admin-card">
            <p><label><input type="checkbox" name="_bh_consent_required" value="1" <?php checked( $required, true ); ?>> <strong>Require consent before every reservation/payment</strong></label></p>
            <p><label for="_bh_consent_form_url"><strong>Consent form URL</strong></label><br>
                <input class="widefat" type="url" id="_bh_consent_form_url" name="_bh_consent_form_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://example.com/consent-form"></p>
            <p class="description">Use the organiser's consent form, privacy/terms page or a form hosted elsewhere. If blank, the Bubba Hub Booking Consent page is used.</p>
            <p><label for="_bh_consent_form_title"><strong>Consent form name</strong></label><br>
                <input class="widefat" type="text" id="_bh_consent_form_title" name="_bh_consent_form_title" value="<?php echo esc_attr( $title ); ?>" placeholder="Booking consent form"></p>
            <p><label for="_bh_consent_version"><strong>Consent version</strong></label><br>
                <input type="text" id="_bh_consent_version" name="_bh_consent_version" value="<?php echo esc_attr( $version ); ?>" placeholder="1.0"></p>
            <p class="description">Increase the version whenever the consent wording changes. Existing bookings retain the version they accepted.</p>
        </div>
        <style>
            .bh-consent-admin-card{background:#f8fafc;border:1px solid #dcdcde;border-radius:10px;padding:16px}.bh-consent-admin-card p{margin:0 0 14px}.bh-consent-admin-card p:last-child{margin-bottom:0}.bh-consent-admin-card input[type=url],.bh-consent-admin-card input[type=text]{min-height:42px;padding:8px 10px;border-radius:7px}.bh-consent-admin-card .description{color:#646970}
        </style>
        <?php
    }

    public function save_group_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( empty( $_POST[ self::NONCE . '_group' ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_group' ] ) ), self::NONCE ) ) return;
        update_post_meta( $post_id, self::GROUP_REQUIRED, empty( $_POST['_bh_consent_required'] ) ? 0 : 1 );
        update_post_meta( $post_id, self::GROUP_URL, esc_url_raw( wp_unslash( $_POST['_bh_consent_form_url'] ?? '' ) ) );
        update_post_meta( $post_id, self::GROUP_TITLE, sanitize_text_field( wp_unslash( $_POST['_bh_consent_form_title'] ?? '' ) ) );
        $version = sanitize_text_field( wp_unslash( $_POST['_bh_consent_version'] ?? '1.0' ) );
        update_post_meta( $post_id, self::GROUP_VERSION, '' !== $version ? $version : '1.0' );
    }

    public function booking_meta_box() {
        add_meta_box( 'bubbahub-booking-consent', 'Consent Record', [ $this, 'render_booking_meta_box' ], 'bh_booking', 'side', 'high' );
    }

    public function render_booking_meta_box( $post ) {
        $status = get_post_meta( $post->ID, '_bh_consent_status', true );
        $version = get_post_meta( $post->ID, '_bh_consent_version', true );
        $when = get_post_meta( $post->ID, '_bh_consent_timestamp', true );
        $source = get_post_meta( $post->ID, '_bh_consent_source', true );
        $url = get_post_meta( $post->ID, '_bh_consent_form_url', true );
        $status = $status ?: 'missing';
        ?>
        <p><strong>Status:</strong> <?php echo esc_html( ucwords( str_replace( '_', ' ', $status ) ) ); ?></p>
        <p><strong>Version:</strong> <?php echo esc_html( $version ?: '—' ); ?></p>
        <p><strong>Accepted:</strong> <?php echo esc_html( $when ?: '—' ); ?></p>
        <p><strong>Source:</strong> <?php echo esc_html( $source ?: '—' ); ?></p>
        <?php if ( $url ) : ?><p><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Open consent form ↗</a></p><?php endif; ?>
        <p class="description">The consent record is attached to this booking and remains separate from the payment status.</p>
        <?php
    }

    private function group_required( $group_id ) {
        $value = get_post_meta( absint( $group_id ), self::GROUP_REQUIRED, true );
        return '' === $value ? true : (bool) $value;
    }

    public function group_id_from_request() {
        $group_id = isset( $_REQUEST['group_id'] ) ? absint( $_REQUEST['group_id'] ) : 0;
        if ( ! $group_id && ! empty( $_REQUEST['session_id'] ) && function_exists( 'bubbahub_booking_meta' ) ) {
            $group_id = absint( bubbahub_booking_meta( absint( $_REQUEST['session_id'] ), '_bh_group_id', 0 ) );
        }
        return $group_id;
    }

    public function form_url( $group_id ) {
        $url = esc_url_raw( get_post_meta( absint( $group_id ), self::GROUP_URL, true ) );
        return $url ?: add_query_arg( 'group_id', absint( $group_id ), home_url( '/booking-consent/' ) );
    }

    public function form_title( $group_id ) {
        return get_post_meta( absint( $group_id ), self::GROUP_TITLE, true ) ?: 'Booking consent form';
    }

    public function consent_version( $group_id ) {
        return get_post_meta( absint( $group_id ), self::GROUP_VERSION, true ) ?: '1.0';
    }

    public function has_valid_consent( $booking_id ) {
        $status = get_post_meta( absint( $booking_id ), '_bh_consent_status', true );
        return 'accepted' === $status && get_post_meta( absint( $booking_id ), '_bh_consent_timestamp', true );
    }

    private function request_acknowledged() {
        return ! empty( $_REQUEST['bh_consent_ack'] ) && '1' === (string) $_REQUEST['bh_consent_ack'];
    }

    private function reject( $message, $status = 400 ) {
        return new WP_Error( 'consent_required', $message, array( 'status' => $status ) );
    }

    private function validate_request( $group_id ) {
        if ( ! $group_id || 'group' !== get_post_type( $group_id ) ) return $this->reject( 'The booking group could not be identified.' );
        if ( ! $this->group_required( $group_id ) ) return true;
        if ( ! $this->request_acknowledged() ) return $this->reject( 'Please read the consent form and confirm your consent before continuing.' );
        return true;
    }

    public function validate_ajax_reservation() {
        $group_id = $this->group_id_from_request();
        $result = $this->validate_request( $group_id );
        if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message(), 'code' => $result->get_error_code() ), 400 );
    }

    public function validate_frontend_reservation() {
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) return;
        $action = sanitize_key( wp_unslash( $_POST['bh_reserve_submit'] ?? '' ) );
        if ( 'reserve_spot' !== $action && empty( $_POST['bh_reserve_submit'] ) ) return;
        $session_id = absint( $_POST['session_id'] ?? 0 );
        $group_id = $session_id && function_exists( 'bubbahub_booking_meta' ) ? absint( bubbahub_booking_meta( $session_id, '_bh_group_id', 0 ) ) : absint( $_POST['group_id'] ?? 0 );
        $result = $this->validate_request( $group_id );
        if ( is_wp_error( $result ) ) {
            $redirect = wp_get_referer() ?: home_url( '/book/' );
            wp_safe_redirect( add_query_arg( array( 'consent_error' => 1, 'group_id' => $group_id, 'session_id' => $session_id ), $redirect ) );
            exit;
        }
    }

    public function validate_payment_request( $result, $server, $request ) {
        $route = $request->get_route();
        if ( false === strpos( $route, '/bubbahub/v1/getpaid/checkout' ) && false === strpos( $route, '/bubbahub/v1/payment/checkout' ) ) return $result;
        $booking_id = 0;
        if ( function_exists( 'bubbahub_getpaid_find_booking_compat' ) && false !== strpos( $route, '/getpaid/checkout' ) ) $booking_id = bubbahub_getpaid_find_booking_compat( $request );
        if ( ! $booking_id ) $booking_id = absint( $request->get_param( 'booking_id' ) );
        if ( ! $booking_id ) return $this->reject( 'Booking consent could not be verified.', 400 );
        $group_id = absint( get_post_meta( $booking_id, '_bh_group_id', true ) );
        if ( $this->group_required( $group_id ) && ! $this->has_valid_consent( $booking_id ) ) return $this->reject( 'Consent must be accepted before payment can be started.', 409 );
        return $result;
    }

    /**
     * Capture consent after the booking engine has finished writing booking meta.
     * This complements save_post_bh_booking, which can fire before group metadata exists.
     */
    public function capture_created_booking_consent( $booking_id, $args = array() ) {
        $booking_id = absint( $booking_id );
        if ( ! $booking_id || 'bh_booking' !== get_post_type( $booking_id ) ) return;
        if ( get_post_meta( $booking_id, '_bh_consent_status', true ) ) return;
        $group_id = absint( get_post_meta( $booking_id, '_bh_group_id', true ) );
        if ( ! $group_id && is_array( $args ) ) $group_id = absint( $args['group_id'] ?? 0 );
        if ( ! $group_id ) return;
        if ( ! $this->group_required( $group_id ) ) {
            update_post_meta( $booking_id, '_bh_consent_status', 'not_required' );
            return;
        }
        if ( $this->request_acknowledged() ) $this->record_consent( $booking_id, $group_id, 'customer_checkout' );
        else update_post_meta( $booking_id, '_bh_consent_status', 'missing' );
    }

    public function save_booking_consent( $post_id, $post ) {
        if ( 'bh_booking' !== $post->post_type ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( get_post_meta( $post_id, '_bh_consent_status', true ) ) return;
        $group_id = absint( get_post_meta( $post_id, '_bh_group_id', true ) );
        if ( ! $group_id ) return;
        if ( ! $this->group_required( $group_id ) ) {
            update_post_meta( $post_id, '_bh_consent_status', 'not_required' );
            return;
        }
        if ( $this->request_acknowledged() ) $this->record_consent( $post_id, $group_id, 'customer_checkout' );
        else update_post_meta( $post_id, '_bh_consent_status', 'missing' );
    }

    public function record_consent( $booking_id, $group_id, $source = 'customer_checkout' ) {
        $version = $this->consent_version( $group_id );
        $url = $this->form_url( $group_id );
        $user_id = get_current_user_id();
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        update_post_meta( $booking_id, '_bh_consent_status', 'accepted' );
        update_post_meta( $booking_id, '_bh_consent_version', $version );
        update_post_meta( $booking_id, '_bh_consent_timestamp', current_time( 'mysql' ) );
        update_post_meta( $booking_id, '_bh_consent_source', sanitize_key( $source ) );
        update_post_meta( $booking_id, '_bh_consent_form_url', $url );
        update_post_meta( $booking_id, '_bh_consent_user_id', absint( $user_id ) );
        // Store a one-way IP fingerprint rather than the raw address.
        if ( $ip ) update_post_meta( $booking_id, '_bh_consent_ip_hash', hash( 'sha256', wp_salt( 'auth' ) . '|' . $ip ) );
        update_post_meta( $booking_id, '_bh_consent_record_id', 'BH-CONSENT-' . $booking_id . '-' . gmdate( 'YmdHis' ) );
    }

    public function frontend_consent_ui() {
        if ( ! is_page( 'book' ) ) return;
        $group_id = $this->group_id_from_request();
        if ( ! $group_id || ! $this->group_required( $group_id ) ) return;
        $url = $this->form_url( $group_id );
        $title = $this->form_title( $group_id );
        $version = $this->consent_version( $group_id );
        ?>
        <div id="bh-consent-template" hidden>
            <div class="bh-booking-consent" data-bh-consent>
                <div class="bh-booking-consent-heading"><strong>Consent required</strong><span>Version <?php echo esc_html( $version ); ?></span></div>
                <p>Please read the consent form before completing your reservation or payment.</p>
                <p><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $title ); ?> ↗</a></p>
                <label class="bh-booking-consent-check"><input type="checkbox" name="bh_consent_ack" value="1" data-bh-consent-checkbox> <span>I confirm that I have read the consent form and agree to the stated terms.</span></label>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded',function(){
            var tpl=document.querySelector('#bh-consent-template'); if(!tpl) return;
            function install(form){
                if(!form || form.querySelector('[data-bh-consent]')) return;
                var node=tpl.firstElementChild.cloneNode(true); node.hidden=false;
                var submit=form.querySelector('[type="submit"]');
                if(submit && submit.parentNode) submit.parentNode.insertBefore(node,submit);
                else form.appendChild(node);
                var cb=node.querySelector('[data-bh-consent-checkbox]');
                if(cb){
                    cb.addEventListener('change',function(){
                        if(submit && submit.getAttribute('data-ticket-submit')!==null) submit.disabled=!cb.checked;
                        form.setAttribute('data-consent-ready',cb.checked?'1':'0');
                    });
                    form.addEventListener('submit',function(e){if(!cb.checked){e.preventDefault();cb.focus();}});
                }
            }
            document.querySelectorAll('.bh-booking-reserve,.nf-form-cont').forEach(install);
            if(window.MutationObserver){var o=new MutationObserver(function(){document.querySelectorAll('.bh-booking-reserve,.nf-form-cont').forEach(install);});o.observe(document.body,{childList:true,subtree:true});}
        });
        </script>
        <style>
            .bh-booking-consent{margin:20px 0;padding:16px;border:1px solid #d9dfe7;border-radius:12px;background:#f8fafc}.bh-booking-consent-heading{display:flex;justify-content:space-between;gap:12px;align-items:center}.bh-booking-consent-heading strong{font-size:16px}.bh-booking-consent-heading span{font-size:12px;color:#667085}.bh-booking-consent p{margin:8px 0}.bh-booking-consent a{font-weight:700}.bh-booking-consent-check{display:flex;gap:10px;align-items:flex-start;margin-top:12px;font-weight:600}.bh-booking-consent-check input{margin-top:3px;min-width:18px;min-height:18px}@media(max-width:600px){.bh-booking-consent{padding:14px}.bh-booking-consent-heading{align-items:flex-start;flex-direction:column}}
        </style>
        <?php
    }

    public function attach_stripe_consent_metadata( $args, $url ) {
        if ( false === strpos( $url, 'api.stripe.com/v1/checkout/sessions' ) ) return $args;
        if ( empty( $args['body'] ) || ! is_string( $args['body'] ) ) return $args;
        parse_str( $args['body'], $body );
        $booking_id = ! empty( $body['metadata']['booking_id'] ) ? absint( $body['metadata']['booking_id'] ) : 0;
        if ( ! $booking_id || ! $this->has_valid_consent( $booking_id ) ) return $args;
        $body['metadata']['consent_status'] = 'accepted';
        $body['metadata']['consent_version'] = get_post_meta( $booking_id, '_bh_consent_version', true );
        $body['metadata']['consent_record_id'] = get_post_meta( $booking_id, '_bh_consent_record_id', true );
        $args['body'] = http_build_query( $body, '', '&' );
        return $args;
    }
}

new BubbaHub_Booking_Consent();
