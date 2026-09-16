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

function bubbahub_account_settings_stage2_register() {
    add_shortcode( 'bubbahub_account_settings_stage2', 'bubbahub_account_settings_stage2_shortcode' );
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

function bubbahub_stage2_payment_url() {
    return bubbahub_stage2_url( 'bubbahub_getpaid_account_url', '/my-bookings/' );
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
        'search_radius' => 'sanitize_text_field',
    );
    foreach ( $fields as $field => $sanitizer ) {
        $value = isset( $_POST[ $field ] ) ? call_user_func( $sanitizer, wp_unslash( $_POST[ $field ] ) ) : '';
        bubbahub_stage2_update_meta( 'bubbahub_' . $field, $value );
    }

    return 'Profile details updated successfully.';
}

/* -------------------------------------------------------------------------
 * Save notification preferences
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_handle_notifications() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_stage2_action'] ) || 'notifications' !== $_POST['bh_stage2_action'] ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';

    foreach ( array( 'push_classes', 'email_digest', 'sms_reminders', 'community_alerts' ) as $key ) {
        bubbahub_stage2_update_meta( 'bubbahub_' . $key, ! empty( $_POST[ $key ] ) ? '1' : '0' );
    }
    return 'Notification preferences saved.';
}

/* -------------------------------------------------------------------------
 * Save consent and safety settings
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_handle_consent() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_stage2_action'] ) || 'consent' !== $_POST['bh_stage2_action'] ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';

    $required = array( 'profile_shared_ack', 'class_leader_contact', 'payment_agreement', 'liability_ack' );
    foreach ( $required as $key ) {
        if ( empty( $_POST[ $key ] ) ) return 'Please confirm all required safety and booking acknowledgements before saving.';
        bubbahub_stage2_update_meta( 'bubbahub_consent_' . $key, '1' );
    }
    foreach ( array( 'media_social', 'media_promotional', 'media_head_office' ) as $key ) {
        bubbahub_stage2_update_meta( 'bubbahub_consent_' . $key, ! empty( $_POST[ $key ] ) ? '1' : '0' );
    }
    bubbahub_stage2_update_meta( 'bubbahub_consent_allergies', isset( $_POST['allergies'] ) ? sanitize_text_field( wp_unslash( $_POST['allergies'] ) ) : '' );
    bubbahub_stage2_update_meta( 'bubbahub_consent_medical_notes', isset( $_POST['medical_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['medical_notes'] ) ) : '' );
    bubbahub_stage2_update_meta( 'bubbahub_consent_saved_at', current_time( 'mysql' ) );
    return 'Consent and safety details saved.';
}

/* -------------------------------------------------------------------------
 * Save interests and existing website taxonomy term IDs
 * ---------------------------------------------------------------------- */
function bubbahub_stage2_handle_interests() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_stage2_action'] ) || 'interests' !== $_POST['bh_stage2_action'] ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';

    $taxonomy = bubbahub_stage2_taxonomy();
    $term_ids = array();
    if ( $taxonomy && ! empty( $_POST['interest_terms'] ) && is_array( $_POST['interest_terms'] ) ) {
        foreach ( $_POST['interest_terms'] as $term_id ) {
            $term_id = absint( $term_id );
            if ( $term_id && term_exists( $term_id, $taxonomy ) ) $term_ids[] = $term_id;
        }
    }
    $term_ids = array_values( array_unique( $term_ids ) );
    bubbahub_stage2_update_meta( 'bubbahub_interest_taxonomy', $taxonomy );
    bubbahub_stage2_update_meta( 'bubbahub_interest_term_ids', $term_ids );

    $custom_groups = array();
    if ( ! empty( $_POST['custom_groups'] ) && is_array( $_POST['custom_groups'] ) ) {
        foreach ( $_POST['custom_groups'] as $group ) {
            $group = sanitize_text_field( wp_unslash( $group ) );
            if ( $group ) $custom_groups[] = $group;
        }
    }
    $custom_groups = array_values( array_unique( array_slice( $custom_groups, 0, bubbahub_stage2_is_pro() ? 100 : 5 ) ) );
    bubbahub_stage2_update_meta( 'bubbahub_custom_group_lists', $custom_groups );
    return 'Interests and group preferences saved.';
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
        <form method="post" class="bh-stage2-form">
            <?php wp_nonce_field( 'bh_stage2_settings', 'bh_stage2_nonce' ); ?><input type="hidden" name="bh_stage2_action" value="profile">
            <div class="bh-profile-grid two">
                <label><span>Full name / display name</span><input name="display_name" value="<?php echo esc_attr( $user->display_name ); ?>" required></label>
                <label><span>Email address</span><input type="email" name="email" value="<?php echo esc_attr( $user->user_email ); ?>" required></label>
                <label><span>Contact number</span><input name="phone" value="<?php echo esc_attr( $get('phone') ); ?>"></label>
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
    </div>
    <?php return ob_get_clean();
}

