<?php
/**
 * Bubba Hub My Hub - Stage 2 account settings.
 *
 * Adds native WordPress/Ultimate Member/GetPaid-connected account settings
 * around the existing Stage 1 child-profile editor without changing the
 * stable My Hub dashboard or the Stage 1 child CRUD.
 *
 * Shortcode: [bubbahub_account_settings_stage2]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_account_settings_stage2_register', 36 );
add_action( 'template_redirect', 'bubbahub_stage2_post_redirect', 20 );
add_action( 'template_redirect', 'bubbahub_stage2_profile_redirect', 19 );

$GLOBALS['bubbahub_stage2_post_result'] = null;

function bubbahub_account_settings_stage2_register() {
    add_shortcode( 'bubbahub_account_settings_stage2', 'bubbahub_account_settings_stage2_shortcode' );
}

function bubbahub_stage2_profile_redirect() {
    if ( ! is_user_logged_in() || empty( $_GET['bh_account_settings'] ) ) return;
    if ( 'profile' !== sanitize_key( wp_unslash( $_GET['bh_settings_section'] ?? '' ) ) ) return;
    if ( ! function_exists( 'um_get_core_page' ) ) return;
    $account_url = bubbahub_stage2_account_url();
    if ( ! $account_url ) return;
    $profile_url = add_query_arg( 'um_tab', 'bubbahub_profile', $account_url );
    wp_safe_redirect( $profile_url );
    exit;
}

function bubbahub_stage2_post_redirect() {
    if ( ! is_user_logged_in() || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['bh_stage2_action'] ) ) return;

    $action = sanitize_key( wp_unslash( $_POST['bh_stage2_action'] ) );
    $handlers = array(
        'profile'       => 'bubbahub_stage2_handle_profile',
        'notifications' => 'bubbahub_stage2_handle_notifications',
        'consent'       => 'bubbahub_stage2_handle_consent',
        'preferences'   => 'bubbahub_stage2_handle_preferences',
        'family_needs'  => 'bubbahub_stage2_handle_preferences',
                'calendar'     => 'bubbahub_stage2_handle_calendar_settings',
        'notification_test' => 'bubbahub_stage2_handle_notification_test',
        'privacy'      => 'bubbahub_stage2_handle_privacy',
    );
    if ( empty( $handlers[ $action ] ) || ! function_exists( $handlers[ $action ] ) ) return;

    $result = call_user_func( $handlers[ $action ] );
    $GLOBALS['bubbahub_stage2_post_result'] = $result;

    $successful_results = array(
        'Profile details updated successfully.',
        'Notification preferences saved.',
        'Consent and safety details saved.',
        'Interests and group preferences saved.',
        'Family needs and discovery preferences saved.',
        'My Bubba Hub Preferences saved.',
        'Calendar settings saved.',
        'Test notification sent.',
        'Privacy request saved.',
    );

    if ( in_array( $result, $successful_results, true ) ) {
        $dashboard_url = add_query_arg(
            'bh_account_settings',
            '1',
            remove_query_arg( 'bh_settings_section', wp_get_referer() ?: home_url( '/my-hub/' ) )
        );
        wp_safe_redirect( $dashboard_url );
        exit;
    }
}

/* -------------------------------------------------------------------------
 * Shared helpers
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_user_meta( $key, $default = '' ) {
    $value = get_user_meta( get_current_user_id(), $key, true );
    return ( '' !== $value && null !== $value && false !== $value ) ? $value : $default;
}

function bubbahub_stage2_update_meta( $key, $value ) {
    return update_user_meta( get_current_user_id(), $key, $value );
}

function bubbahub_stage2_url( $filter, $fallback ) {
    $url = apply_filters( $filter, '' );
    return $url ? esc_url_raw( $url ) : home_url( $fallback );
}

function bubbahub_stage2_account_url() {
    if ( function_exists( 'um_get_core_page' ) ) {
        $url = um_get_core_page( 'account' );
        if ( $url ) return esc_url_raw( $url );
    }
    return home_url( '/account/' );
}

function bubbahub_stage2_find_page_by_shortcode( $shortcode ) {
    if ( ! shortcode_exists( $shortcode ) ) return '';

    $pages = get_pages(
        array(
            'post_status' => 'publish',
            'number'       => 100,
            'orderby'      => 'ID',
            'order'        => 'ASC',
        )
    );

    foreach ( $pages as $page ) {
        if ( has_shortcode( (string) $page->post_content, $shortcode ) ) {
            return esc_url_raw( get_permalink( $page->ID ) );
        }
    }

    return '';
}

function bubbahub_stage2_payment_url() {
    $url = apply_filters( 'bubbahub_getpaid_account_url', '' );
    if ( $url ) return esc_url_raw( $url );

    $url = bubbahub_stage2_find_page_by_shortcode( 'wpinv_history' );
    if ( $url ) return $url;

    return home_url( '/wpi-checkout/wpi-history/' );
}

function bubbahub_stage2_getpaid_shortcode_output( $needles = array() ) {
    global $shortcode_tags;

    if ( empty( $shortcode_tags ) || empty( $needles ) ) return '';

    foreach ( $shortcode_tags as $tag => $callback ) {
        $tag_lc = strtolower( (string) $tag );
        $match = false;

        foreach ( $needles as $needle ) {
            if ( false !== strpos( $tag_lc, strtolower( (string) $needle ) ) && ( false !== strpos( $tag_lc, 'wpinv' ) || false !== strpos( $tag_lc, 'getpaid' ) ) ) {
                $match = true;
                break;
            }
        }

        if ( ! $match ) continue;

        $output = do_shortcode( '[' . $tag . ']' );
        if ( trim( wp_strip_all_tags( (string) $output ) ) || trim( (string) $output ) ) {
            return $output;
        }
    }

    return '';
}

function bubbahub_stage2_getpaid_checkout_url() {
    $url = bubbahub_stage2_find_page_by_shortcode( 'wpinv_checkout' );
    return $url ? $url : home_url( '/wpi-checkout/' );
}

function bubbahub_stage2_getpaid_invoice_url() {
    $url = bubbahub_stage2_find_page_by_shortcode( 'wpinv_history' );
    return $url ? $url : bubbahub_stage2_payment_url();
}

function bubbahub_stage2_getpaid_subscription_url() {
    $url = bubbahub_stage2_find_page_by_shortcode( 'wpinv_subscriptions' );
    return $url ? $url : bubbahub_stage2_payment_url();
}

/* -------------------------------------------------------------------------
 * Ultimate Member account integration
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_register_um_profile_tab() {
    if ( ! function_exists( 'UM' ) ) return;
    add_filter( 'um_account_page_default_tabs_hook', 'bubbahub_stage2_um_profile_tab', 100 );
    add_filter( 'um_account_content_hook_bubbahub_profile', 'bubbahub_stage2_um_profile_content', 20, 2 );
    add_action( 'um_submit_account_errors_hook', 'bubbahub_stage2_um_profile_validate', 20, 1 );
    add_action( 'um_after_user_account_updated', 'bubbahub_stage2_um_profile_save', 20, 2 );
}
add_action( 'init', 'bubbahub_stage2_register_um_profile_tab', 40 );

function bubbahub_stage2_um_profile_tab( $tabs ) {
    $tabs[ 150 ]['bubbahub_profile'] = array(
        'icon'         => 'um-faicon-user',
        'title'        => __( 'My Bubba Hub Profile', 'bubbahub' ),
        'submit_title' => __( 'Save profile details', 'bubbahub' ),
        'custom'       => true,
    );
    return $tabs;
}

function bubbahub_stage2_um_profile_content( $output = '', $shortcode_args = array() ) {
    if ( ! is_user_logged_in() ) return $output;

    $uid = get_current_user_id();
    $user = wp_get_current_user();
    $relationships = array(
        'mum'            => 'Mum',
        'dad'            => 'Dad',
        'parent'         => 'Parent',
        'step-parent'    => 'Step-parent',
        'carer'          => 'Carer',
        'foster-carer'   => 'Foster carer',
        'grandparent'    => 'Grandparent',
        'guardian'       => 'Guardian',
        'family-member'  => 'Family member',
        'other'          => 'Other',
    );

    $relationship = (string) get_user_meta( $uid, 'bubbahub_relationship_to_children', true );
    $fields = array(
        'phone'              => get_user_meta( $uid, 'bubbahub_phone', true ),
        'address1'           => get_user_meta( $uid, 'bubbahub_address1', true ),
        'address2'           => get_user_meta( $uid, 'bubbahub_address2', true ),
        'town'               => get_user_meta( $uid, 'bubbahub_town', true ),
        'county'             => get_user_meta( $uid, 'bubbahub_county', true ),
        'postcode'            => get_user_meta( $uid, 'bubbahub_postcode', true ),
        'billing_address1'   => get_user_meta( $uid, 'bubbahub_billing_address1', true ),
        'billing_address2'   => get_user_meta( $uid, 'bubbahub_billing_address2', true ),
        'billing_town'       => get_user_meta( $uid, 'bubbahub_billing_town', true ),
        'billing_county'     => get_user_meta( $uid, 'bubbahub_billing_county', true ),
        'billing_postcode'   => get_user_meta( $uid, 'bubbahub_billing_postcode', true ),
    );

    $profile_url = function_exists( 'um_user_profile_url' ) ? um_user_profile_url( $uid ) : '';
    $avatar = '';
    if ( function_exists( 'um_fetch_user' ) && function_exists( 'um_user' ) ) {
        um_fetch_user( $uid );
        $avatar = um_user( 'profile_photo', 96 );
        um_reset_user();
    }

    ob_start();
    ?>
    <div class="bh-um-bubba-profile">
        <div class="bh-um-profile-intro">
            <div class="bh-um-profile-avatar">
                <?php echo $avatar ? $avatar : '<span aria-hidden="true">👤</span>'; ?>
            </div>
            <div>
                <h3>My Bubba Hub Profile</h3>
                <p>Keep the details Bubba Hub uses for your account, bookings and family connections in one place.</p>
                <?php if ( $profile_url ) : ?>
                    <a class="bh-um-profile-photo-link" href="<?php echo esc_url( $profile_url ); ?>">Change profile photo</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="bh-um-profile-section">
            <h4>Personal details</h4>
            <div class="bh-um-profile-grid">
                <div class="bh-um-field">
                    <label for="bubbahub_relationship_to_children">Relationship to child(ren)</label>
                    <select id="bubbahub_relationship_to_children" name="bubbahub_relationship_to_children">
                        <option value="">Select relationship</option>
                        <?php foreach ( $relationships as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $relationship, $key ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bh-um-field">
                    <label for="bubbahub_phone">Contact number</label>
                    <input type="tel" id="bubbahub_phone" name="bubbahub_phone" value="<?php echo esc_attr( $fields['phone'] ); ?>" autocomplete="tel">
                </div>
            </div>
        </div>

        <div class="bh-um-profile-section">
            <h4>Home address</h4>
            <div class="bh-um-profile-grid">
                <div class="bh-um-field bh-um-field-wide"><label for="bubbahub_address1">Address line 1</label><input type="text" id="bubbahub_address1" name="bubbahub_address1" value="<?php echo esc_attr( $fields['address1'] ); ?>" autocomplete="address-line1"></div>
                <div class="bh-um-field"><label for="bubbahub_address2">Address line 2</label><input type="text" id="bubbahub_address2" name="bubbahub_address2" value="<?php echo esc_attr( $fields['address2'] ); ?>" autocomplete="address-line2"></div>
                <div class="bh-um-field"><label for="bubbahub_town">Town / City</label><input type="text" id="bubbahub_town" name="bubbahub_town" value="<?php echo esc_attr( $fields['town'] ); ?>" autocomplete="address-level2"></div>
                <div class="bh-um-field"><label for="bubbahub_county">County</label><input type="text" id="bubbahub_county" name="bubbahub_county" value="<?php echo esc_attr( $fields['county'] ); ?>" autocomplete="address-level1"></div>
                <div class="bh-um-field"><label for="bubbahub_postcode">Postcode</label><input type="text" id="bubbahub_postcode" name="bubbahub_postcode" value="<?php echo esc_attr( $fields['postcode'] ); ?>" autocomplete="postal-code"></div>
            </div>
        </div>

        <div class="bh-um-profile-section">
            <div class="bh-um-section-heading-row">
                <div><h4>Billing address</h4><p>Use a separate billing address if it is different from your home address.</p></div>
                <label class="bh-um-inline-check"><input type="checkbox" id="bubbahub_billing_same" name="bubbahub_billing_same" value="1"><span>Same as home address</span></label>
            </div>
            <div class="bh-um-profile-grid" id="bubbahub-billing-fields">
                <div class="bh-um-field bh-um-field-wide"><label for="bubbahub_billing_address1">Billing address line 1</label><input type="text" id="bubbahub_billing_address1" name="bubbahub_billing_address1" value="<?php echo esc_attr( $fields['billing_address1'] ); ?>" autocomplete="billing address-line1"></div>
                <div class="bh-um-field"><label for="bubbahub_billing_address2">Billing address line 2</label><input type="text" id="bubbahub_billing_address2" name="bubbahub_billing_address2" value="<?php echo esc_attr( $fields['billing_address2'] ); ?>" autocomplete="billing address-line2"></div>
                <div class="bh-um-field"><label for="bubbahub_billing_town">Town / City</label><input type="text" id="bubbahub_billing_town" name="bubbahub_billing_town" value="<?php echo esc_attr( $fields['billing_town'] ); ?>" autocomplete="billing address-level2"></div>
                <div class="bh-um-field"><label for="bubbahub_billing_county">County</label><input type="text" id="bubbahub_billing_county" name="bubbahub_billing_county" value="<?php echo esc_attr( $fields['billing_county'] ); ?>" autocomplete="billing address-level1"></div>
                <div class="bh-um-field"><label for="bubbahub_billing_postcode">Postcode</label><input type="text" id="bubbahub_billing_postcode" name="bubbahub_billing_postcode" value="<?php echo esc_attr( $fields['billing_postcode'] ); ?>" autocomplete="billing postal-code"></div>
            </div>
        </div>
    </div>
    <style>
      .bh-um-bubba-profile{margin:0}.bh-um-profile-intro{display:flex;gap:16px;align-items:center;margin:0 0 20px;padding:16px;border:1px solid #e1e9e5;border-radius:16px;background:#fbfdfc}.bh-um-profile-avatar{width:72px;height:72px;flex:0 0 72px;border-radius:50%;overflow:hidden;background:#eaf3ef;display:flex;align-items:center;justify-content:center;font-size:28px}.bh-um-profile-avatar img{width:100%;height:100%;object-fit:cover}.bh-um-profile-intro h3{margin:0 0 4px;color:#1e3330}.bh-um-profile-intro p{margin:0 0 7px;color:#718079;font-size:12px;line-height:1.5}.bh-um-profile-photo-link{font-size:12px;font-weight:700;color:#31584b}.bh-um-profile-section{margin-top:14px;padding:16px;border:1px solid #e1e9e5;border-radius:16px;background:#fff}.bh-um-profile-section h4{margin:0 0 12px;color:#31584b;font-size:14px}.bh-um-section-heading-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.bh-um-section-heading-row p{margin:0;color:#718079;font-size:11px}.bh-um-inline-check{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:700;color:#31584b;white-space:nowrap}.bh-um-profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.bh-um-field{min-width:0}.bh-um-field-wide{grid-column:1/-1}.bh-um-field label{display:block;margin:0 0 5px;color:#31584b;font-size:11px;font-weight:700}.bh-um-field input,.bh-um-field select{width:100%;min-height:42px;padding:9px 11px;border:1px solid #dbe7e1;border-radius:10px;background:#fff;box-sizing:border-box}.bh-um-field input:focus,.bh-um-field select:focus{outline:none;border-color:#668785;box-shadow:0 0 0 2px rgba(102,135,133,.12)}@media(max-width:650px){.bh-um-profile-grid{grid-template-columns:1fr}.bh-um-field-wide{grid-column:auto}.bh-um-section-heading-row{display:block}.bh-um-inline-check{margin-top:10px;white-space:normal}}
    </style>
    <script>
    document.addEventListener('DOMContentLoaded',function(){
      var same=document.getElementById('bubbahub_billing_same'), billing=document.getElementById('bubbahub-billing-fields');
      if(!same||!billing)return;
      same.addEventListener('change',function(){
        var pairs=[['bubbahub_address1','bubbahub_billing_address1'],['bubbahub_address2','bubbahub_billing_address2'],['bubbahub_town','bubbahub_billing_town'],['bubbahub_county','bubbahub_billing_county'],['bubbahub_postcode','bubbahub_billing_postcode']];
        pairs.forEach(function(p){var a=document.getElementById(p[0]),b=document.getElementById(p[1]);if(a&&b&&same.checked)b.value=a.value;});
        billing.style.opacity=same.checked?'0.55':'1';
      });
    });
    </script>
    <?php
    $output .= ob_get_clean();
    return $output;
}

function bubbahub_stage2_um_profile_validate( $submitted ) {
    if ( empty( $submitted['_um_account_tab'] ) || 'bubbahub_profile' !== sanitize_key( $submitted['_um_account_tab'] ) ) return;
    if ( ! empty( $submitted['bubbahub_phone'] ) && function_exists( 'UM' ) && isset( UM()->validation ) && method_exists( UM()->validation(), 'is_phone_number' ) && ! UM()->validation()->is_phone_number( sanitize_text_field( $submitted['bubbahub_phone'] ) ) ) {
        UM()->form()->add_error( 'bubbahub_phone', __( 'Please enter a valid contact number.', 'bubbahub' ) );
    }
}

function bubbahub_stage2_um_profile_save( $user_id, $changes = array() ) {
    if ( ! is_user_logged_in() || (int) $user_id !== get_current_user_id() ) return;
    if ( empty( $_POST['_um_account_tab'] ) || 'bubbahub_profile' !== sanitize_key( wp_unslash( $_POST['_um_account_tab'] ) ) ) return;

    $map = array(
        'bubbahub_relationship_to_children' => 'sanitize_key',
        'bubbahub_phone'                    => 'sanitize_text_field',
        'bubbahub_address1'                 => 'sanitize_text_field',
        'bubbahub_address2'                 => 'sanitize_text_field',
        'bubbahub_town'                     => 'sanitize_text_field',
        'bubbahub_county'                   => 'sanitize_text_field',
        'bubbahub_postcode'                 => 'sanitize_text_field',
        'bubbahub_billing_address1'         => 'sanitize_text_field',
        'bubbahub_billing_address2'         => 'sanitize_text_field',
        'bubbahub_billing_town'             => 'sanitize_text_field',
        'bubbahub_billing_county'           => 'sanitize_text_field',
        'bubbahub_billing_postcode'         => 'sanitize_text_field',
    );

    foreach ( $map as $key => $sanitizer ) {
        if ( isset( $_POST[ $key ] ) ) {
            update_user_meta( $user_id, $key, call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ) );
        }
    }

    if ( ! empty( $_POST['bubbahub_billing_same'] ) ) {
        $pairs = array(
            'bubbahub_address1' => 'bubbahub_billing_address1',
            'bubbahub_address2' => 'bubbahub_billing_address2',
            'bubbahub_town'     => 'bubbahub_billing_town',
            'bubbahub_county'   => 'bubbahub_billing_county',
            'bubbahub_postcode' => 'bubbahub_billing_postcode',
        );
        foreach ( $pairs as $source => $target ) {
            update_user_meta( $user_id, $target, get_user_meta( $user_id, $source, true ) );
        }
    }
}

function bubbahub_stage2_payment_methods_url() {
    $url = apply_filters( 'bubbahub_getpaid_payment_methods_url', '' );
    if ( $url ) return esc_url_raw( $url );
    $url = apply_filters( 'bubbahub_stripe_customer_portal_url', '' );
    return $url ? esc_url_raw( $url ) : bubbahub_stage2_payment_url();
}

function bubbahub_stage2_pricing_url() {
    return apply_filters( 'bubbahub_pricing_url', home_url( '/pricing/' ) );
}

function bubbahub_stage2_is_pro() {
    $uid = get_current_user_id();
    $flags = array(
        get_user_meta( $uid, 'bubba_pro_active', true ),
        get_user_meta( $uid, 'bubbahub_pro_active', true ),
        get_user_meta( $uid, 'is_pro', true ),
    );
    foreach ( $flags as $flag ) {
        if ( in_array( strtolower( (string) $flag ), array( '1', 'yes', 'true', 'active', 'pro' ), true ) ) return true;
    }
    $roles = (array) get_userdata( $uid )->roles;
    foreach ( $roles as $role ) {
        if ( false !== stripos( $role, 'pro' ) ) return true;
    }
    return false;
}

function bubbahub_stage2_taxonomy() {
    $preferred = array( 'post_tag', 'group_tag', 'group_tags', 'interest', 'interests', 'at_biz_dir_tags', 'at_biz_dir_tag' );
    foreach ( $preferred as $taxonomy ) {
        if ( taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( 'group', $taxonomy ) ) return $taxonomy;
    }
    $taxonomies = get_object_taxonomies( 'group', 'objects' );
    foreach ( $taxonomies as $taxonomy => $object ) {
        $haystack = strtolower( $taxonomy . ' ' . $object->label . ' ' . $object->name );
        if ( false !== strpos( $haystack, 'tag' ) || false !== strpos( $haystack, 'interest' ) ) return $taxonomy;
    }
    return '';
}

function bubbahub_stage2_taxonomy_terms() {
    $taxonomy = bubbahub_stage2_taxonomy();
    if ( ! $taxonomy ) return array();
    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 200, 'orderby' => 'name', 'order' => 'ASC' ) );
    return is_wp_error( $terms ) ? array() : $terms;
}

function bubbahub_stage2_category_terms() {
    if ( ! taxonomy_exists( 'category' ) || ! is_object_in_taxonomy( 'group', 'category' ) ) return array();
    $terms = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'number' => 200, 'orderby' => 'name', 'order' => 'ASC' ) );
    return is_wp_error( $terms ) ? array() : $terms;
}

function bubbahub_stage2_location_taxonomy() {
    $preferred = array( 'location', 'locations', 'region', 'regions', 'area', 'areas', 'group_location', 'group_locations' );
    foreach ( $preferred as $taxonomy ) {
        if ( taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( 'group', $taxonomy ) ) return $taxonomy;
    }
    $taxonomies = get_object_taxonomies( 'group', 'objects' );
    foreach ( $taxonomies as $taxonomy => $object ) {
        $haystack = strtolower( $taxonomy . ' ' . $object->label . ' ' . $object->name );
        if ( false !== strpos( $haystack, 'location' ) || false !== strpos( $haystack, 'region' ) || false !== strpos( $haystack, 'area' ) ) return $taxonomy;
    }
    return '';
}

function bubbahub_stage2_location_terms() {
    $taxonomy = bubbahub_stage2_location_taxonomy();
    if ( ! $taxonomy ) return array();
    $terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 200, 'orderby' => 'name', 'order' => 'ASC' ) );
    return is_wp_error( $terms ) ? array() : $terms;
}

function bubbahub_stage2_location_term_tree( $terms ) {
    $tree = array();
    foreach ( (array) $terms as $term ) {
        $parent = isset( $term->parent ) ? (int) $term->parent : 0;
        if ( ! isset( $tree[ $parent ] ) ) $tree[ $parent ] = array();
        $tree[ $parent ][] = $term;
    }
    return $tree;
}
function bubbahub_stage2_render_location_options( $tree, $parent = 0, $depth = 0, $selected = array() ) {
    if ( empty( $tree[ $parent ] ) ) return '';
    $html = '';
    foreach ( $tree[ $parent ] as $term ) {
        $name = $term->name;
        $is_selected = in_array( $name, $selected, true );
        $prefix = $depth ? str_repeat('— ', min(3, $depth)) : '';
        $html .= '<label class="bh-location-option bh-location-depth-'.$depth.'">';
        $html .= '<input type="checkbox" name="preferred_locations[]" value="'.esc_attr($name).'" '.checked($is_selected,true,false).'>';
        $html .= '<span>'.esc_html($prefix.$name).'</span></label>';
        $html .= bubbahub_stage2_render_location_options( $tree, (int) $term->term_id, $depth + 1, $selected );
    }
    return $html;
}

/* -------------------------------------------------------------------------
 * Save account profile
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_handle_profile() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_stage2_action'] ) || 'profile' !== $_POST['bh_stage2_action'] ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';

    $uid = get_current_user_id();
    $display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    if ( ! $display_name || ! $email || ! is_email( $email ) ) return 'Please enter a valid name and email address.';

    $existing = email_exists( $email );
    if ( $existing && (int) $existing !== $uid ) return 'That email address is already in use.';

    $result = wp_update_user( array( 'ID' => $uid, 'display_name' => $display_name, 'user_email' => $email ) );
    if ( is_wp_error( $result ) ) return 'Your account could not be updated. ' . $result->get_error_message();

    $fields = array(
        'phone' => 'sanitize_text_field',
        'address1' => 'sanitize_text_field',
        'address2' => 'sanitize_text_field',
        'town' => 'sanitize_text_field',
        'county' => 'sanitize_text_field',
        'postcode' => 'sanitize_text_field',
        'billing_address1' => 'sanitize_text_field',
        'billing_address2' => 'sanitize_text_field',
        'billing_town' => 'sanitize_text_field',
        'billing_county' => 'sanitize_text_field',
        'billing_postcode' => 'sanitize_text_field',
    );
    foreach ( $fields as $field => $sanitizer ) {
        $value = isset( $_POST[ $field ] ) ? call_user_func( $sanitizer, wp_unslash( $_POST[ $field ] ) ) : '';
        bubbahub_stage2_update_meta( 'bubbahub_' . $field, $value );
    }

    $allowed_relationships = array(
        'mum' => 'Mum',
        'dad' => 'Dad',
        'parent' => 'Parent',
        'step-parent' => 'Step-parent',
        'carer' => 'Carer',
        'foster-carer' => 'Foster carer',
        'grandparent' => 'Grandparent',
        'guardian' => 'Guardian',
        'family-member' => 'Family member',
        'other' => 'Other',
    );
    $relationship = isset( $_POST['relationship_to_children'] ) ? sanitize_key( wp_unslash( $_POST['relationship_to_children'] ) ) : '';
    if ( isset( $allowed_relationships[ $relationship ] ) ) {
        bubbahub_stage2_update_meta( 'bubbahub_relationship_to_children', $relationship );
    } else {
        bubbahub_stage2_update_meta( 'bubbahub_relationship_to_children', '' );
    }

    if ( ! empty( $_FILES['profile_image']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $upload = wp_handle_upload(
            $_FILES['profile_image'],
            array(
                'test_form' => false,
                'mimes' => array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                ),
            )
        );

        if ( ! empty( $upload['error'] ) ) {
            return 'Your profile details were saved, but the profile image could not be uploaded: ' . $upload['error'];
        }

        if ( ! empty( $upload['file'] ) && ! empty( $upload['url'] ) && ! empty( $upload['type'] ) ) {
            $attachment_id = wp_insert_attachment(
                array(
                    'post_mime_type' => sanitize_mime_type( $upload['type'] ),
                    'post_title' => sanitize_text_field( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_author' => $uid,
                ),
                $upload['file']
            );

            if ( ! is_wp_error( $attachment_id ) ) {
                $metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
                if ( $metadata ) wp_update_attachment_metadata( $attachment_id, $metadata );

                $old_attachment_id = absint( get_user_meta( $uid, 'bubbahub_profile_image_id', true ) );
                update_user_meta( $uid, 'bubbahub_profile_image_id', $attachment_id );

                if ( $old_attachment_id && $old_attachment_id !== $attachment_id && (int) get_post_field( 'post_author', $old_attachment_id ) === $uid ) {
                    wp_delete_attachment( $old_attachment_id, true );
                }
            } else {
                return 'Your profile details were saved, but the profile image could not be saved.';
            }
        }
    }

    return 'Profile details updated successfully.';
}

/* -------------------------------------------------------------------------
 * Save notification preferences
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_handle_notifications() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_stage2_action'] ) || 'notifications' !== $_POST['bh_stage2_action'] ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';

    $keys = array( 'new_groups','group_updates','new_suggestions','saved_group_updates','new_classes','booking_alerts','booking_reminders','planner_reminders','calendar_reminders','messages','community_alerts','whats_on','email_digest' );
    foreach ( $keys as $key ) bubbahub_stage2_update_meta( 'bubbahub_' . $key, ! empty( $_POST[ $key ] ) ? '1' : '0' );
    bubbahub_stage2_update_meta( 'bubbahub_sms_reminders', '0' );

    if ( function_exists( 'bubbahub_notification_save_preferences' ) ) {
        bubbahub_notification_save_preferences( get_current_user_id(), array(
            'class_booking' => ! empty( $_POST['booking_alerts'] ),
            'booking_reminders' => ! empty( $_POST['booking_reminders'] ),
            'saved_groups' => ! empty( $_POST['saved_group_updates'] ),
            'planner_reminders' => ! empty( $_POST['planner_reminders'] ),
            'calendar_reminders' => ! empty( $_POST['calendar_reminders'] ),
            'messages' => ! empty( $_POST['messages'] ),
            'email_digest' => ! empty( $_POST['email_digest'] ),
            'community' => ! empty( $_POST['community_alerts'] ),
            'new_groups' => ! empty( $_POST['new_groups'] ),
            'group_updates' => ! empty( $_POST['group_updates'] ),
            'new_suggestions' => ! empty( $_POST['new_suggestions'] ),
            'new_classes' => ! empty( $_POST['new_classes'] ),
            'whats_on' => ! empty( $_POST['whats_on'] ),
        ) );
    }
    return 'Notification preferences saved.';
}
function bubbahub_stage2_consent_version() {
    return '1.1';
}

function bubbahub_stage2_handle_consent() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_stage2_action'] ) || 'consent' !== $_POST['bh_stage2_action'] ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';

    /*
     * Sections 1, 4, 5 and 6 are required.
     * Section 1 requires a relationship plus either one saved child profile or
     * a participant name for someone being booked on another person's behalf.
     */
    $required = array(
        'profile_shared_ack',
        'class_leader_contact',
        'payment_agreement',
        'liability_ack',
        'booking_terms_ack',
        'data_processing_ack',
        'accuracy_declaration',
    );
    foreach ( $required as $key ) {
        if ( empty( $_POST[ $key ] ) ) return 'Please complete all required consent sections before saving.';
        bubbahub_stage2_update_meta( 'bubbahub_consent_' . $key, '1' );
    }

    $allowed_relationships = array(
        'mum' => 'Mum',
        'dad' => 'Dad',
        'parent' => 'Parent',
        'step-parent' => 'Step-parent',
        'carer' => 'Carer',
        'foster-carer' => 'Foster carer',
        'grandparent' => 'Grandparent',
        'guardian' => 'Guardian',
        'family-member' => 'Family member',
        'other' => 'Other',
    );

    $relationship_values = isset( $_POST['relationship'] ) ? (array) $_POST['relationship'] : array();
    $relationships = array();
    foreach ( $relationship_values as $relationship ) {
        $relationship = sanitize_key( wp_unslash( $relationship ) );
        if ( isset( $allowed_relationships[ $relationship ] ) ) $relationships[] = $relationship;
    }
    $relationships = array_values( array_unique( $relationships ) );
    if ( ! $relationships ) return 'Please select at least one relationship to the child or participant.';
    bubbahub_stage2_update_meta( 'bubbahub_consent_relationship', $relationships );

    $child_profile_ids = isset( $_POST['child_profile_ids'] ) ? (array) $_POST['child_profile_ids'] : array();
    $child_profile_ids = array_values( array_unique( array_filter( array_map( 'absint', $child_profile_ids ) ) ) );
    foreach ( $child_profile_ids as $child_profile_id ) {
        $child_post = get_post( $child_profile_id );
        if ( ! $child_post || 'bh_child' !== $child_post->post_type || absint( $child_post->post_author ) !== get_current_user_id() ) {
            return 'Please select only child profiles belonging to your account.';
        }
    }

    $participant_name = isset( $_POST['participant_name'] ) ? sanitize_text_field( wp_unslash( $_POST['participant_name'] ) ) : '';
    if ( ! $child_profile_ids && '' === trim( $participant_name ) ) {
        return 'Please select at least one child profile or enter the participant / child name.';
    }

    bubbahub_stage2_update_meta( 'bubbahub_consent_child_profile_ids', $child_profile_ids );
    /* Keep the legacy single ID populated for existing booking integrations. */
    bubbahub_stage2_update_meta( 'bubbahub_consent_child_profile_id', $child_profile_ids ? $child_profile_ids[0] : 0 );

    $fields = array(
        'emergency_name'      => 'sanitize_text_field',
        'emergency_phone'     => 'sanitize_text_field',
        'emergency_relation'  => 'sanitize_text_field',
        'participant_name'    => 'sanitize_text_field',
        'participant_dob'     => 'sanitize_text_field',
        'allergies'           => 'sanitize_text_field',
        'medical_notes'       => 'sanitize_textarea_field',
        'accessibility_notes' => 'sanitize_textarea_field',
        'additional_notes'    => 'sanitize_textarea_field',
    );
    foreach ( $fields as $key => $sanitizer ) {
        $value = isset( $_POST[ $key ] ) ? call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ) : '';
        bubbahub_stage2_update_meta( 'bubbahub_consent_' . $key, $value );
    }

    /*
     * Media permissions are required choices, but consent itself remains opt-in:
     * the user must explicitly choose Yes or No for every media category.
     */
    $media_keys = array( 'media_social', 'media_promotional', 'media_head_office' );
    foreach ( $media_keys as $key ) {
        if ( ! isset( $_POST[ $key ] ) || ! in_array( (string) $_POST[ $key ], array( '0', '1' ), true ) ) {
            return 'Please choose Yes or No for each photo and media permission.';
        }
        bubbahub_stage2_update_meta( 'bubbahub_consent_' . $key, '1' === (string) $_POST[ $key ] ? '1' : '0' );
    }

    bubbahub_stage2_update_meta( 'bubbahub_consent_version', bubbahub_stage2_consent_version() );
    bubbahub_stage2_update_meta( 'bubbahub_consent_saved_at', current_time( 'mysql' ) );
    bubbahub_stage2_update_meta( 'bubbahub_consent_user_id', get_current_user_id() );

    return 'Consent and safety details saved.';
}