function bubbahub_stage2_render_notifications() {
    ob_start(); ?>
    <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Notification preferences</h3><span>Choose how Bubba Hub contacts you</span></div>
    <form method="post" class="bh-stage2-form"><?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?><input type="hidden" name="bh_stage2_action" value="notifications">
    <?php $items=array('push_classes'=>'Class and booking alerts','email_digest'=>'Email digest','sms_reminders'=>'SMS reminders','community_alerts'=>'Community alerts'); foreach($items as $key=>$label): ?><label class="bh-stage2-check"><input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_'.$key,'1'),'1'); ?>><span><strong><?php echo esc_html($label); ?></strong><small>Receive relevant Bubba Hub updates through this channel.</small></span></label><?php endforeach; ?>
    <div class="bh-profile-actions"><button type="submit">Save notification preferences</button></div></form></div><?php return ob_get_clean();
}

function bubbahub_stage2_render_consent() {
    $saved = bubbahub_stage2_user_meta('bubbahub_consent_saved_at');
    ob_start(); ?>
    <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Class consent & safety</h3><span><?php echo $saved ? 'Last saved '.esc_html(wp_date('j M Y',strtotime($saved))) : 'Not completed yet'; ?></span></div>
    <form method="post" class="bh-stage2-form"><?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?><input type="hidden" name="bh_stage2_action" value="consent">
    <label class="bh-stage2-check"><input type="checkbox" name="profile_shared_ack" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_profile_shared_ack'),'1'); ?> required><span><strong>Profile information is accurate and may be shared securely with a class provider for attendance and safety.</strong></span></label>
    <label class="bh-stage2-check"><input type="checkbox" name="class_leader_contact" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_class_leader_contact'),'1'); ?> required><span><strong>Class leaders may contact me by phone, email or SMS about sessions and emergencies.</strong></span></label>
    <label class="bh-stage2-check"><input type="checkbox" name="payment_agreement" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_payment_agreement'),'1'); ?> required><span><strong>I agree to the payment, refund and cancellation terms for bookings.</strong></span></label>
    <div class="bh-profile-grid two"><label><span>Allergies / dietary information</span><input name="allergies" value="<?php echo esc_attr(bubbahub_stage2_user_meta('bubbahub_consent_allergies')); ?>"></label><label><span>Medical / safety notes</span><textarea name="medical_notes" rows="3"><?php echo esc_textarea(bubbahub_stage2_user_meta('bubbahub_consent_medical_notes')); ?></textarea></label></div>
    <div class="bh-profile-card-heading" style="margin-top:20px"><h3>Photo & media permissions</h3><span>Optional</span></div>
    <?php foreach(array('media_social'=>'Social media','media_promotional'=>'Promotional materials','media_head_office'=>'Bubba Hub / head office use') as $key=>$label): ?><label class="bh-stage2-check"><input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_'.$key),'1'); ?>><span><strong><?php echo esc_html($label); ?></strong></span></label><?php endforeach; ?>
    <label class="bh-stage2-check"><input type="checkbox" name="liability_ack" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_liability_ack'),'1'); ?> required><span><strong>I acknowledge the class provider's safety and liability requirements.</strong></span></label>
    <div class="bh-profile-actions"><button type="submit">Save consent & safety</button></div></form></div><?php return ob_get_clean();
}