function bubbahub_stage2_get_consent_snapshot( $uid = 0 ) {
    $uid = absint( $uid ?: get_current_user_id() );
    if ( ! $uid ) return array();

    $keys = array(
        'relationship','emergency_name','emergency_phone','emergency_relation',
        'participant_name','participant_dob','allergies','medical_notes',
        'accessibility_notes','additional_notes','profile_shared_ack',
        'class_leader_contact','payment_agreement','liability_ack',
        'booking_terms_ack','data_processing_ack','accuracy_declaration',
        'media_social','media_promotional','media_head_office','child_profile_ids',
        'child_profile_id','child_year_of_birth','version','saved_at',
    );
    $snapshot = array();
    foreach ( $keys as $key ) {
        $meta_key = 'bubbahub_consent_' . $key;
        $snapshot[ $key ] = get_user_meta( $uid, $meta_key, true );
    }

    $child_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) get_user_meta( $uid, 'bubbahub_consent_child_profile_ids', true ) ) ) ) );
    if ( ! $child_ids ) {
        $legacy_child_id = absint( get_user_meta( $uid, 'bubbahub_consent_child_profile_id', true ) );
        if ( $legacy_child_id ) $child_ids = array( $legacy_child_id );
    }

    $child_years = array();
    $valid_child_ids = array();
    foreach ( $child_ids as $child_id ) {
        if ( 'bh_child' !== get_post_type( $child_id ) || absint( get_post_field( 'post_author', $child_id ) ) !== $uid ) continue;
        $valid_child_ids[] = $child_id;
        $child_dob = function_exists( 'bubbahub_profile_field' ) ? bubbahub_profile_field( $child_id, 'child_date_of_birth', '' ) : get_post_meta( $child_id, 'child_date_of_birth', true );
        if ( $child_dob ) {
            $year = wp_date( 'Y', strtotime( $child_dob ) );
            if ( $year ) $child_years[] = sanitize_text_field( $year );
        }
    }

    $snapshot['child_profile_ids'] = $valid_child_ids;
    $snapshot['child_profile_id'] = $valid_child_ids ? $valid_child_ids[0] : 0;
    /* Provider-facing child profile data is deliberately limited to year of birth. */
    $share_child_yob = '1' === (string) get_user_meta( $uid, 'bubbahub_privacy_share_child_yob_leaders', true );
    $share_contact = '1' === (string) get_user_meta( $uid, 'bubbahub_privacy_share_contact_leaders', true );
    $leader_messages = '1' === (string) get_user_meta( $uid, 'bubbahub_privacy_leader_messages', true );

    $snapshot['child_profile'] = array(
        'year_of_birth' => $share_child_yob ? array_values( array_unique( $child_years ) ) : array(),
    );
    $snapshot['child_years_of_birth'] = $share_child_yob ? array_values( array_unique( $child_years ) ) : array();
    $snapshot['child_year_of_birth'] = $share_child_yob && ! empty( $child_years ) ? sanitize_text_field( $child_years[0] ) : '';
    $snapshot['provider_privacy'] = array(
        'share_contact' => $share_contact,
        'share_child_year_of_birth' => $share_child_yob,
        'allow_leader_messages' => $leader_messages,
    );
    /* Never retain the full participant DOB in the provider-facing snapshot. */
    unset( $snapshot['participant_dob'] );
    $snapshot['version'] = $snapshot['version'] ?: bubbahub_stage2_consent_version();
    $snapshot['user_id'] = $uid;
    $snapshot['captured_at'] = current_time( 'mysql' );
    return $snapshot;
}

function bubbahub_stage2_consent_is_valid( $uid = 0 ) {
    $uid = absint( $uid ?: get_current_user_id() );
    if ( ! $uid ) return false;
    $snapshot = bubbahub_stage2_get_consent_snapshot( $uid );
    if ( ! $snapshot ) return false;
    $required = array(
        'profile_shared_ack','class_leader_contact','payment_agreement',
        'liability_ack','booking_terms_ack','data_processing_ack','accuracy_declaration',
    );
    foreach ( $required as $key ) {
        if ( empty( $snapshot[ $key ] ) ) return false;
    }

    /* Section 1 must identify the relationship and participant. */
    $relationships = array_filter( (array) $snapshot['relationship'] );
    if ( ! $relationships ) return false;
    $child_ids = array_filter( array_map( 'absint', (array) $snapshot['child_profile_ids'] ) );
    if ( ! $child_ids && '' === trim( (string) $snapshot['participant_name'] ) ) return false;

    /* Section 6 requires an explicit Yes/No choice for every media category. */
    foreach ( array( 'media_social', 'media_promotional', 'media_head_office' ) as $media_key ) {
        if ( ! isset( $snapshot[ $media_key ] ) || ! in_array( (string) $snapshot[ $media_key ], array( '0', '1' ), true ) ) return false;
    }

    return ! empty( $snapshot['version'] ) && ! empty( $snapshot['saved_at'] ) && version_compare( (string) $snapshot['version'], '1.1', '>=' );
}

/* Copy the customer's current consent onto every booking as an immutable booking snapshot. */
add_action( 'save_post_bh_booking', 'bubbahub_stage2_attach_consent_to_booking', 30, 3 );
add_action( 'added_post_meta', 'bubbahub_stage2_attach_consent_after_user_meta', 30, 4 );
add_action( 'updated_post_meta', 'bubbahub_stage2_attach_consent_after_user_meta', 30, 4 );
function bubbahub_stage2_attach_consent_after_user_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
    if ( '_bh_user_id' !== $meta_key || 'bh_booking' !== get_post_type( $post_id ) ) return;
    bubbahub_stage2_attach_consent_to_booking( $post_id, get_post( $post_id ), true );
}
function bubbahub_stage2_attach_consent_to_booking( $post_id, $post, $update ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
    $uid = absint( get_post_meta( $post_id, '_bh_user_id', true ) );
    if ( ! $uid ) return;

    $snapshot = bubbahub_stage2_get_consent_snapshot( $uid );
    if ( ! $snapshot ) return;

    update_post_meta( $post_id, '_bh_consent_snapshot', $snapshot );
    update_post_meta( $post_id, '_bh_child_profile_ids_internal', array_map( 'absint', (array) $snapshot['child_profile_ids'] ) );
    update_post_meta( $post_id, '_bh_child_profile_id_internal', absint( $snapshot['child_profile_id'] ) );
    update_post_meta( $post_id, '_bh_child_years_of_birth', array_map( 'sanitize_text_field', (array) $snapshot['child_years_of_birth'] ) );
    update_post_meta( $post_id, '_bh_child_year_of_birth', sanitize_text_field( $snapshot['child_year_of_birth'] ) );
    /* Provider-facing child profile data is limited to year of birth and respects the user's sharing preference. */
    update_post_meta( $post_id, '_bh_child_profile_for_provider', array(
        'year_of_birth' => array_map( 'sanitize_text_field', (array) $snapshot['child_years_of_birth'] ),
    ) );
    update_post_meta( $post_id, '_bh_provider_privacy', isset( $snapshot['provider_privacy'] ) ? $snapshot['provider_privacy'] : array() );
    update_post_meta( $post_id, '_bh_consent_version', sanitize_text_field( $snapshot['version'] ) );
    update_post_meta( $post_id, '_bh_consent_captured_at', sanitize_text_field( $snapshot['captured_at'] ) );
    update_post_meta( $post_id, '_bh_consent_status', bubbahub_stage2_consent_is_valid( $uid ) ? 'accepted' : 'missing' );
}

/* -------------------------------------------------------------------------
 * Save interests/ and existing website taxonomy term IDs
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_handle_interests() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_stage2_action'] ) || 'interests' !== $_POST['bh_stage2_action'] ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';

    $taxonomy = bubbahub_stage2_taxonomy();

    $user_interests = array();
    if ( isset( $_POST['user_interest'] ) ) {
        $raw_interests = is_array( $_POST['user_interest'] ) ? $_POST['user_interest'] : array( $_POST['user_interest'] );
        foreach ( $raw_interests as $interest ) {
            $interest = sanitize_text_field( wp_unslash( $interest ) );
            if ( '' !== $interest ) $user_interests[] = function_exists( 'mb_substr' ) ? mb_substr( $interest, 0, 100 ) : substr( $interest, 0, 100 );
        }
    }
    $user_interests = array_values( array_unique( array_slice( $user_interests, 0, 30 ) ) );
    bubbahub_stage2_update_meta( 'bubbahub_user_interest', $user_interests );
    update_user_meta( get_current_user_id(), 'user_interest', implode( ', ', $user_interests ) );    update_user_meta( get_current_user_id(), 'User_interest', implode( ', ', $user_interests ) );

    /* Keep existing tag-based matching working by mapping typed interests to exact taxonomy terms. */
    $term_ids = array();
    if ( $taxonomy && $user_interests ) {
        $terms = bubbahub_stage2_taxonomy_terms();
        foreach ( $terms as $term ) {
            foreach ( $user_interests as $interest ) {
                if ( 0 === strcasecmp( trim( $term->name ), trim( $interest ) ) || sanitize_title( $term->name ) === sanitize_title( $interest ) ) {
                    $term_ids[] = (int) $term->term_id;
                    break;
                }
            }
        }
    }
    bubbahub_stage2_update_meta( 'bubbahub_interest_taxonomy', $taxonomy );
    bubbahub_stage2_update_meta( 'bubbahub_interest_term_ids', array_values( array_unique( $term_ids ) ) );

    $preferred_group_types = array();
    if ( isset( $_POST['preferred_group_types'] ) ) {
        $raw_group_types = is_array( $_POST['preferred_group_types'] ) ? $_POST['preferred_group_types'] : array( $_POST['preferred_group_types'] );
        foreach ( $raw_group_types as $group_type ) {
            $group_type = sanitize_text_field( wp_unslash( $group_type ) );
            if ( '' !== $group_type ) $preferred_group_types[] = function_exists( 'mb_substr' ) ? mb_substr( $group_type, 0, 100 ) : substr( $group_type, 0, 100 );
        }
    }
    $preferred_group_types = array_values( array_unique( array_slice( $preferred_group_types, 0, 30 ) ) );
    bubbahub_stage2_update_meta( 'bubbahub_preferred_group_types', $preferred_group_types );
    update_user_meta( get_current_user_id(), 'preferred_group_types', $preferred_group_types );

    $preferred_locations = array();
    if ( isset( $_POST['preferred_locations'] ) ) {
        $raw_locations = is_array( $_POST['preferred_locations'] ) ? $_POST['preferred_locations'] : array( $_POST['preferred_locations'] );
        foreach ( $raw_locations as $location ) {
            $location = sanitize_text_field( wp_unslash( $location ) );
            if ( '' !== $location ) $preferred_locations[] = function_exists( 'mb_substr' ) ? mb_substr( $location, 0, 100 ) : substr( $location, 0, 100 );
        }
    }
    $preferred_locations = array_values( array_unique( array_slice( $preferred_locations, 0, 20 ) ) );
    bubbahub_stage2_update_meta( 'bubbahub_preferred_locations', $preferred_locations );
    update_user_meta( get_current_user_id(), 'preferred_locations', $preferred_locations );

    $age_ranges = array( 'pregnancy','0-6 months','6-12 months','1-2 years','2-3 years','3-5 years','5-8 years','8+ years' );
    $session_lengths = array( 'under-30','30-45','45-60','60-90','90-plus' );
    $price_brackets = array( 'free','under-5','5-10','10-15','15-plus' );

    $age_range = isset( $_POST['preferred_age_range'] ) ? (array) $_POST['preferred_age_range'] : array();
    $session_length = isset( $_POST['preferred_session_length'] ) ? (array) $_POST['preferred_session_length'] : array();
    $price_bracket = isset( $_POST['preferred_price_bracket'] ) ? (array) $_POST['preferred_price_bracket'] : array();

    $age_range = array_values( array_unique( array_intersect( array_map( 'sanitize_text_field', array_map( 'wp_unslash', $age_range ) ), $age_ranges ) ) );
    $session_length = array_values( array_unique( array_intersect( array_map( 'sanitize_key', array_map( 'wp_unslash', $session_length ) ), $session_lengths ) ) );
    $price_bracket = array_values( array_unique( array_intersect( array_map( 'sanitize_key', array_map( 'wp_unslash', $price_bracket ) ), $price_brackets ) ) );

    bubbahub_stage2_update_meta( 'bubbahub_preferred_age_range', $age_range );
    bubbahub_stage2_update_meta( 'bubbahub_preferred_session_length', $session_length );
    bubbahub_stage2_update_meta( 'bubbahub_preferred_price_bracket', $price_bracket );
    update_user_meta( get_current_user_id(), 'preferred_age_range', $age_range );
    update_user_meta( get_current_user_id(), 'preferred_session_length', $session_length );
    update_user_meta( get_current_user_id(), 'preferred_price_bracket', $price_bracket );

    return 'Interests and preferences saved.';
}