function bubbahub_stage2_render_interests() {
    $terms = bubbahub_stage2_taxonomy_terms();
    $selected = (array) bubbahub_stage2_user_meta('bubbahub_interest_term_ids',array());
    $custom = (array) bubbahub_stage2_user_meta('bubbahub_custom_group_lists',array());
    $is_pro = bubbahub_stage2_is_pro();
    $taxonomy = bubbahub_stage2_taxonomy();
    ob_start(); ?>
    <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>My interests & groups</h3><span><?php echo $taxonomy ? 'Using existing website tags' : 'No group interest taxonomy detected'; ?></span></div>
    <form method="post" class="bh-stage2-form"><?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?><input type="hidden" name="bh_stage2_action" value="interests">
    <?php if($terms): ?><div class="bh-interest-tags"><?php foreach($terms as $term): ?><label><input type="checkbox" name="interest_terms[]" value="<?php echo esc_attr($term->term_id); ?>" <?php checked(in_array((int)$term->term_id,array_map('intval',$selected),true)); ?>><span><?php echo esc_html($term->name); ?></span></label><?php endforeach; ?></div><?php else: ?><p class="bh-muted">Create or assign an interest/tag taxonomy to the <strong>group</strong> listings and it will appear here automatically.</p><?php endif; ?>
    <div class="bh-profile-card-heading" style="margin-top:20px"><h3>Custom group lists</h3><span><?php echo $is_pro ? 'Unlimited Pro' : count($custom).' / 5 on Free'; ?></span></div>
    <div class="bh-custom-groups"><?php foreach($custom as $index=>$group): ?><label><input type="hidden" name="custom_groups[]" value="<?php echo esc_attr($group); ?>"><span><?php echo esc_html($group); ?></span></label><?php endforeach; ?></div>
    <p class="bh-muted">Custom group lists are stored with your account. Existing website taxonomy tags remain the source for selectable interests.</p>
    <div class="bh-profile-actions"><button type="submit">Save interests & groups</button></div></form></div><?php return ob_get_clean();
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
    $url = bubbahub_stage2_payment_methods_url();
    ob_start(); ?>
    <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Payment methods</h3><span>Secure payment provider</span></div>
    <div class="bh-pro-status"><strong>💳 Your payment details stay with the payment provider.</strong><p>Bubba Hub does not display or store full card numbers here. Use the connected GetPaid / Stripe customer area to add, remove or update a payment method.</p></div>
    <div class="bh-profile-actions"><a class="bh-stage2-button" href="<?php echo esc_url($url); ?>">Open payment methods</a></div></div>
    <?php return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Main Stage 2 screen
 * ---------------------------------------------------------------------- */
function bubbahub_account_settings_stage2_shortcode() {
    if ( ! is_user_logged_in() ) return '<p>Please log in to manage your account settings.</p>';
    wp_enqueue_style( 'bubbahub-profile-settings' );

    $message = '';
    $message .= bubbahub_stage2_handle_profile();
    $message .= bubbahub_stage2_handle_notifications();
    $message .= bubbahub_stage2_handle_consent();
    $message .= bubbahub_stage2_handle_interests();

    $section = isset( $_GET['bh_settings_section'] ) ? sanitize_key( wp_unslash( $_GET['bh_settings_section'] ) ) : 'home';
    $user = wp_get_current_user();
    $children = bubbahub_stage2_children();
    $is_pro = bubbahub_stage2_is_pro();
    $um_url = bubbahub_stage2_account_url();
    $payment_url = bubbahub_stage2_payment_url();
    $pricing_url = bubbahub_stage2_pricing_url();

    if ( 'profile' === $section ) $content = bubbahub_stage2_render_profile( $user );
    elseif ( 'notifications' === $section ) $content = bubbahub_stage2_render_notifications();
    elseif ( 'consent' === $section ) $content = bubbahub_stage2_render_consent();
    elseif ( 'interests' === $section ) $content = bubbahub_stage2_render_interests();
    elseif ( 'pro' === $section ) $content = bubbahub_stage2_render_pro();
    elseif ( 'payments' === $section ) $content = bubbahub_stage2_render_payments();
    else $content = '';

    if ( $content ) {
        $back = add_query_arg( 'bh_account_settings', '1', remove_query_arg( 'bh_settings_section' ) );
        ob_start(); ?>
        <div class="bh-profile-shell"><div class="bh-profile-header dark"><div><span class="bh-profile-kicker">ACCOUNT SETTINGS</span><h1>Your Account Settings</h1><p>Manage your Bubba Hub profile, children, membership, interests, notifications and payments.</p></div><a class="bh-profile-back light" href="<?php echo esc_url($back); ?>">‹ Back to settings</a></div><?php if($message): ?><div class="bh-profile-success">✓ <?php echo esc_html($message); ?></div><?php endif; ?><?php echo $content; ?></div><?php return ob_get_clean();
    }

    ob_start(); ?>
    <div class="bh-profile-shell">
        <div class="bh-profile-header dark"><div><span class="bh-profile-kicker">ACCOUNT SETTINGS</span><h1>Your Account Settings</h1><p>Manage your Bubba Hub profile, children, membership and payment details in one place.</p><?php if($is_pro): ?><div class="bh-pro-pill">⭐ Pro Member Active</div><?php endif; ?></div><a class="bh-profile-back light" href="<?php echo esc_url(remove_query_arg('bh_account_settings')); ?>">‹ Back to My Hub</a></div>
        <?php if($message): ?><div class="bh-profile-success">✓ <?php echo esc_html($message); ?></div><?php endif; ?>
        <div class="bh-account-grid">
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'profile'))); ?>"><span class="bh-account-icon">👤</span><div><h3>Edit my profile</h3><p>Personal details, contact information, addresses and search radius.</p></div><span>→</span></a>
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'pro'))); ?>"><span class="bh-account-icon">⭐</span><div><h3>Manage my Pro Account</h3><p><?php echo $is_pro ? 'Manage your active membership and billing.' : 'View Pro options and membership information.'; ?></p></div><span>→</span></a>
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'interests'))); ?>"><span class="bh-account-icon">✨</span><div><h3>My Interests & Groups</h3><p>Choose interests from tags already used by Bubba Hub groups.</p></div><span>→</span></a>
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'notifications'))); ?>"><span class="bh-account-icon">🔔</span><div><h3>Notification preferences</h3><p>Manage class, community, email and SMS notification choices.</p></div><span>→</span></a>
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'consent'))); ?>"><span class="bh-account-icon">🛡️</span><div><h3>Class Consent & Safety</h3><p>Manage safety information, contact consent and media permissions.</p></div><span>→</span></a>
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'payments'))); ?>"><span class="bh-account-icon">💳</span><div><h3>Payment methods</h3><p>Open the connected GetPaid / Stripe payment area securely.</p></div><span>→</span></a>
        </div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Connected account</h3><span>Ultimate Member / WordPress</span></div><div class="bh-connected-row"><div><strong><?php echo esc_html($user->display_name); ?></strong><small><?php echo esc_html($user->user_email); ?></small></div><a href="<?php echo esc_url($um_url); ?>">Open Ultimate Member →</a></div></div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Child profiles</h3><a href="<?php echo esc_url(add_query_arg('bh_add_child','1')); ?>">＋ Manage children</a></div><?php if($children): ?><div class="bh-account-children-list"><?php foreach($children as $child): $name=function_exists('bubbahub_profile_field')?bubbahub_profile_field($child->ID,'child_name',$child->post_title):$child->post_title; $status=function_exists('bubbahub_profile_field')?bubbahub_profile_field($child->ID,'child_status','born'):'born'; ?><div><span class="bh-mini-avatar"><?php echo esc_html(strtoupper(substr((string)$name,0,1))); ?></span><div><strong><?php echo esc_html($name); ?></strong><small><?php echo 'expecting'===$status?'Expecting':'Child profile'; ?></small></div><a href="<?php echo esc_url(add_query_arg(array('bh_add_child'=>1,'child_id'=>$child->ID))); ?>">Edit</a></div><?php endforeach; ?></div><?php else: ?><p class="bh-muted">No child profiles have been added yet.</p><?php endif; ?></div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Payment account</h3><span>GetPaid connection</span></div><div class="bh-connected-row"><div><strong><?php echo $is_pro ? 'Pro / payment account available' : 'Payment history and invoices'; ?></strong><small>Use the connected payment area for invoices, subscriptions and secure payment details.</small></div><a href="<?php echo esc_url($payment_url); ?>">Open payments →</a></div></div>
    </div>
    <?php return ob_get_clean();
}