/* -------------------------------------------------------------------------
 * Additional account settings
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_handle_family_needs() {
    if ( ! is_user_logged_in() || 'family_needs' !== ( $_POST['bh_stage2_action'] ?? '' ) ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';
    $allowed = array(
        'accessibility_needs' => array( 'step-free','accessible-toilet','quiet-space','hearing-support','visual-support','sensory-friendly','other' ),
        'sen_friendly' => array( 'sen-friendly' ),
        'activity_setting' => array( 'indoor','outdoor' ),
        'term_holiday' => array( 'term-time','school-holidays' ),
        'preferred_days' => array( 'monday','tuesday','wednesday','thursday','friday','saturday','sunday' ),
        'preferred_times' => array( 'morning','afternoon','early-evening' ),
    );
    foreach ( $allowed as $key => $options ) {
        $values = isset( $_POST[ $key ] ) ? (array) $_POST[ $key ] : array();
        $values = array_values( array_unique( array_intersect( array_map( 'sanitize_key', array_map( 'wp_unslash', $values ) ), $options ) ) );
        bubbahub_stage2_update_meta( 'bubbahub_' . $key, $values );
    }
    bubbahub_stage2_update_meta( 'bubbahub_free_activities_only', ! empty( $_POST['free_activities_only'] ) ? '1' : '0' );

    $radius = isset( $_POST['search_radius'] ) ? sanitize_text_field( wp_unslash( $_POST['search_radius'] ) ) : '10 miles';
    if ( ! in_array( $radius, array( '5 miles','10 miles','15 miles','20 miles','25 miles' ), true ) ) $radius = '10 miles';
    bubbahub_stage2_update_meta( 'bubbahub_search_radius', $radius );

    return 'Family needs and discovery preferences saved.';
}
function bubbahub_stage2_handle_preferences() {
    if ( ! is_user_logged_in() || ! in_array( (string) ( $_POST['bh_stage2_action'] ?? '' ), array( 'preferences', 'interests', 'family_needs' ), true ) ) return '';
    $_POST['bh_stage2_action'] = 'interests';
    $interests_result = bubbahub_stage2_handle_interests();
    $_POST['bh_stage2_action'] = 'family_needs';
    $family_result = bubbahub_stage2_handle_family_needs();
    if ( false !== strpos( (string) $interests_result, 'Security check failed' ) || false !== strpos( (string) $family_result, 'Security check failed' ) ) return 'Security check failed. Please try again.';
    return 'My Bubba Hub Preferences saved.';
}

function bubbahub_stage2_handle_calendar_settings() {
    if ( ! is_user_logged_in() || 'calendar' !== ( $_POST['bh_stage2_action'] ?? '' ) ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';

    $reminder = isset( $_POST['calendar_reminder_minutes'] ) ? sanitize_key( wp_unslash( $_POST['calendar_reminder_minutes'] ) ) : '60';
    $calendar = isset( $_POST['default_calendar'] ) ? sanitize_key( wp_unslash( $_POST['default_calendar'] ) ) : 'bubba';
    $view = isset( $_POST['calendar_default_view'] ) ? sanitize_key( wp_unslash( $_POST['calendar_default_view'] ) ) : 'week';
    $price = isset( $_POST['calendar_price_filter'] ) ? sanitize_key( wp_unslash( $_POST['calendar_price_filter'] ) ) : 'all';

    $allowed_days = array( 'monday','tuesday','wednesday','thursday','friday','saturday','sunday' );
    // The settings UI is expressed as "Show days": checked = visible.
    // Store the inverse as hidden days so existing calendar filtering remains stable.
    $selected_visible = isset( $_POST['calendar_visible_days'] ) ? (array) $_POST['calendar_visible_days'] : array();
    $selected_visible = array_values( array_unique( array_filter( array_map(
        function( $day ) { return sanitize_key( wp_unslash( $day ) ); },
        $selected_visible
    ), function( $day ) use ( $allowed_days ) { return in_array( $day, $allowed_days, true ); } ) ) );
    $hidden_days = array_values( array_diff( $allowed_days, $selected_visible ) );
    // Never allow the calendar to end up with no visible day.
    if ( count( $hidden_days ) >= 7 ) array_pop( $hidden_days );

    $time_options = array( 'morning','afternoon','evening' );
    $time_of_day = array();
    $selected_times = isset( $_POST['calendar_time_of_day'] ) ? (array) $_POST['calendar_time_of_day'] : array();
    foreach ( $selected_times as $time ) {
        $time = sanitize_key( wp_unslash( $time ) );
        if ( in_array( $time, $time_options, true ) ) $time_of_day[] = $time;
    }
    $time_of_day = array_values( array_unique( $time_of_day ) );

    if ( ! in_array( $reminder, array( '15','30','60','120','1440' ), true ) ) $reminder = '60';
    if ( ! in_array( $calendar, array( 'bubba','google','apple','ics' ), true ) ) $calendar = 'bubba';
    if ( ! in_array( $view, array( 'list','today','week','month' ), true ) ) $view = 'week';
    if ( ! in_array( $price, array( 'all','free','paid' ), true ) ) $price = 'all';

    bubbahub_stage2_update_meta( 'bubbahub_calendar_reminder_minutes', $reminder );
    bubbahub_stage2_update_meta( 'bubbahub_default_calendar', $calendar );
    bubbahub_stage2_update_meta( 'bubbahub_calendar_default_view', $view );
    bubbahub_stage2_update_meta( 'bubbahub_calendar_hidden_days', $hidden_days );
    bubbahub_stage2_update_meta( 'bubbahub_calendar_time_of_day', $time_of_day );
    bubbahub_stage2_update_meta( 'bubbahub_calendar_price_filter', $price );
    bubbahub_stage2_update_meta( 'bubbahub_calendar_use_nap_schedule', ! empty( $_POST['calendar_use_nap_schedule'] ) ? '1' : '0' );

    return 'Calendar settings saved.';
}
function bubbahub_stage2_handle_notification_test() {
    if ( ! is_user_logged_in() || 'notification_test' !== ( $_POST['bh_stage2_action'] ?? '' ) ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';
    $user = wp_get_current_user();
    if ( empty( $user->user_email ) || ! is_email( $user->user_email ) ) return 'We could not send a test notification to your account email address.';
    return wp_mail( $user->user_email, 'Bubba Hub notification test', 'This is a test notification from Bubba Hub. Your notification email address is working.' ) ? 'Test notification sent.' : 'The test notification could not be sent. Please check your email settings.';
}
function bubbahub_stage2_handle_privacy() {
    if ( ! is_user_logged_in() || 'privacy' !== ( $_POST['bh_stage2_action'] ?? '' ) ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';

    $uid = get_current_user_id();
    $restrict_leader_sharing = ! empty( $_POST['privacy_restrict_leader_sharing'] ) ? '1' : '0';
    $share_contact = ! empty( $_POST['privacy_share_contact_leaders'] ) ? '1' : '0';
    $share_child_yob = ! empty( $_POST['privacy_share_child_yob_leaders'] ) ? '1' : '0';
    $leader_messages = ! empty( $_POST['privacy_leader_messages'] ) ? '1' : '0';
    if ( '1' === $restrict_leader_sharing ) {
        $share_contact = '0';
        $share_child_yob = '0';
        $leader_messages = '0';
    }
    update_user_meta( $uid, 'bubbahub_privacy_restrict_leader_sharing', $restrict_leader_sharing );
    update_user_meta( $uid, 'bubbahub_privacy_share_contact_leaders', $share_contact );
    update_user_meta( $uid, 'bubbahub_privacy_share_child_yob_leaders', $share_child_yob );
    update_user_meta( $uid, 'bubbahub_privacy_personalised_recommendations', ! empty( $_POST['privacy_personalised_recommendations'] ) ? '1' : '0' );
    update_user_meta( $uid, 'bubbahub_privacy_anonymous_analytics', ! empty( $_POST['privacy_anonymous_analytics'] ) ? '1' : '0' );
    update_user_meta( $uid, 'bubbahub_privacy_leader_messages', $leader_messages );

    $request = isset( $_POST['privacy_request'] ) ? sanitize_key( wp_unslash( $_POST['privacy_request'] ) ) : '';
    if ( $request && in_array( $request, array( 'export','deletion' ), true ) ) {
        update_user_meta( $uid, 'bubbahub_privacy_request', $request );
        update_user_meta( $uid, 'bubbahub_privacy_request_at', current_time( 'mysql' ) );
        $user = wp_get_current_user();
        wp_mail( get_option( 'admin_email' ), 'Bubba Hub privacy request', sprintf( "A privacy request has been submitted.\n\nUser ID: %d\nName: %s\nEmail: %s\nRequest: %s\nTime: %s", $uid, $user->display_name, $user->user_email, $request, current_time( 'mysql' ) ) );
        return 'Privacy and data-sharing settings saved. Your privacy request has also been submitted.';
    }
    return 'Privacy and data-sharing settings saved.';
}

/* -------------------------------------------------------------------------
 * Shared child list from Stage 1
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_children() {
    return get_posts( array(
        'post_type' => 'bh_child', 'post_status' => 'publish', 'author' => get_current_user_id(),
        'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'ASC', 'no_found_rows' => true,
    ) );
}

function bubbahub_stage2_render_profile( $user ) {
    $get = function( $key, $default = '' ) { return bubbahub_stage2_user_meta( 'bubbahub_' . $key, $default ); };
    ob_start(); ?>
    <div class="bh-profile-card">
        <div class="bh-profile-card-heading"><h3>Edit my profile</h3><span>Saved to your WordPress account</span></div>
        <form method="post" class="bh-stage2-form" enctype="multipart/form-data">
            <?php
            wp_nonce_field( 'bh_stage2_settings', 'bh_stage2_nonce' );
            $profile_image_id = absint( get_user_meta( get_current_user_id(), 'bubbahub_profile_image_id', true ) );
            $relationship = $get('relationship_to_children');
            $relationship_options = array(
                'mum' => 'Mum',
                'dad' => 'Dad',
                'parent' => 'Parent',
                'step-parent' => 'Step-parent',
                'carer' => 'Carer',
                'foster-carer' => 'Foster carer',
                'grandparent' => 'Grandparent',
                'guardian' => 'Guardian',
                'family-member' => 'Family member',
                'other' => 'Other',
            );
            ?>
            <input type="hidden" name="bh_stage2_action" value="profile">
            <div class="bh-profile-grid two">
                <label><span>Full name / display name</span><input name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" required></label>
                <label><span>Email address</span><input type="email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" required></label>
                <label><span>Contact number</span><input name="phone" value="<?php echo esc_attr( $get('phone') ); ?>"></label>
                <label><span>Relationship to child(ren)</span><select name="relationship_to_children"><option value="">Select relationship</option><?php foreach ( $relationship_options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $relationship, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
                <label class="bh-profile-image-field">
                    <span>Profile image</span>
                    <?php if ( $profile_image_id && wp_attachment_is_image( $profile_image_id ) ) : ?>
                        <span class="bh-profile-image-preview"><?php echo wp_get_attachment_image( $profile_image_id, 'thumbnail', false, array( 'alt' => 'Current profile image' ) ); ?></span>
                        <small>Choose a new image to replace your current one.</small>
                    <?php else : ?>
                        <small>Upload a JPG, PNG or WebP image.</small>
                    <?php endif; ?>
                    <input name="profile_image" type="file" accept="image/jpeg,image/png,image/webp">
                </label>
                <label><span>Search radius</span><select name="search_radius"><?php foreach(array('5 miles','10 miles','15 miles','20 miles','25 miles') as $r): ?><option value="<?php echo esc_attr($r); ?>" <?php selected($get('search_radius','10 miles'),$r); ?>><?php echo esc_html($r); ?></option><?php endforeach; ?></select></label>
                <label><span>Address line 1</span><input name="address1" value="<?php echo esc_attr($get('address1')); ?>"></label>
                <label><span>Address line 2</span><input name="address2" value="<?php echo esc_attr($get('address2')); ?>"></label>
                <label><span>Town / city</span><input name="town" value="<?php echo esc_attr($get('town')); ?>"></label>
                <label><span>County</span><input name="county" value="<?php echo esc_attr($get('county','Devon')); ?>"></label>
                <label><span>Postcode</span><input name="postcode" value="<?php echo esc_attr($get('postcode')); ?>"></label>
            </div>
            <div class="bh-profile-card-heading" style="margin-top:20px"><h3>Billing address</h3><span>Used for payments where required</span></div>
            <div class="bh-profile-grid two">
                <label><span>Billing address line 1</span><input name="billing_address1" value="<?php echo esc_attr($get('billing_address1')); ?>"></label>
                <label><span>Billing address line 2</span><input name="billing_address2" value="<?php echo esc_attr($get('billing_address2')); ?>"></label>
                <label><span>Billing town / city</span><input name="billing_town" value="<?php echo esc_attr($get('billing_town')); ?>"></label>
                <label><span>Billing county</span><input name="billing_county" value="<?php echo esc_attr($get('billing_county')); ?>"></label>
                <label><span>Billing postcode</span><input name="billing_postcode" value="<?php echo esc_attr($get('billing_postcode')); ?>"></label>
            </div>
            <div class="bh-profile-actions"><button type="submit">Save profile</button></div>
        </form>
    </div>    <?php return ob_get_clean();
}

function bubbahub_stage2_render_notifications() {
    $groups = array(
        'Discovery & suggestions' => array(
            'new_groups' => array('🆕','New groups','Get an alert when new groups are added that may be relevant to your family.'),
            'group_updates' => array('✏️','Group updates','Get an alert when relevant groups are edited or updated.'),
            'new_suggestions' => array('✨','New suggestions','Get personalised suggestions based on your interests, children, locations and preferences.'),
            'saved_group_updates' => array('❤️','Saved group updates','Get updates when a group you have saved or followed changes.'),
            'new_classes' => array('🎟️','New classes & sessions','Get alerts when new classes or sessions become available.'),
        ),
        'Bookings & planning' => array(
            'booking_alerts' => array('📅','Booking alerts','Booking confirmations, status changes, cancellations and important booking updates.'),
            'booking_reminders' => array('⏰','Booking reminders','Reminders before your upcoming booked classes and activities.'),
            'planner_reminders' => array('🗓️','Planner reminders','Reminders for activities and events in your family planner.'),
            'calendar_reminders' => array('🔔','Calendar reminders','Reminders for events and activities saved to your Bubba Hub calendars.'),
        ),
        'Messages & community' => array(
            'messages' => array('💬','Messages & support','Alerts when a specialist, group leader or support contact replies to you.'),
            'community_alerts' => array('🏡','Community updates','Useful local family updates and community news.'),
            'whats_on' => array('📍','What’s On alerts','New and updated family events and activities in your preferred areas.'),
        ),
        'Email' => array(
            'email_digest' => array('✉️','Email digest','Receive a regular round-up of relevant Bubba Hub updates by email.'),
        ),
    );
    ob_start(); ?>
    <div class="bh-profile-card bh-notification-settings">
        <div class="bh-profile-card-heading"><div><h3>Notification preferences</h3><span>Choose exactly which Bubba Hub updates you receive</span></div></div>
        <p class="bh-notification-intro">Turn individual notification types on or off. These choices control optional alerts; essential account, payment and transaction messages may still be sent when required.</p>
        <form method="post" class="bh-stage2-form">
            <?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?><input type="hidden" name="bh_stage2_action" value="notifications">
            <?php foreach ( $groups as $heading => $items ) : ?>
                <div class="bh-notification-group"><h4><?php echo esc_html($heading); ?></h4>
                <?php foreach ( $items as $key => $item ) : ?>
                    <label class="bh-notification-preference"><span class="bh-notification-preference-icon" aria-hidden="true"><?php echo esc_html($item[0]); ?></span><span class="bh-notification-preference-copy"><strong><?php echo esc_html($item[1]); ?></strong><small><?php echo esc_html($item[2]); ?></small></span><input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_'.$key,'1'),'1'); ?>><span class="bh-notification-preference-toggle" aria-hidden="true"></span></label>
                <?php endforeach; ?></div>
            <?php endforeach; ?>
            <div class="bh-notification-sms-disabled"><span aria-hidden="true">📱</span><div><strong>SMS notifications</strong><small>Currently unavailable. SMS is disabled and no SMS notifications will be sent.</small></div><span class="bh-notification-disabled-pill">Disabled</span></div>
            <div class="bh-profile-actions"><button type="submit">Save notification preferences</button></div>
        </form>
    </div>
    <style>
    .bh-notification-intro{margin:-4px 0 20px;color:#687a73;font-size:13px;line-height:1.55}.bh-notification-group{margin:0 0 24px;border:1px solid #e4ece8;border-radius:16px;overflow:hidden;background:#fff}.bh-notification-group h4{margin:0;padding:14px 16px;background:#f5f9f6;color:#31584b;font-size:14px}.bh-notification-preference{display:grid;grid-template-columns:42px minmax(0,1fr) 0 42px;gap:12px;align-items:center;padding:14px 16px;border-top:1px solid #edf1ef;cursor:pointer;position:relative}.bh-notification-preference-icon{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:#f3f7f4;font-size:18px}.bh-notification-preference-copy{display:flex;flex-direction:column;gap:3px;min-width:0}.bh-notification-preference-copy strong{color:#24483d;font-size:14px}.bh-notification-preference-copy small{color:#718079;font-size:11px;line-height:1.45}.bh-notification-preference input{position:absolute;opacity:0;pointer-events:none}.bh-notification-preference-toggle{width:42px;height:24px;border-radius:999px;background:#cbd7d1;position:relative;transition:.2s}.bh-notification-preference-toggle:after{content:"";position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.14);transition:.2s}.bh-notification-preference input:checked + .bh-notification-preference-toggle{background:#2f6c52}.bh-notification-preference input:checked + .bh-notification-preference-toggle:after{transform:translateX(18px)}.bh-notification-sms-disabled{display:flex;align-items:center;gap:12px;padding:14px 16px;margin:0 0 18px;border:1px dashed #d6dfda;border-radius:14px;background:#fafcfb;color:#738079}.bh-notification-sms-disabled>span:first-child{font-size:20px}.bh-notification-sms-disabled div{flex:1}.bh-notification-sms-disabled strong{display:block;color:#53665f;font-size:13px}.bh-notification-sms-disabled small{display:block;margin-top:3px;font-size:11px;line-height:1.4}.bh-notification-disabled-pill{font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;padding:5px 8px;border-radius:999px;background:#edf0ee;color:#718079}@media(max-width:600px){.bh-notification-preference{grid-template-columns:36px minmax(0,1fr) 42px;padding:13px 12px;gap:9px}.bh-notification-preference-icon{width:34px;height:34px}.bh-notification-preference-copy strong{font-size:13px}.bh-notification-preference-copy small{font-size:10.5px}.bh-notification-group h4{padding:12px}.bh-notification-sms-disabled{align-items:flex-start}.bh-notification-disabled-pill{margin-left:auto}}
    
    .bh-consent-card{overflow:hidden}.bh-consent-help{margin:-3px 0 14px;color:#718079;font-size:12px;line-height:1.5}.bh-consent-help strong{color:#31584b}.bh-consent-required{border-color:#d5e6de}.bh-consent-required .bh-profile-card-heading>span{color:#31584b;font-weight:800}.bh-consent-multi-options{display:flex;flex-wrap:wrap;gap:8px;padding:10px 0 2px}.bh-consent-multi-options label{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border:1px solid #dbe7e1;border-radius:999px;background:#fff;cursor:pointer;color:#31584b;font-size:12px}.bh-consent-multi-options input{margin:0}.bh-consent-field-wide{grid-column:1/-1}.bh-media-choice{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 0;border-top:1px solid #e7efeb}.bh-media-choice:first-of-type{border-top:0}.bh-media-choice>div:first-child{display:flex;flex-direction:column;gap:3px}.bh-media-choice small{color:#718079;font-size:11px}.bh-media-options{display:flex;gap:8px;flex:0 0 auto}.bh-media-options label{display:inline-flex;align-items:center;gap:5px;padding:7px 10px;border:1px solid #dbe7e1;border-radius:999px;background:#fff;cursor:pointer;color:#31584b;font-size:11px}.bh-media-options input{margin:0}@media(max-width:600px){.bh-consent-field-wide{grid-column:auto}.bh-media-choice{align-items:flex-start;flex-direction:column;gap:9px}.bh-media-options{width:100%}.bh-media-options label{flex:1;justify-content:center}.bh-consent-multi-options{gap:6px}.bh-consent-multi-options label{font-size:11px;padding:7px 9px}.bh-consent-section{padding:14px;border-radius:15px}.bh-consent-section .bh-profile-grid.two{grid-template-columns:1fr}.bh-consent-section .bh-stage2-check{align-items:flex-start}.bh-consent-section .bh-stage2-check span{line-height:1.45}}.bh-consent-section{margin-top:18px;padding:18px;border:1px solid #e2ebe7;border-radius:18px;background:#fbfdfc}.bh-consent-section .bh-profile-card-heading{margin-bottom:12px}.bh-consent-section h4{margin:0;color:#28483f;font-size:15px}.bh-consent-section .bh-profile-grid{margin-top:0}.bh-consent-notice{margin-top:18px;padding:14px 16px;border-radius:14px;background:#eef7f2;border:1px solid #d5e9df;color:#48665d;font-size:12px;line-height:1.55}.bh-consent-notice strong{color:#294a40}@media(max-width:600px){.bh-consent-section{padding:14px;border-radius:15px}.bh-consent-section .bh-profile-grid.two{grid-template-columns:1fr}.bh-consent-section .bh-stage2-check{align-items:flex-start}.bh-consent-section .bh-stage2-check span{line-height:1.45}}
</style>
    <?php return ob_get_clean();
}
function bubbahub_stage2_render_consent() {
    $saved = bubbahub_stage2_user_meta('bubbahub_consent_saved_at');
    ob_start(); ?>
    <div class="bh-profile-card bh-consent-card">
      <div class="bh-profile-card-heading"><h3>Booking Consent & Safety</h3><span><?php echo $saved ? 'Version '.esc_html(bubbahub_stage2_user_meta('bubbahub_consent_version','1.0')).' · Last saved '.esc_html(wp_date('j M Y',strtotime($saved))) : 'Required before booking'; ?></span></div>
      <p class="bh-muted">This consent form is your standing booking consent. When you make a booking, Bubba Hub saves a copy of the consent that applied at the time of booking with the booking record. You can update your standing consent here for future bookings.</p>
      <form method="post" class="bh-stage2-form">
        <?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?><input type="hidden" name="bh_stage2_action" value="consent">
        <div class="bh-consent-section bh-consent-required"><div class="bh-profile-card-heading"><h4>1. Parent / guardian details</h4><span>Required</span></div>
          <p class="bh-consent-help">Choose your relationship to the participant. This is pre-filled from your profile, but you can change it when booking on someone else's behalf. You can select more than one relationship.</p>
          <div class="bh-profile-grid two">
            <label class="bh-consent-field-wide"><span>Relationship to child / participant <em>Required</em></span>
              <?php
              $relationship_options = array(
                  'mum' => 'Mum', 'dad' => 'Dad', 'parent' => 'Parent', 'step-parent' => 'Step-parent',
                  'carer' => 'Carer', 'foster-carer' => 'Foster carer', 'grandparent' => 'Grandparent',
                  'guardian' => 'Guardian', 'family-member' => 'Family member', 'other' => 'Other',
              );
              $saved_relationships = (array) bubbahub_stage2_user_meta('bubbahub_consent_relationship', array());
              if ( ! $saved_relationships ) {
                  $profile_relationship = sanitize_key( bubbahub_stage2_user_meta('bubbahub_relationship_to_children') );
                  if ( $profile_relationship && isset( $relationship_options[ $profile_relationship ] ) ) $saved_relationships = array( $profile_relationship );
              }
              ?>
              <select class="bh-consent-multi-select" name="relationship[]" multiple size="5" aria-label="Relationship to child or participant">
                <?php foreach ( $relationship_options as $value => $label ) : ?>
                  <option value="<?php echo esc_attr($value); ?>" <?php selected(in_array($value, $saved_relationships, true)); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
              </select>
              <small class="bh-muted">Select one or more options. Hold Ctrl/Cmd to select multiple options.</small>
            </label>
            <label class="bh-consent-field-wide"><span>Child profile(s) for this consent <em>Required*</em></span>
              <?php
              $saved_child_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) bubbahub_stage2_user_meta('bubbahub_consent_child_profile_ids', array()) ) ) ) );
              if ( ! $saved_child_ids ) {
                  $legacy_child_id = absint( bubbahub_stage2_user_meta('bubbahub_consent_child_profile_id', 0) );
                  if ( $legacy_child_id ) $saved_child_ids = array( $legacy_child_id );
              }
              $children_for_consent = bubbahub_stage2_children();
              ?>
              <?php if ( $children_for_consent ) : ?>
                <select class="bh-consent-multi-select" name="child_profile_ids[]" multiple size="5" aria-label="Child profiles for this consent">
                  <?php foreach ( $children_for_consent as $child ) :
                      $child_name = function_exists('bubbahub_profile_field') ? bubbahub_profile_field($child->ID, 'child_name', $child->post_title) : $child->post_title;
                  ?>
                    <option value="<?php echo absint($child->ID); ?>" <?php selected(in_array((int)$child->ID, $saved_child_ids, true)); ?>><?php echo esc_html($child_name); ?></option>
                  <?php endforeach; ?>
                </select>
                <small class="bh-muted">Select one or more saved children. Only each child's year of birth can be shared with a class leader, subject to your Privacy &amp; Security settings.</small>
              <?php else : ?>
                <p class="bh-muted">No saved child profiles yet. Use the participant name below.</p>
              <?php endif; ?>
              <small class="bh-muted">Select one or more saved children. Only each child's year of birth can be shared with a class leader, subject to your Privacy &amp; Security settings.</small>
            </label>
            <label class="bh-consent-field-wide"><span>Participant / child name <em>Required if not using a saved child profile</em></span><input name="participant_name" value="<?php echo esc_attr(bubbahub_stage2_user_meta('bubbahub_consent_participant_name')); ?>" placeholder="Complete this if different from the children in your profiles"></label>
          </div>
        </div>
        <div class="bh-consent-section"><div class="bh-profile-card-heading"><h4>2. Emergency contact</h4><span>Safety</span></div>
          <div class="bh-profile-grid two">
            <label><span>Name</span><input name="emergency_name" value="<?php echo esc_attr(bubbahub_stage2_user_meta('bubbahub_consent_emergency_name')); ?>"></label>
            <label><span>Relationship</span><input name="emergency_relation" value="<?php echo esc_attr(bubbahub_stage2_user_meta('bubbahub_consent_emergency_relation')); ?>"></label>
            <label><span>Phone number</span><input type="tel" name="emergency_phone" value="<?php echo esc_attr(bubbahub_stage2_user_meta('bubbahub_consent_emergency_phone')); ?>"></label>
          </div>
        </div>
        <div class="bh-consent-section"><div class="bh-profile-card-heading"><h4>3. Health, medical & accessibility information</h4><span>Safety information</span></div>
          <div class="bh-profile-grid two">
            <label><span>Allergies / dietary information</span><input name="allergies" value="<?php echo esc_attr(bubbahub_stage2_user_meta('bubbahub_consent_allergies')); ?>"></label>
            <label><span>Medical / safety notes</span><textarea name="medical_notes" rows="4"><?php echo esc_textarea(bubbahub_stage2_user_meta('bubbahub_consent_medical_notes')); ?></textarea></label>
            <label><span>Accessibility / additional support</span><textarea name="accessibility_notes" rows="4"><?php echo esc_textarea(bubbahub_stage2_user_meta('bubbahub_consent_accessibility_notes')); ?></textarea></label>
            <label><span>Other information the class provider should know</span><textarea name="additional_notes" rows="4"><?php echo esc_textarea(bubbahub_stage2_user_meta('bubbahub_consent_additional_notes')); ?></textarea></label>
          </div>
        </div>
        <div class="bh-consent-section bh-consent-required"><div class="bh-profile-card-heading"><h4>4. Booking, contact & information sharing</h4><span>Required</span></div>
          <label class="bh-stage2-check"><input type="checkbox" name="profile_shared_ack" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_profile_shared_ack'),'1'); ?> required><span><strong>I confirm the information on my account is accurate and may be shared with the relevant class provider where needed for attendance, administration and safety.</strong></span></label>
          <label class="bh-stage2-check"><input type="checkbox" name="class_leader_contact" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_class_leader_contact'),'1'); ?> required><span><strong>I consent to the class leader / provider contacting me about my booking, changes, attendance and urgent matters.</strong></span></label>
          <label class="bh-stage2-check"><input type="checkbox" name="payment_agreement" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_payment_agreement'),'1'); ?> required><span><strong>I agree to the booking price, payment, refund and cancellation terms shown at the time of booking.</strong></span></label>
          <label class="bh-stage2-check"><input type="checkbox" name="booking_terms_ack" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_booking_terms_ack'),'1'); ?> required><span><strong>I understand that a booking is subject to the class provider's published rules, capacity, timetable and any session-specific requirements.</strong></span></label>
          <label class="bh-stage2-check"><input type="checkbox" name="data_processing_ack" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_data_processing_ack'),'1'); ?> required><span><strong>I understand that booking information will be processed and retained as needed to administer the booking, payment, safety and related communications.</strong></span></label>
        </div>
        <div class="bh-consent-section bh-consent-required"><div class="bh-profile-card-heading"><h4>5. Safety & responsibility</h4><span>Required</span></div>
          <label class="bh-stage2-check"><input type="checkbox" name="liability_ack" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_liability_ack'),'1'); ?> required><span><strong>I acknowledge that I am responsible for providing accurate safety information and following the class provider's instructions and safety requirements.</strong></span></label>
          <label class="bh-stage2-check"><input type="checkbox" name="accuracy_declaration" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_accuracy_declaration'),'1'); ?> required><span><strong>I confirm that I am authorised to give this consent for the participant named above and that the information supplied is accurate to the best of my knowledge.</strong></span></label>
        </div>
        <div class="bh-consent-section bh-consent-required"><div class="bh-profile-card-heading"><h4>6. Photo & media permissions</h4><span>Required</span></div>
          <p class="bh-consent-help">Please make a choice for each permission. Choosing <strong>No</strong> is a valid choice and does not affect your ability to book.</p>
          <?php foreach(array(
              'media_social'=>'Social media',
              'media_promotional'=>'Promotional materials',
              'media_head_office'=>'Bubba Hub / head office use'
          ) as $key=>$label):
              $saved_media = bubbahub_stage2_user_meta('bubbahub_consent_'.$key, '');
          ?>
            <div class="bh-media-choice">
              <div><strong><?php echo esc_html($label); ?></strong><small>Would you like the participant to be included where applicable?</small></div>
              <div class="bh-media-options" role="radiogroup" aria-label="<?php echo esc_attr($label); ?>">
                <label><input type="radio" name="<?php echo esc_attr($key); ?>" value="1" <?php checked($saved_media,'1'); ?> required><span>Yes</span></label>
                <label><input type="radio" name="<?php echo esc_attr($key); ?>" value="0" <?php checked($saved_media,'0'); ?> required><span>No</span></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="bh-consent-notice"><strong>Important:</strong> This form is a standing consent for Bubba Hub bookings. A snapshot is attached to each booking so the consent in force at the time of booking can be retained for the booking record.</div>
        <div class="bh-profile-actions"><button type="submit">Save my booking consent</button></div>
      </form>
    </div>
    <?php return ob_get_clean();
}

function bubbahub_stage2_pref_array( $key, $fallback = array() ) {
    $value = bubbahub_stage2_user_meta( $key, $fallback );
    if ( is_array( $value ) ) return array_values( array_filter( array_map( 'sanitize_key', $value ) ) );
    if ( is_string( $value ) && '' !== trim( $value ) ) return array_values( array_filter( array_map( 'sanitize_key', preg_split( '/[,|]+/', $value ) ) ) );
    return $fallback;
}

function bubbahub_stage2_render_preferences() {
    $taxonomy = bubbahub_stage2_taxonomy();
    $terms = bubbahub_stage2_taxonomy_terms();

    $user_interests = (array) bubbahub_stage2_user_meta('bubbahub_user_interest', array());
    if ( ! $user_interests ) {
        $legacy_interest_text = get_user_meta( get_current_user_id(), 'User_interest', true );
        if ( '' === $legacy_interest_text ) $legacy_interest_text = get_user_meta( get_current_user_id(), 'user_interest', true );
        if ( is_string( $legacy_interest_text ) && '' !== trim( $legacy_interest_text ) ) {
            $user_interests = array_values( array_filter( array_map( 'trim', preg_split( '/[,\n]+/', $legacy_interest_text ) ) ) );
        }
    }
    if ( ! $user_interests ) {
        $legacy_ids = array_map( 'absint', (array) bubbahub_stage2_user_meta('bubbahub_interest_term_ids', array()) );
        if ( $legacy_ids && $terms ) {
            foreach ( $terms as $term ) {
                if ( in_array( (int) $term->term_id, $legacy_ids, true ) ) $user_interests[] = $term->name;
            }
        }
    }

    $preferred_locations = (array) bubbahub_stage2_user_meta('bubbahub_preferred_locations', array());
    if ( ! $preferred_locations ) {
        $legacy_location_id = absint( bubbahub_stage2_user_meta('bubbahub_planner_location_term_id', 0) );
        $legacy_location_taxonomy = bubbahub_stage2_location_taxonomy();
        if ( $legacy_location_id && $legacy_location_taxonomy ) {
            $legacy_location_name = get_term_field( 'name', $legacy_location_id, $legacy_location_taxonomy );
            if ( ! is_wp_error( $legacy_location_name ) && $legacy_location_name ) $preferred_locations[] = $legacy_location_name;
        }
    }

    $group_type_terms = bubbahub_stage2_category_terms();
    $preferred_group_types = (array) bubbahub_stage2_user_meta('bubbahub_preferred_group_types', array());
    $location_terms = bubbahub_stage2_location_terms();
    $age_range = bubbahub_stage2_pref_array('bubbahub_preferred_age_range');
    $session_length = bubbahub_stage2_pref_array('bubbahub_preferred_session_length');
    $price_bracket = bubbahub_stage2_pref_array('bubbahub_preferred_price_bracket');

    $age_options = array(
        '' => 'Any age',
        'pregnancy' => 'Pregnancy',
        '0-6 months' => '0–6 months',
        '6-12 months' => '6–12 months',
        '1-2 years' => '1–2 years',
        '2-3 years' => '2–3 years',
        '3-5 years' => '3–5 years',
        '5-8 years' => '5–8 years',
        '8+ years' => '8+ years',
    );
    $session_options = array(
        '' => 'Any session length',
        'under-30' => 'Under 30 minutes',
        '30-45' => '30–45 minutes',
        '45-60' => '45–60 minutes',
        '60-90' => '60–90 minutes',
        '90-plus' => '90+ minutes',
    );
    $price_options = array(
        '' => 'Any price',
        'free' => 'Free',
        'under-5' => 'Under £5',
        '5-10' => '£5–£10',
        '10-15' => '£10–£15',
        '15-plus' => '£15+',
    );

    ob_start(); ?>
    <div class="bh-preference-card">
        <div class="bh-profile-card-heading">
            <div><h3>My Bubba Hub Preferences</h3><span>Personalise what Bubba Hub shows your family, where you would like to go and when.</span></div>
        </div>
        <form method="post" class="bh-stage2-form bh-preferences-form" data-bh-preferences-form>
            <?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?>
            <input type="hidden" name="bh_stage2_action" value="preferences">

            <nav class="bh-preferences-jump-menu" aria-label="Jump to preference section">
                <div class="bh-preferences-jump-head"><strong>Jump to</strong><span>Go straight to the settings you want to update.</span></div>
                <div class="bh-preferences-jump-links">
                    <a href="#bh-pref-interests">✨ Interests</a><a href="#bh-pref-group-types">👨‍👩‍👧 Group types</a><a href="#bh-pref-locations">📍 Locations</a><a href="#bh-pref-filters">🎯 Age, length &amp; price</a><a href="#bh-pref-family-needs">💚 Family needs</a>
                </div>
            </nav>
            <div id="bh-pref-interests" class="bh-preference-section bh-preference-anchor">
                <div class="bh-preference-heading"><h4>✨ My Interests</h4><p>Start typing to get suggestions from tags already used by Bubba Hub groups, or enter your own interest.</p></div>
                    <div class="bh-chip-editor" data-bh-chip-editor data-field="user_interest">
                        <div class="bh-chip-list" data-bh-chip-list>
                            <?php foreach ( $user_interests as $interest ) : ?>
                                <span class="bh-preference-chip"><span><?php echo esc_html($interest); ?></span><button type="button" data-bh-remove aria-label="Remove <?php echo esc_attr($interest); ?>">×</button><input type="hidden" name="user_interest[]" value="<?php echo esc_attr($interest); ?>"></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="bh-chip-input-row">
                            <input type="text" data-bh-chip-input list="bh-interest-suggestions" placeholder="e.g. messy play, baby music, swimming" autocomplete="off">
                            <button type="button" class="bh-chip-add" data-bh-add>Add</button>
                        </div>
                        <datalist id="bh-interest-suggestions"><?php foreach ( $terms as $term ) : ?><option value="<?php echo esc_attr($term->name); ?>"></option><?php endforeach; ?></datalist>
                    </div>
                </div>
                <div id="bh-pref-group-types" class="bh-preference-section bh-preference-anchor">
                    <div class="bh-preference-heading"><h4>👨‍👩‍👧 Preferred Group Types</h4><p>Choose the types of groups and activities you would like to discover.</p></div>
                    <div class="bh-group-type-options">
                        <?php foreach ( $group_type_terms as $term ) : $name = $term->name; $checked = in_array( $name, $preferred_group_types, true ); ?>
                            <label class="bh-group-type-option"><input type="checkbox" name="preferred_group_types[]" value="<?php echo esc_attr($name); ?>" <?php checked($checked); ?>><span><?php echo esc_html($name); ?></span></label>
                        <?php endforeach; ?>
                        <?php if ( ! $group_type_terms ) : ?><span class="bh-location-empty">No Group category options are currently available.</span><?php endif; ?>
                    </div>
                </div>

            <div id="bh-pref-locations" class="bh-preference-section bh-location-preference-section bh-preference-anchor">
                <div class="bh-preference-heading"><h4>📍 Preferred Locations</h4><p>Select locations from the <strong>Location</strong> hierarchy used by Bubba Hub groups. You can choose a region, town or area at any level.</p></div>
                <div class="bh-location-columns">
                    <div class="bh-location-column">
                        <div class="bh-location-column-title">Select locations</div>
                        <div class="bh-location-multiselect" data-bh-location-multiselect>
                            <button type="button" class="bh-location-select" aria-expanded="false" aria-haspopup="listbox">
                                <span data-bh-location-label>Select locations</span>
                                <span aria-hidden="true">▾</span>
                            </button>
                            <div class="bh-location-options" role="listbox" aria-label="Preferred locations" aria-multiselectable="true" hidden>
                                <?php $location_tree = bubbahub_stage2_location_term_tree( $location_terms ); echo bubbahub_stage2_render_location_options( $location_tree, 0, 0, $preferred_locations ); ?>
                                <?php if ( ! $location_terms ) : ?>
                                    <span class="bh-location-empty">No group Location taxonomy options are currently available.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="bh-location-column bh-selected-locations-column">
                        <div class="bh-location-column-title">Selected locations <span data-bh-selected-location-count>0</span></div>
                        <div class="bh-selected-locations" data-bh-selected-locations>
                            <span class="bh-location-none" data-bh-no-selected>None selected</span>
                        </div>
                    </div>
                </div>
            </div>
            <div id="bh-pref-filters" class="bh-preference-grid bh-preference-anchor">
                <div class="bh-preference-section">
                    <div class="bh-preference-heading"><h4>Preferred Age Range</h4><p>Choose the age range you want to see in your group suggestions.</p></div>
                    <label class="bh-preference-field"><span>Age range</span><select name="preferred_age_range[]" multiple size="5" aria-label="Preferred age ranges"><?php foreach($age_options as $value=>$label): if($value==='') continue; ?><option value="<?php echo esc_attr($value); ?>" <?php selected(in_array($value,$age_range,true)); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="bh-preference-section">
                    <div class="bh-preference-heading"><h4>Preferred Session Length</h4><p>Choose the session length that suits your family.</p></div>
                    <label class="bh-preference-field"><span>Session length</span><select name="preferred_session_length[]" multiple size="5" aria-label="Preferred session lengths"><?php foreach($session_options as $value=>$label): if($value==='') continue; ?><option value="<?php echo esc_attr($value); ?>" <?php selected(in_array($value,$session_length,true)); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="bh-preference-section">
                    <div class="bh-preference-heading"><h4>Preferred Price Bracket</h4><p>Choose your preferred price range per session.</p></div>
                    <label class="bh-preference-field"><span>Price bracket</span><select name="preferred_price_bracket[]" multiple size="5" aria-label="Preferred price brackets"><?php foreach($price_options as $value=>$label): if($value==='') continue; ?><option value="<?php echo esc_attr($value); ?>" <?php selected(in_array($value,$price_bracket,true)); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                </div>
            </div>

            <div id="bh-pref-family-needs" class="bh-preference-section bh-family-preferences-section bh-preference-anchor">
                <div class="bh-preference-heading"><h4>💚 Family Needs & Discovery</h4><p>Choose the accessibility, activity, timing and search preferences that help Bubba Hub find suitable groups for your family.</p></div>
                <div class="bh-settings-option-grid">
                    <?php
                    $family_data = array(
                        'accessibility_needs' => array('step-free'=>'Step-free access','accessible-toilet'=>'Accessible toilet','quiet-space'=>'Quiet / low-stimulation space','hearing-support'=>'Hearing support','visual-support'=>'Visual support','sensory-friendly'=>'Sensory-friendly','other'=>'Other support'),
                        'sen_friendly' => array('sen-friendly'=>'SEN friendly'),
                        'activity_setting' => array('indoor'=>'Indoor','outdoor'=>'Outdoor'),
                        'term_holiday' => array('term-time'=>'Term-time','school-holidays'=>'School holidays'),
                        'preferred_days' => array('monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday'),
                        'preferred_times' => array('morning'=>'Morning','afternoon'=>'Afternoon','early-evening'=>'Early evening'),
                    );
                    foreach($family_data as $name=>$opts):
                        $selected=bubbahub_stage2_pref_array('bubbahub_'.$name);
                    ?>
                        <fieldset class="bh-settings-fieldset"><legend><?php echo esc_html(ucwords(str_replace('_',' ',$name))); ?></legend><div class="bh-settings-check-grid">
                        <?php foreach($opts as $v=>$label): ?><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($v); ?>" <?php checked(in_array($v,$selected,true)); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?>
                        </div></fieldset>
                    <?php endforeach; ?>
                </div>
                <div class="bh-family-search-controls">
                    <label class="bh-inline-setting"><input type="checkbox" name="free_activities_only" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_free_activities_only'),'1'); ?>><span><strong>Free activities only</strong><small>Limit discovery to activities marked as free.</small></span></label>
                    <?php $radius=bubbahub_stage2_user_meta('bubbahub_search_radius','10 miles'); ?>
                    <label class="bh-preference-field bh-search-radius-field"><span>Default search radius</span><select name="search_radius"><?php foreach(array('5 miles','10 miles','15 miles','20 miles','25 miles') as $r): ?><option value="<?php echo esc_attr($r); ?>" <?php selected($radius,$r); ?>><?php echo esc_html($r); ?></option><?php endforeach; ?></select></label>
                </div>
            </div>
            <div class="bh-profile-actions"><button type="submit">Save My Bubba Hub Preferences</button></div>
        </form>
    </div>

    <script>
    (function(){
        function initChipEditor(editor){
            if(editor.dataset.ready) return;
            editor.dataset.ready='1';
            var input=editor.querySelector('[data-bh-chip-input]');
            var add=editor.querySelector('[data-bh-add]');
            var list=editor.querySelector('[data-bh-chip-list]');
            if(!input||!add||!list) return;

            function normalise(value){ return value.trim().replace(/\\s+/g,' '); }
            function values(){
                return Array.prototype.map.call(list.querySelectorAll('input[type="hidden"]'),function(el){ return el.value.trim().toLowerCase(); });
            }
            function addValue(){
                var value=normalise(input.value);
                if(!value||values().indexOf(value.toLowerCase())!==-1) return;
                var chip=document.createElement('span');
                chip.className='bh-preference-chip';
                var text=document.createElement('span');
                text.textContent=value;
                var remove=document.createElement('button');
                remove.type='button';
                remove.setAttribute('data-bh-remove','');
                remove.setAttribute('aria-label','Remove '+value);
                remove.textContent='×';
                remove.addEventListener('click',function(){ chip.remove(); });
                var hidden=document.createElement('input');
                hidden.type='hidden';
                hidden.name=editor.dataset.field+'[]';
                hidden.value=value;
                chip.appendChild(text);
                chip.appendChild(remove);
                chip.appendChild(hidden);
                list.appendChild(chip);
                input.value='';
                input.focus();
            }
            add.addEventListener('click',addValue);
            input.addEventListener('keydown',function(event){
                if(event.key==='Enter'){
                    event.preventDefault();
                    addValue();
                }
            });
            list.querySelectorAll('[data-bh-remove]').forEach(function(button){
                button.addEventListener('click',function(){ button.closest('.bh-preference-chip').remove(); });
            });
        }
        document.querySelectorAll('[data-bh-chip-editor]').forEach(initChipEditor);
    }());
    </script>
    <style>
        .bh-interest-group-grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:16px!important;margin-bottom:18px!important}
        .bh-interest-group-grid>.bh-preference-section{min-width:0!important;padding:16px!important;border:1px solid #e4ece8!important;border-radius:16px!important;background:#fff!important;box-sizing:border-box!important}
        .bh-group-type-options{display:flex!important;flex-wrap:wrap!important;gap:8px!important;max-height:190px!important;overflow:auto!important;padding:2px!important}
        .bh-group-type-option{display:inline-flex!important;align-items:center!important;gap:7px!important;padding:8px 10px!important;border:1px solid #dbe7e1!important;border-radius:999px!important;background:#fbfdfc!important;color:#31584b!important;font-size:12px!important;font-weight:700!important;cursor:pointer!important}
        .bh-group-type-option input{width:16px!important;height:16px!important;margin:0!important;accent-color:#5f9183!important}
        @media(max-width:620px){.bh-interest-group-grid{grid-template-columns:1fr!important;gap:12px!important}.bh-group-type-options{max-height:none!important}}
        /* Keep the three discovery preferences visually prominent below Preferred Locations. */
        .bh-preference-grid{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr))!important;gap:16px!important;margin-top:18px!important;width:100%!important}
        .bh-preference-grid .bh-preference-section{display:block!important;visibility:visible!important;min-width:0!important;padding:16px!important;border:1px solid #e4ece8!important;border-radius:16px!important;background:#fff!important;box-sizing:border-box!important}
        .bh-preference-grid .bh-preference-heading{display:block!important;margin:0 0 12px!important}
        .bh-preference-grid .bh-preference-heading h4{display:block!important;margin:0 0 5px!important;color:#31584b!important;font-size:15px!important;line-height:1.25!important;font-weight:800!important}
        .bh-preference-grid .bh-preference-heading p{display:block!important;margin:0!important;color:#718079!important;font-size:12px!important;line-height:1.45!important}
        .bh-preference-grid .bh-preference-field{display:block!important;margin:0!important}
        .bh-preference-grid .bh-preference-field>span{display:block!important;margin:0 0 7px!important;color:#40574f!important;font-size:12px!important;font-weight:800!important}
        .bh-preference-grid .bh-preference-field select{display:block!important;visibility:visible!important;width:100%!important;min-height:174px!important;height:auto!important;padding:6px!important;border:1px solid #dbe7e1!important;border-radius:12px!important;background:#fbfdfc!important;color:#1e3330!important;font:inherit!important;font-size:12px!important;line-height:1.5!important;box-sizing:border-box!important}
        .bh-preference-grid .bh-preference-field select option{display:block!important;padding:8px 9px!important;border-radius:7px!important}
        .bh-preference-grid .bh-preference-field select option:checked{background:#eaf3ef!important;color:#31584b!important;font-weight:800!important}
        @media(max-width:900px){.bh-preference-grid{grid-template-columns:1fr 1fr!important}}
        @media(max-width:620px){.bh-preference-grid{grid-template-columns:1fr!important;gap:12px!important}.bh-preference-grid .bh-preference-section{padding:14px!important}.bh-preference-grid .bh-preference-field select{min-height:150px!important}}\n        .bh-location-columns{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;align-items:start}
        .bh-location-column{min-width:0;padding:14px;border:1px solid #e4ece8;border-radius:14px;background:#fbfdfc}
        .bh-location-column-title{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:9px;color:#31584b;font-size:12px;font-weight:800}
        .bh-location-column-title span{min-width:22px;padding:2px 7px;border-radius:999px;background:#eaf3ef;text-align:center}
        .bh-location-multiselect{position:relative;width:100%;max-width:100%}
        .bh-location-select{width:100%;display:flex;align-items:center;justify-content:space-between;gap:12px;min-height:44px;padding:11px 13px;border:1px solid #e6ebe9;border-radius:13px;background:#fff;color:#1e3330;font:inherit;font-size:13px;font-weight:600;text-align:left;cursor:pointer}
        .bh-location-select:focus{outline:2px solid rgba(95,145,131,.25);outline-offset:2px}
        .bh-location-options{position:absolute;z-index:30;top:calc(100% + 6px);left:0;width:100%;max-height:280px;overflow:auto;padding:7px;background:#fff;border:1px solid #dbe7e1;border-radius:14px;box-shadow:0 10px 30px rgba(30,51,48,.12)}
        .bh-location-option{display:flex!important;flex-direction:row!important;align-items:center;gap:9px;padding:9px 10px;border-radius:9px;font-size:12px!important;font-weight:600!important;cursor:pointer}
        .bh-location-option:hover{background:#f4f8f6}
        .bh-location-option input{width:17px!important;height:17px!important;margin:0;flex:0 0 17px;accent-color:#5f9183}
        .bh-location-empty{display:block;padding:10px;color:#718079;font-size:12px}
        .bh-selected-locations{display:flex;flex-wrap:wrap;gap:7px;min-height:44px;align-items:flex-start}
        .bh-selected-location-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 9px;border-radius:999px;background:#eaf3ef;color:#31584b;font-size:11px;font-weight:700}
        .bh-selected-location-chip button{border:0;background:transparent;color:#31584b;font-size:15px;line-height:1;padding:0;cursor:pointer}
        .bh-location-none{color:#718079;font-size:12px;padding:8px 0}
        @media(max-width:620px){.bh-location-columns{grid-template-columns:1fr}.bh-location-options{max-height:240px}.bh-location-option{padding:10px}}
    </style>
    <script>
    (function(){
        document.querySelectorAll('[data-bh-location-multiselect]').forEach(function(wrapper){
            if(wrapper.dataset.ready) return;
            wrapper.dataset.ready='1';
            var trigger=wrapper.querySelector('.bh-location-select');
            var menu=wrapper.querySelector('.bh-location-options');
            var label=wrapper.querySelector('[data-bh-location-label]');
            var boxes=wrapper.querySelectorAll('input[name="preferred_locations[]"]');
            if(!trigger||!menu) return;
            function sync(){
                var selected=[];
                boxes.forEach(function(box){if(box.checked) selected.push(box.value);});
                label.textContent=selected.length ? selected.length+' location'+(selected.length===1?'':'s')+' selected' : 'Select locations';
                var selectedWrap=wrapper.closest('.bh-location-columns').querySelector('[data-bh-selected-locations]');
                var countEl=wrapper.closest('.bh-location-columns').querySelector('[data-bh-selected-location-count]');
                var empty=wrapper.closest('.bh-location-columns').querySelector('[data-bh-no-selected]');
                if(countEl) countEl.textContent=selected.length;
                if(selectedWrap){
                    selectedWrap.innerHTML='';
                    if(!selected.length){
                        var none=document.createElement('span'); none.className='bh-location-none'; none.textContent='None selected'; selectedWrap.appendChild(none);
                    } else {
                        selected.forEach(function(value){
                            var chip=document.createElement('span'); chip.className='bh-selected-location-chip';
                            var text=document.createElement('span'); text.textContent=value;
                            var remove=document.createElement('button'); remove.type='button'; remove.textContent='×'; remove.setAttribute('aria-label','Remove '+value);
                            remove.addEventListener('click',function(){
                                boxes.forEach(function(box){if(box.value===value) box.checked=false;});
                                sync();
                            });
                            chip.appendChild(text); chip.appendChild(remove); selectedWrap.appendChild(chip);
                        });
                    }
                }
            }
            function close(){menu.hidden=true;trigger.setAttribute('aria-expanded','false');}
            trigger.addEventListener('click',function(){
                var open=menu.hidden;
                menu.hidden=!open;
                trigger.setAttribute('aria-expanded',open?'true':'false');
            });
            boxes.forEach(function(box){box.addEventListener('change',sync);});
            document.addEventListener('click',function(event){if(!wrapper.contains(event.target)) close();});
            document.addEventListener('keydown',function(event){if(event.key==='Escape') close();});
            sync();
        });
    }());
    </script>
    <style>
      .bh-preferences-jump-menu{position:sticky;top:12px;z-index:10;margin:0 0 18px;padding:12px 14px;border:1px solid #dfe9e4;border-radius:16px;background:rgba(255,255,255,.96);box-shadow:0 6px 18px rgba(50,72,64,.07);backdrop-filter:blur(8px)}
      .bh-preferences-jump-head{display:flex;align-items:baseline;gap:8px;margin-bottom:9px}
      .bh-preferences-jump-head strong{font-size:13px;color:#31584b}.bh-preferences-jump-head span{font-size:11px;color:#718079}
      .bh-preferences-jump-links{display:flex;gap:7px;overflow-x:auto;padding:2px 2px 3px;scrollbar-width:thin;-webkit-overflow-scrolling:touch}
      .bh-preferences-jump-links a{display:inline-flex;align-items:center;justify-content:center;min-height:34px;padding:7px 11px;border:1px solid #dfe8e4;border-radius:999px;background:#f8faf8;color:#40574f!important;text-decoration:none!important;font-size:11px;font-weight:800;white-space:nowrap}
      .bh-preferences-jump-links a:hover,.bh-preferences-jump-links a:focus-visible{background:#eef5f0;border-color:#a9c7bb;outline:none}
      .bh-preference-anchor{scroll-margin-top:105px}.bh-preferences-form .bh-preference-section{margin-bottom:18px}.bh-preferences-form .bh-preference-heading{margin-bottom:13px}.bh-preferences-form .bh-preference-heading h4{font-size:16px}
      @media(max-width:650px){.bh-preferences-jump-menu{top:8px;margin-bottom:15px;padding:11px 12px;border-radius:14px}.bh-preferences-jump-head{display:block;margin-bottom:7px}.bh-preferences-jump-head strong{display:block;margin-bottom:2px}.bh-preferences-jump-head span{font-size:10px}.bh-preferences-jump-links{margin-right:-2px;padding-right:2px}.bh-preferences-jump-links a{min-height:32px;padding:6px 10px;font-size:10px}.bh-preference-anchor{scroll-margin-top:92px}}
      .bh-family-preferences-section{margin-top:18px}
      .bh-family-search-controls{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:14px;margin-top:14px;align-items:start}
      .bh-location-depth-1{padding-left:18px!important}.bh-location-depth-2{padding-left:36px!important}.bh-location-depth-3{padding-left:54px!important}
      @media(max-width:650px){.bh-family-search-controls{grid-template-columns:1fr}.bh-location-depth-1{padding-left:14px!important}.bh-location-depth-2{padding-left:26px!important}.bh-location-depth-3{padding-left:38px!important}}
    </style>
    <?php return ob_get_clean();
}

function bubbahub_stage2_render_family_needs() {
    $data = array(
        'accessibility_needs' => array('step-free'=>'Step-free access','accessible-toilet'=>'Accessible toilet','quiet-space'=>'Quiet / low-stimulation space','hearing-support'=>'Hearing support','visual-support'=>'Visual support','sensory-friendly'=>'Sensory-friendly','other'=>'Other support'),
        'sen_friendly' => array('sen-friendly'=>'SEN friendly'),
        'activity_setting' => array('indoor'=>'Indoor','outdoor'=>'Outdoor'),
        'term_holiday' => array('term-time'=>'Term-time','school-holidays'=>'School holidays'),
        'preferred_days' => array('monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday'),
        'preferred_times' => array('morning'=>'Morning','afternoon'=>'Afternoon','early-evening'=>'Early evening'),
    );
    $radius = bubbahub_stage2_user_meta('bubbahub_search_radius','10 miles');
    ob_start(); ?>
    <div class="bh-profile-card">
      <div class="bh-profile-card-heading"><div><h3>My Family & Search Preferences</h3><span>Tell Bubba Hub what your family needs and what you'd like to discover</span></div></div>
      <form method="post" class="bh-stage2-form"><?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?><input type="hidden" name="bh_stage2_action" value="family_needs">
      <div class="bh-family-search-section"><div class="bh-family-search-heading"><h4>Family needs</h4><p>Choose the facilities, settings and times that work best for your family.</p></div>
      <div class="bh-settings-option-grid">
      <?php foreach($data as $name=>$opts): $selected=bubbahub_stage2_pref_array('bubbahub_'.$name); ?>
        <fieldset class="bh-settings-fieldset"><legend><?php echo esc_html(ucwords(str_replace('_',' ',$name))); ?></legend><div class="bh-settings-check-grid">
        <?php foreach($opts as $v=>$label): ?><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[]" value="<?php echo esc_attr($v); ?>" <?php checked(in_array($v,$selected,true)); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?>
        </div></fieldset>
      <?php endforeach; ?>
      </div>
      <label class="bh-inline-setting"><input type="checkbox" name="free_activities_only" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_free_activities_only'),'1'); ?>><span><strong>Free activities only</strong><small>Limit discovery to free activities when this is enabled.</small></span></label>
      </div>
      <div class="bh-family-search-section"><div class="bh-family-search-heading"><h4>Search area</h4><p>Choose how far Bubba Hub should look when discovering local groups and activities.</p></div>
      <label class="bh-preference-field bh-search-radius-field"><span>Default search radius</span><select name="search_radius"><?php foreach(array('5 miles','10 miles','15 miles','20 miles','25 miles') as $r): ?><option value="<?php echo esc_attr($r); ?>" <?php selected($radius,$r); ?>><?php echo esc_html($r); ?></option><?php endforeach; ?></select></label>
      </div>
      <div class="bh-profile-actions"><button type="submit">Save family & search preferences</button></div>
      </form>
    </div>
    <style>
      .bh-family-search-section{margin-top:18px;padding-top:18px;border-top:1px solid #e4ece8}
      .bh-family-search-heading{margin-bottom:12px}
      .bh-family-search-heading h4{margin:0 0 4px;color:#31584b;font-size:15px;font-weight:800}
      .bh-family-search-heading p{margin:0;color:#718079;font-size:12px;line-height:1.45}
      .bh-settings-option-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
      .bh-settings-fieldset{border:1px solid #e4ece8;border-radius:14px;padding:14px;margin:0;min-width:0}
      .bh-settings-fieldset legend{padding:0 6px;color:#31584b;font-weight:800;font-size:13px}
      .bh-settings-check-grid{display:flex;flex-wrap:wrap;gap:8px}
      .bh-settings-check-grid label{display:flex;align-items:center;gap:7px;padding:8px 10px;border-radius:10px;background:#f5f9f6;font-size:12px;color:#40574f}
      .bh-settings-check-grid input{accent-color:#2f6c52}
      .bh-inline-setting{display:flex;align-items:center;gap:10px;margin-top:14px;padding:13px;border:1px solid #e4ece8;border-radius:14px}
      .bh-inline-setting small{display:block;color:#718079;margin-top:2px}
      .bh-search-radius-field{display:block;max-width:360px}
      .bh-search-radius-field span{display:block;margin-bottom:7px;font-weight:800;color:#40574f;font-size:12px}
      .bh-search-radius-field select{width:100%;min-height:42px;padding:8px 10px;border:1px solid #dbe7e1;border-radius:10px;background:#fbfdfc;color:#1e3330}
      @media(max-width:650px){.bh-settings-option-grid{grid-template-columns:1fr}}
    </style>
    <?php return ob_get_clean();}
function bubbahub_stage2_render_calendar_settings() {
    $reminder = bubbahub_stage2_user_meta('bubbahub_calendar_reminder_minutes','60');
    $calendar = bubbahub_stage2_user_meta('bubbahub_default_calendar','bubba');
    $view = bubbahub_stage2_user_meta('bubbahub_calendar_default_view','week');
    $hidden_days = bubbahub_stage2_pref_array('bubbahub_calendar_hidden_days');
    $time_of_day = bubbahub_stage2_pref_array('bubbahub_calendar_time_of_day');
    $price = bubbahub_stage2_user_meta('bubbahub_calendar_price_filter','all');
    $use_naps = bubbahub_stage2_user_meta('bubbahub_calendar_use_nap_schedule','1');
    $options = array('bubba'=>'Bubba Hub planner','google'=>'Google Calendar (ICS import)','apple'=>'Apple Calendar (ICS import)','ics'=>'Downloadable ICS');
    $days = array('monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday');
    $times = array('morning'=>'Morning','afternoon'=>'Afternoon','evening'=>'Evening');
    ob_start(); ?>
    <div class="bh-profile-card bh-calendar-settings-card">
      <div class="bh-profile-card-heading"><div><h3>Calendar settings</h3><span>Make your Bubba Hub calendar fit your family's routine</span></div></div>
      <p class="bh-muted bh-calendar-settings-intro">Choose how the calendar opens, which days and times you normally want to see, and whether free or paid activities should be included by default.</p>
      <form method="post" class="bh-stage2-form">
        <?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?>
        <input type="hidden" name="bh_stage2_action" value="calendar">

        <div class="bh-calendar-settings-grid">
          <div class="bh-calendar-setting-card">
            <div class="bh-calendar-setting-heading"><span class="bh-calendar-setting-icon">🗓️</span><div><h4>Default view</h4><p>Choose the view shown when you open Calendar.</p></div></div>
            <select name="calendar_default_view" aria-label="Default calendar view">
              <?php foreach(array('week'=>'Week','today'=>'Today','month'=>'Monthly','list'=>'List') as $v=>$label): ?><option value="<?php echo esc_attr($v); ?>" <?php selected($view,$v); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="bh-calendar-setting-card">
            <div class="bh-calendar-setting-heading"><span class="bh-calendar-setting-icon">💷</span><div><h4>Activities to show</h4><p>Set the default price filter for your calendar.</p></div></div>
            <select name="calendar_price_filter" aria-label="Default activity price filter">
              <?php foreach(array('all'=>'Free & paid','free'=>'Free only','paid'=>'Paid only') as $v=>$label): ?><option value="<?php echo esc_attr($v); ?>" <?php selected($price,$v); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="bh-calendar-setting-card bh-calendar-setting-wide">
            <div class="bh-calendar-setting-heading"><span class="bh-calendar-setting-icon">☀️</span><div><h4>Time of day</h4><p>Show only the parts of the day that suit your family. Leave all unchecked to show every time.</p></div></div>
            <div class="bh-calendar-check-pills">
              <?php foreach($times as $v=>$label): ?><label><input type="checkbox" name="calendar_time_of_day[]" value="<?php echo esc_attr($v); ?>" <?php checked(in_array($v,$time_of_day,true)); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?>
            </div>
          </div>
          <div class="bh-calendar-setting-card bh-calendar-setting-wide">
            <div class="bh-calendar-setting-heading"><span class="bh-calendar-setting-icon">📅</span><div><h4>Show days</h4><p>Choose which days are normally visible. Untick a day to hide it from your calendar.</p></div></div>
            <div class="bh-calendar-check-pills">
              <?php foreach($days as $v=>$label): ?><label><input type="checkbox" name="calendar_visible_days[]" value="<?php echo esc_attr($v); ?>" <?php checked(!in_array($v,$hidden_days,true)); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?>
            </div>
          </div>
          <div class="bh-calendar-setting-card bh-calendar-setting-wide">
            <div class="bh-calendar-setting-heading"><span class="bh-calendar-setting-icon">😴</span><div><h4>Nap schedule</h4><p>Use your children's saved nap times when showing calendar activities.</p></div></div>
            <label class="bh-calendar-toggle"><input type="checkbox" name="calendar_use_nap_schedule" value="1" <?php checked($use_naps,'1'); ?>><span class="bh-calendar-toggle-track" aria-hidden="true"></span><span class="bh-calendar-toggle-copy"><strong>Use nap schedule</strong><small>Highlight activities that overlap a saved child nap time.</small></span></label>
          </div>
          <div class="bh-calendar-setting-card">
            <div class="bh-calendar-setting-heading"><span class="bh-calendar-setting-icon">🔔</span><div><h4>Reminder timing</h4><p>Default reminder timing for calendar reminders.</p></div></div>
            <select name="calendar_reminder_minutes"><?php foreach(array('15'=>'15 minutes','30'=>'30 minutes','60'=>'1 hour','120'=>'2 hours','1440'=>'1 day') as $v=>$l): ?><option value="<?php echo esc_attr($v); ?>" <?php selected($reminder,$v); ?>><?php echo esc_html($l); ?></option><?php endforeach; ?></select>
          </div>
          <div class="bh-calendar-setting-card">
            <div class="bh-calendar-setting-heading"><span class="bh-calendar-setting-icon">🔗</span><div><h4>Calendar workflow</h4><p>Choose the calendar format you normally use.</p></div></div>
            <select name="default_calendar"><?php foreach($options as $v=>$l): ?><option value="<?php echo esc_attr($v); ?>" <?php selected($calendar,$v); ?>><?php echo esc_html($l); ?></option><?php endforeach; ?></select>
          </div>
        </div>

        <p class="bh-muted bh-calendar-settings-note">Your saved preferences are defaults for Calendar. You can still use the calendar's search and advanced filters to change what you are viewing. ICS is supported for compatible calendar apps; a live Google/Apple sync still requires the relevant provider permissions.</p>
        <div class="bh-profile-actions"><button type="submit">Save calendar settings</button></div>
      </form>
    </div>
    <style>
      .bh-calendar-settings-intro{margin:0 0 18px;line-height:1.55}
      .bh-calendar-settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
      .bh-calendar-setting-card{min-width:0;padding:16px;border:1px solid #e4ece8;border-radius:16px;background:#fbfdfc;box-sizing:border-box}
      .bh-calendar-setting-wide{grid-column:1/-1}
      .bh-calendar-setting-heading{display:flex;align-items:flex-start;gap:10px;margin-bottom:12px}
      .bh-calendar-setting-icon{display:flex;align-items:center;justify-content:center;flex:0 0 38px;width:38px;height:38px;border-radius:11px;background:#eaf3ef;font-size:18px}
      .bh-calendar-setting-heading h4{margin:0 0 3px;color:#31584b;font-size:14px;font-weight:800}
      .bh-calendar-setting-heading p{margin:0;color:#718079;font-size:11px;line-height:1.45}
      .bh-calendar-setting-card select{width:100%;min-height:44px;padding:9px 11px;border:1px solid #dbe7e1;border-radius:11px;background:#fff;color:#1e3330;font:inherit;font-size:13px}
      .bh-calendar-check-pills{display:flex;flex-wrap:wrap;gap:8px}
      .bh-calendar-check-pills label{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border:1px solid #dbe7e1;border-radius:999px;background:#fff;color:#40574f;font-size:12px;font-weight:700;cursor:pointer}
      .bh-calendar-check-pills input{width:16px;height:16px;margin:0;accent-color:#5f9183}
      .bh-calendar-toggle{display:flex;align-items:center;gap:10px;cursor:pointer}
      .bh-calendar-toggle input{position:absolute;opacity:0;pointer-events:none}
      .bh-calendar-toggle-track{position:relative;flex:0 0 44px;width:44px;height:25px;border-radius:999px;background:#cbd7d1;transition:.2s}
      .bh-calendar-toggle-track:after{content:"";position:absolute;top:3px;left:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.14);transition:.2s}
      .bh-calendar-toggle input:checked + .bh-calendar-toggle-track{background:#5f9183}
      .bh-calendar-toggle input:checked + .bh-calendar-toggle-track:after{transform:translateX(19px)}
      .bh-calendar-toggle-copy{display:flex;flex-direction:column;gap:2px}.bh-calendar-toggle-copy strong{color:#31584b;font-size:13px}.bh-calendar-toggle-copy small{color:#718079;font-size:11px;line-height:1.4}
      .bh-calendar-settings-note{margin:16px 0 0;line-height:1.5}
      @media(max-width:650px){.bh-calendar-settings-grid{grid-template-columns:1fr}.bh-calendar-setting-wide{grid-column:auto}.bh-calendar-setting-card{padding:14px}.bh-calendar-check-pills label{padding:8px 10px}}
    </style>
    <?php return ob_get_clean();
}
function bubbahub_stage2_render_privacy() {
    $existing = bubbahub_stage2_user_meta('bubbahub_privacy_request','');
    $restrict_leader_sharing = bubbahub_stage2_user_meta('bubbahub_privacy_restrict_leader_sharing','0');
    $share_contact = bubbahub_stage2_user_meta('bubbahub_privacy_share_contact_leaders','1');
    $share_child_yob = bubbahub_stage2_user_meta('bubbahub_privacy_share_child_yob_leaders','1');
    $personalised = bubbahub_stage2_user_meta('bubbahub_privacy_personalised_recommendations','1');
    $analytics = bubbahub_stage2_user_meta('bubbahub_privacy_anonymous_analytics','1');
    $leader_messages = bubbahub_stage2_user_meta('bubbahub_privacy_leader_messages','1');
    $toggle = function($name,$value,$label,$description){
        ob_start(); ?>
        <label class="bh-privacy-toggle"><input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($value,'1'); ?>><span class="bh-privacy-toggle-track" aria-hidden="true"></span><span class="bh-privacy-toggle-copy"><strong><?php echo esc_html($label); ?></strong><small><?php echo esc_html($description); ?></small></span></label>
        <?php return ob_get_clean();
    };
    ob_start(); ?>
    <div class="bh-profile-card bh-privacy-settings">
      <div class="bh-profile-card-heading"><div><h3>Privacy & security</h3><span>Choose how your information is used and shared</span></div></div>
      <div class="bh-privacy-notice"><strong>🔒 Your choices</strong><p>These settings control optional Bubba Hub data sharing. Information that is legally required, needed to process a booking, or required for payment or account security may still need to be processed.</p></div>
      <div class="bh-privacy-purpose"><strong>💚 How Bubba Hub uses your data</strong><p>In a nutshell, Bubba Hub uses your information to make the directory more useful and personalised for your family, and to help connect you with relevant class leaders, groups and services efficiently. This can help speed up bookings, enquiries and communication, and help you receive the support and information relevant to your family's needs.</p><p>Your information is used for the operation and improvement of Bubba Hub and the services you choose to use. We do not use your information for unrelated purposes, and your privacy settings give you control over optional sharing. Information may still need to be processed where necessary to provide a service you have requested, complete a booking or payment, maintain account security, or meet legal requirements.</p></div>
      <form method="post" class="bh-stage2-form">
        <?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?><input type="hidden" name="bh_stage2_action" value="privacy">
        <div class="bh-privacy-section">
          <div class="bh-privacy-section-heading"><span>👩‍🏫</span><div><h4>Sharing with class leaders</h4><p>Control optional information sharing with group and class leaders.</p></div></div>
          <?php
          echo $toggle('privacy_restrict_leader_sharing',$restrict_leader_sharing,'Don’t share optional information with class leaders','Turn this on to stop optional contact, child year-of-birth and leader messaging permissions. Booking information that is required to provide the service may still be processed.');
          echo $toggle('privacy_share_contact_leaders',$share_contact,'Share my contact details with class leaders','Allow relevant leaders to receive the contact details needed to communicate about your booking or enquiry.');
          echo $toggle('privacy_share_child_yob_leaders',$share_child_yob,'Share my child’s year of birth','Allow the class leader to see the child’s year of birth where it helps them manage the booking.');
          echo $toggle('privacy_leader_messages',$leader_messages,'Allow messages from class leaders','Allow class leaders to contact you through Bubba Hub about bookings, classes or enquiries.');
          ?>
        </div>
        <div class="bh-privacy-section">
          <div class="bh-privacy-section-heading"><span>✨</span><div><h4>Personalisation</h4><p>Choose whether Bubba Hub can use your preferences to tailor what you see.</p></div></div>
          <?php
          echo $toggle('privacy_personalised_recommendations',$personalised,'Personalised group recommendations','Use your interests, locations and family preferences to suggest relevant activities.');
          echo $toggle('privacy_anonymous_analytics',$analytics,'Anonymous usage analytics','Allow aggregated, non-personal analytics to help improve Bubba Hub features and performance.');
          ?>
        </div>
        <div class="bh-privacy-section">
          <div class="bh-privacy-section-heading"><span>🛡️</span><div><h4>Account security</h4><p>Password and core account security remain managed by the connected account system.</p></div></div>
          <div class="bh-profile-actions"><a class="bh-stage2-button" href="<?php echo esc_url(bubbahub_stage2_account_url()); ?>">Open account & security</a><a class="bh-stage2-button" href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Log out</a></div>
        </div>
        <div class="bh-privacy-section">
          <div class="bh-privacy-section-heading"><span>📦</span><div><h4>Your data</h4><p>Request a copy of your data or ask Bubba Hub to delete your account. Requests are reviewed where bookings, payments or legal records need to be retained.</p></div></div>
          <div class="bh-privacy-request-grid">
            <label class="bh-stage2-check"><input type="radio" name="privacy_request" value="export" <?php checked($existing,'export'); ?>><span><strong>Request my data</strong><small>Ask for a copy of personal information held by Bubba Hub.</small></span></label>
            <label class="bh-stage2-check"><input type="radio" name="privacy_request" value="deletion" <?php checked($existing,'deletion'); ?>><span><strong>Request account deletion</strong><small>Request deletion of your account and personal data, subject to required retention.</small></span></label>
          </div>
        </div>
        <div class="bh-privacy-section">
          <div class="bh-privacy-section-heading"><span>ℹ️</span><div><h4>What class leaders receive</h4><p>Bookings should use the minimum information needed to run the activity.</p></div></div>
          <div class="bh-privacy-data-list">
            <div><strong>Normally shared</strong><span>Booking details and information required to manage the booking.</span></div>
            <div><strong>Optional</strong><span>Your contact details and your child’s year of birth, according to the settings above.</span></div>
            <div><strong>Not provider-facing</strong><span>Full child profile details, full date of birth and private My Hub preferences.</span></div>
          </div>
        </div>
        <div class="bh-profile-actions"><button type="submit">Save privacy settings</button></div>
      </form>
    </div>
    <style>
      .bh-privacy-settings .bh-privacy-notice{margin:0 0 16px;padding:13px 15px;border:1px solid #dbe7e1;border-radius:14px;background:#f7fbf9}.bh-privacy-notice strong{display:block;color:#31584b;font-size:13px}.bh-privacy-notice p{margin:4px 0 0;color:#718079;font-size:11px;line-height:1.5}
      .bh-privacy-purpose{margin:0 0 16px;padding:13px 15px;border:1px solid #dbe7e1;border-radius:14px;background:#fff}.bh-privacy-purpose strong{display:block;color:#31584b;font-size:13px}.bh-privacy-purpose p{margin:5px 0 0;color:#718079;font-size:11px;line-height:1.5}.bh-privacy-purpose p+p{margin-top:7px}
      .bh-privacy-section{padding:16px;margin-top:14px;border:1px solid #e4ece8;border-radius:16px;background:#fbfdfc}.bh-privacy-section-heading{display:flex;gap:10px;align-items:flex-start;margin-bottom:12px}.bh-privacy-section-heading>span{display:flex;align-items:center;justify-content:center;flex:0 0 38px;width:38px;height:38px;border-radius:11px;background:#eaf3ef;font-size:18px}.bh-privacy-section-heading h4{margin:0 0 3px;color:#31584b;font-size:14px;font-weight:800}.bh-privacy-section-heading p{margin:0;color:#718079;font-size:11px;line-height:1.45}
      .bh-privacy-toggle{display:flex;align-items:center;gap:10px;padding:11px 0;border-top:1px solid #e7efeb;cursor:pointer}.bh-privacy-toggle:first-of-type{border-top:0}.bh-privacy-toggle input{position:absolute;opacity:0;pointer-events:none}.bh-privacy-toggle-track{position:relative;flex:0 0 44px;width:44px;height:25px;border-radius:999px;background:#cbd7d1;transition:.2s}.bh-privacy-toggle-track:after{content:"";position:absolute;top:3px;left:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.14);transition:.2s}.bh-privacy-toggle input:checked + .bh-privacy-toggle-track{background:#5f9183}.bh-privacy-toggle input:checked + .bh-privacy-toggle-track:after{transform:translateX(19px)}.bh-privacy-toggle-copy{display:flex;flex-direction:column;gap:2px}.bh-privacy-toggle-copy strong{color:#31584b;font-size:12px}.bh-privacy-toggle-copy small{color:#718079;font-size:11px;line-height:1.4}
      .bh-privacy-request-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.bh-privacy-request-grid .bh-stage2-check{margin:0}.bh-privacy-data-list{display:grid;gap:8px}.bh-privacy-data-list div{padding:10px 11px;border-radius:10px;background:#fff;border:1px solid #e4ece8}.bh-privacy-data-list strong{display:block;color:#31584b;font-size:11px}.bh-privacy-data-list span{display:block;color:#718079;font-size:11px;line-height:1.45;margin-top:2px}
      @media(max-width:650px){.bh-privacy-section{padding:14px}.bh-privacy-request-grid{grid-template-columns:1fr}}
    </style>
    <?php return ob_get_clean();
}
function bubbahub_stage2_render_notification_test() {
    $user=wp_get_current_user();
    ob_start(); ?><div class="bh-profile-card"><div class="bh-profile-card-heading"><div><h3>Notification activity & test</h3><span>Check your email notification delivery</span></div></div><p class="bh-muted">A test email will be sent to <strong><?php echo esc_html($user->user_email); ?></strong>. This checks email delivery only; SMS remains disabled.</p><form method="post"><?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?><input type="hidden" name="bh_stage2_action" value="notification_test"><button type="submit" class="bh-stage2-button">Send test email</button></form></div><?php return ob_get_clean();
}

function bubbahub_stage2_render_pro() {
    $is_pro = bubbahub_stage2_is_pro(); $payment = bubbahub_stage2_payment_url(); $pricing = bubbahub_stage2_pricing_url();
    ob_start(); ?>
    <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Manage my Pro Account</h3><span><?php echo $is_pro ? 'Pro active' : 'Free account'; ?></span></div>
    <div class="bh-pro-status"><strong><?php echo $is_pro ? '⭐ Bubba Pro Active' : 'Bubba Hub Free'; ?></strong><p><?php echo $is_pro ? 'Manage your billing and payment details through the connected payment account.' : 'Upgrade to unlock Pro features and higher account limits.'; ?></p></div>
    <div class="bh-profile-actions"><a class="bh-stage2-button" href="<?php echo esc_url($is_pro ? $payment : $pricing); ?>"><?php echo $is_pro ? 'Manage subscription & billing' : 'View Pro options'; ?></a></div></div>
    <?php return ob_get_clean();
}

function bubbahub_stage2_render_payments() {
    $invoice_url = bubbahub_stage2_getpaid_invoice_url();
    $subscription_url = bubbahub_stage2_getpaid_subscription_url();
    $checkout_url = bubbahub_stage2_getpaid_checkout_url();

    /*
     * GetPaid exposes Invoice History and Subscriptions as configured pages,
     * while Wallet and Wallet Transactions are widgets/shortcodes/blocks.
     * We deliberately render the native components where available instead
     * of inventing page URLs for them.
     */
    $wallet_output = bubbahub_stage2_getpaid_shortcode_output( array( 'wallet' ) );
    $transaction_output = bubbahub_stage2_getpaid_shortcode_output( array( 'wallet_transaction', 'wallet_transactions' ) );

    ob_start(); ?>
    <div class="bh-payment-settings">
        <div class="bh-profile-card">
            <div class="bh-profile-card-heading">
                <h3>My Payments, Invoices &amp; Wallet</h3>
                <span>GetPaid &amp; secure payments</span>
            </div>

            <div class="bh-pro-status">
                <strong>Secure payment management</strong>
                <p>Your full card details are not displayed or stored by Bubba Hub. Payment information is handled by the connected payment provider.</p>
            </div>

            <div class="bh-settings-list bh-payment-options-list" role="navigation" aria-label="Payment settings">
                <div class="bh-account-settings-menu-container">
                    <div class="bh-account-settings-item bh-payment-option bh-payment-option-panel">
                        <span class="bh-account-settings-icon" aria-hidden="true">👛</span>
                        <span class="bh-account-settings-content">
                            <h3>Wallet balance</h3>
                            <p>Use the native GetPaid Wallet component to view your balance and manage wallet funds.</p>
                            <?php if ( $wallet_output ) : ?>
                                <div class="bh-getpaid-native-component"><?php echo $wallet_output; ?></div>
                            <?php else : ?>
                                <p class="bh-muted">The GetPaid Wallet extension is not currently exposing its Wallet component on this site.</p>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="bh-account-settings-menu-container">
                    <div class="bh-account-settings-item bh-payment-option bh-payment-option-panel">
                        <span class="bh-account-settings-icon" aria-hidden="true">↔️</span>
                        <span class="bh-account-settings-content">
                            <h3>Transactions</h3>
                            <p>Review your GetPaid wallet transaction history.</p>
                            <?php if ( $transaction_output ) : ?>
                                <div class="bh-getpaid-native-component"><?php echo $transaction_output; ?></div>
                            <?php else : ?>
                                <p class="bh-muted">The GetPaid Wallet Transactions component is not currently exposing its widget/shortcode on this site.</p>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <div class="bh-account-settings-menu-container">
                    <div class="bh-account-settings-item bh-payment-option bh-payment-option-panel">
                        <span class="bh-account-settings-icon" aria-hidden="true">💳</span>
                        <span class="bh-account-settings-content">
                            <h3>Payment methods</h3>
                            <p>Saved card details are handled by your connected Stripe payment gateway. GetPaid does not provide a separate Payment Methods page in Page Settings.</p>
                            <a class="bh-payment-option-link" href="<?php echo esc_url( $checkout_url ); ?>">Manage saved payment method at checkout →</a>
                        </span>
                    </div>
                </div>

                <div class="bh-account-settings-menu-container">
                    <div class="bh-account-settings-item bh-payment-option bh-payment-option-panel">
                        <span class="bh-account-settings-icon" aria-hidden="true">🧾</span>
                        <span class="bh-account-settings-content">
                            <h3>Invoices</h3>
                            <p>View your GetPaid invoice history and payment status.</p>
                            <a class="bh-payment-option-link" href="<?php echo esc_url( $invoice_url ); ?>">View invoices →</a>
                        </span>
                    </div>
                </div>

                <div class="bh-account-settings-menu-container">
                    <div class="bh-account-settings-item bh-payment-option bh-payment-option-panel">
                        <span class="bh-account-settings-icon" aria-hidden="true">🔄</span>
                        <span class="bh-account-settings-content">
                            <h3>Subscriptions</h3>
                            <p>View your active and previous GetPaid subscriptions.</p>
                            <a class="bh-payment-option-link" href="<?php echo esc_url( $subscription_url ); ?>">View subscriptions →</a>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Main Stage 2 screen
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_account_settings_url( $section = '' ) {
    $url = bubbahub_stage2_account_url();
    $args = array( 'um_tab' => 'bubbahub_account_settings' );
    if ( $section !== '' ) {
        $args['bh_settings_section'] = sanitize_key( $section );
    }
    return add_query_arg( $args, $url );
}

function bubbahub_account_settings_stage2_shortcode() {
    if ( ! is_user_logged_in() ) return '<p>Please log in to manage your account settings.</p>';
    wp_enqueue_style( 'bubbahub-profile-settings' );
    wp_enqueue_style( 'bubbahub-account-settings' );

    $message = '';
    if ( null !== $GLOBALS['bubbahub_stage2_post_result'] ) {
        $message = (string) $GLOBALS['bubbahub_stage2_post_result'];
    } else {
        $message .= bubbahub_stage2_handle_profile();
        $message .= bubbahub_stage2_handle_notifications();
        $message .= bubbahub_stage2_handle_consent();
        $message .= bubbahub_stage2_handle_interests();
    }

    $section = isset( $_GET['bh_settings_section'] ) ? sanitize_key( wp_unslash( $_GET['bh_settings_section'] ) ) : 'home';
    $user = wp_get_current_user();
    $is_pro = bubbahub_stage2_is_pro();
    $um_url = bubbahub_stage2_account_url();
    $payment_url = bubbahub_stage2_payment_url();
    $pricing_url = bubbahub_stage2_pricing_url();

    if ( 'profile' === $section ) $content = '<div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>My Bubba Hub Profile</h3><span>Ultimate Member account</span></div><p>Your profile details are managed in the Ultimate Member account area.</p><div class="bh-profile-actions"><a class="bh-stage2-button" href="'.esc_url( add_query_arg( 'um_tab', 'bubbahub_profile', $um_url ) ).'">Open My Bubba Hub Profile</a></div></div>';
    elseif ( 'notifications' === $section ) $content = bubbahub_stage2_render_notifications();
    elseif ( 'consent' === $section ) $content = bubbahub_stage2_render_consent();
    elseif ( 'preferences' === $section || 'interests' === $section || 'family_needs' === $section ) $content = bubbahub_stage2_render_preferences();
    elseif ( 'calendar' === $section ) $content = bubbahub_stage2_render_calendar_settings();
    elseif ( 'privacy' === $section ) $content = bubbahub_stage2_render_privacy();
    elseif ( 'notification_test' === $section ) $content = bubbahub_stage2_render_notification_test();
    elseif ( 'pro' === $section ) $content = bubbahub_stage2_render_pro();
    elseif ( 'payments' === $section ) $content = bubbahub_stage2_render_payments();
    else $content = '';

    if ( $content ) {
        $back = bubbahub_stage2_account_settings_url();
        ob_start(); ?>
        <div id="bh-account-settings-screen" class="bh-profile-shell"><div class="bh-profile-header dark"><div><span class="bh-profile-kicker">ACCOUNT SETTINGS</span><h1>Your Account Settings</h1><p>Manage your Bubba Hub profile, membership, interests, notifications and payments.</p></div><a class="bh-profile-back light" href="<?php echo esc_url($back); ?>">‹ Back to settings</a></div><?php if($message): ?><div class="bh-profile-success">✓ <?php echo esc_html($message); ?></div><?php endif; ?><?php echo $content; ?></div><?php return ob_get_clean();
    }

    ob_start(); ?>
    <div id="bh-account-settings-screen" class="bh-profile-shell">
        <div class="bh-profile-header dark"><div><span class="bh-profile-kicker">ACCOUNT SETTINGS</span><h1>Your Account Settings</h1><p>Manage your Bubba Hub profile, membership and payment details in one place.</p><?php if($is_pro): ?><div class="bh-pro-pill">⭐ Pro Member Active</div><?php endif; ?></div><a class="bh-profile-back light" href="<?php echo esc_url(remove_query_arg('bh_account_settings')); ?>">‹ Back to My Hub</a></div>
        <?php if($message): ?><div class="bh-profile-success">✓ <?php echo esc_html($message); ?></div><?php endif; ?>
        <div id="bh-account-settings-list" class="bh-settings-list" role="navigation" aria-label="Account settings">
            <div class="bh-account-settings-menu-container"><a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg('um_tab', 'bubbahub_profile', $um_url)); ?>">
                <span class="bh-account-settings-icon" aria-hidden="true">👤</span><span class="bh-account-settings-content"><h3>Edit my profile</h3><p>Personal details, contact information and account addresses.</p></span>
            </a></div>
            <div class="bh-account-settings-menu-container"><a class="bh-account-settings-item" href="<?php echo esc_url(bubbahub_stage2_account_settings_url('pro')); ?>"><span class="bh-account-settings-icon" aria-hidden="true">⭐</span><span class="bh-account-settings-content"><h3>Manage my Pro Account</h3><p><?php echo $is_pro ? 'Manage your active membership and billing.' : 'View Pro options and membership information.'; ?></p></span></a></div>
            <div class="bh-account-settings-menu-container"><a class="bh-account-settings-item" href="<?php echo esc_url(bubbahub_stage2_account_settings_url('family_needs')); ?>"><span class="bh-account-settings-icon" aria-hidden="true">✨</span><span class="bh-account-settings-content"><h3>My Bubba Hub Directory Preferences</h3><p>Manage your interests, family needs, preferred locations and search preferences in one place.</p></span></a></div>
            <div class="bh-account-settings-menu-container"><a class="bh-account-settings-item" href="<?php echo esc_url(bubbahub_stage2_account_settings_url('calendar')); ?>"><span class="bh-account-settings-icon" aria-hidden="true">🗓️</span><span class="bh-account-settings-content"><h3>Calendar Settings</h3><p>Choose your default view, visible days, time of day, free/paid activities, nap schedule and reminder settings.</p></span></a></div>
            <div class="bh-account-settings-menu-container"><a class="bh-account-settings-item" href="<?php echo esc_url(bubbahub_stage2_account_settings_url('notification_test')); ?>"><span class="bh-account-settings-icon" aria-hidden="true">🧪</span><span class="bh-account-settings-content"><h3>Notification Test</h3><p>Send a test email to check your account notification delivery.</p></span></a></div>
            <div class="bh-account-settings-menu-container"><a class="bh-account-settings-item" href="<?php echo esc_url(bubbahub_stage2_account_settings_url('privacy')); ?>"><span class="bh-account-settings-icon" aria-hidden="true">🔐</span><span class="bh-account-settings-content"><h3>Privacy & Security</h3><p>Open account security and submit data or deletion requests.</p></span></a></div>
            <div class="bh-account-settings-menu-container"><a class="bh-account-settings-item" href="<?php echo esc_url(bubbahub_stage2_account_settings_url('notifications')); ?>"><span class="bh-account-settings-icon" aria-hidden="true">🔔</span><span class="bh-account-settings-content"><h3>Notification preferences</h3><p>Manage every optional notification type, including new groups, updates, suggestions, bookings and planner alerts.</p></span></a></div>
            <div class="bh-account-settings-menu-container"><a class="bh-account-settings-item" href="<?php echo esc_url(bubbahub_stage2_account_settings_url('consent')); ?>"><span class="bh-account-settings-icon" aria-hidden="true">🛡️</span><span class="bh-account-settings-content"><h3>Class Consent & Safety</h3><p>Manage safety information, contact consent and media permissions.</p></span></a></div>
            <div class="bh-account-settings-menu-container"><a class="bh-account-settings-item" href="<?php echo esc_url(bubbahub_stage2_account_settings_url('payments')); ?>"><span class="bh-account-settings-icon" aria-hidden="true">💳</span><span class="bh-account-settings-content"><h3>My Payments, Invoices &amp; Wallet</h3><p>Manage payment methods, invoices, wallet balance, transactions and subscriptions.</p></span></a></div>
        </div>
    </div>
    <?php return ob_get_clean();
}