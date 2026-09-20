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

$GLOBALS['bubbahub_stage2_post_result'] = null;

function bubbahub_account_settings_stage2_register() {
    add_shortcode( 'bubbahub_account_settings_stage2', 'bubbahub_account_settings_stage2_shortcode' );
}

function bubbahub_stage2_post_redirect() {
    if ( ! is_user_logged_in() || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['bh_stage2_action'] ) ) return;

    $action = sanitize_key( wp_unslash( $_POST['bh_stage2_action'] ) );
    $handlers = array(
        'profile'       => 'bubbahub_stage2_handle_profile',
        'notifications' => 'bubbahub_stage2_handle_notifications',
        'consent'       => 'bubbahub_stage2_handle_consent',
        'interests'     => 'bubbahub_stage2_handle_interests',
    );
    if ( empty( $handlers[ $action ] ) || ! function_exists( $handlers[ $action ] ) ) return;

    $result = call_user_func( $handlers[ $action ] );
    $GLOBALS['bubbahub_stage2_post_result'] = $result;

    $successful_results = array(
        'Profile details updated successfully.',
        'Notification preferences saved.',
        'Consent and safety details saved.',
        'Interests and group preferences saved.',
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

    $keys = array( 'new_groups','group_updates','new_suggestions','saved_group_updates','new_classes','booking_alerts','booking_reminders','planner_reminders','messages','community_alerts','whats_on','email_digest' );
    foreach ( $keys as $key ) bubbahub_stage2_update_meta( 'bubbahub_' . $key, ! empty( $_POST[ $key ] ) ? '1' : '0' );
    bubbahub_stage2_update_meta( 'bubbahub_sms_reminders', '0' );

    if ( function_exists( 'bubbahub_notification_save_preferences' ) ) {
        bubbahub_notification_save_preferences( get_current_user_id(), array(
            'class_booking' => ! empty( $_POST['booking_alerts'] ),
            'booking_reminders' => ! empty( $_POST['booking_reminders'] ),
            'saved_groups' => ! empty( $_POST['saved_group_updates'] ),
            'planner_reminders' => ! empty( $_POST['planner_reminders'] ),
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
    update_user_meta( get_current_user_id(), 'user_interest', implode( ', ', $user_interests ) );
    update_user_meta( get_current_user_id(), 'User_interest', implode( ', ', $user_interests ) );

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

    $age_range = isset( $_POST['preferred_age_range'] ) ? sanitize_text_field( wp_unslash( $_POST['preferred_age_range'] ) ) : '';
    $session_length = isset( $_POST['preferred_session_length'] ) ? sanitize_key( wp_unslash( $_POST['preferred_session_length'] ) ) : '';
    $price_bracket = isset( $_POST['preferred_price_bracket'] ) ? sanitize_key( wp_unslash( $_POST['preferred_price_bracket'] ) ) : '';

    if ( ! in_array( $age_range, $age_ranges, true ) ) $age_range = '';
    if ( ! in_array( $session_length, $session_lengths, true ) ) $session_length = '';
    if ( ! in_array( $price_bracket, $price_brackets, true ) ) $price_bracket = '';

    bubbahub_stage2_update_meta( 'bubbahub_preferred_age_range', $age_range );
    bubbahub_stage2_update_meta( 'bubbahub_preferred_session_length', $session_length );
    bubbahub_stage2_update_meta( 'bubbahub_preferred_price_bracket', $price_bracket );
    update_user_meta( get_current_user_id(), 'preferred_age_range', $age_range );
    update_user_meta( get_current_user_id(), 'preferred_session_length', $session_length );
    update_user_meta( get_current_user_id(), 'preferred_price_bracket', $price_bracket );

    return 'Interests and preferences saved.';
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
    </div>
    <?php return ob_get_clean();
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
    </style>
    <?php return ob_get_clean();
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

    $location_terms = bubbahub_stage2_location_terms();
    $age_range = (string) bubbahub_stage2_user_meta('bubbahub_preferred_age_range', '');
    $session_length = (string) bubbahub_stage2_user_meta('bubbahub_preferred_session_length', '');
    $price_bracket = (string) bubbahub_stage2_user_meta('bubbahub_preferred_price_bracket', '');

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
            <div><h3>My Interests</h3><span>Tell us what you and your family enjoy</span></div>
        </div>
        <form method="post" class="bh-stage2-form bh-preferences-form" data-bh-preferences-form>
            <?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?>
            <input type="hidden" name="bh_stage2_action" value="interests">

            <div class="bh-preference-section">
                <div class="bh-preference-heading"><h4>My Interests <small>(User_interest)</small></h4><p>Start typing to get suggestions from tags already used by Bubba Hub groups, or enter your own interest.</p></div>
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

            <div class="bh-preference-section">
                <div class="bh-preference-heading"><h4>Preferred Locations</h4><p>Add as many towns, areas or regions as you like. Each preference is listed separately.</p></div>
                <div class="bh-chip-editor" data-bh-chip-editor data-field="preferred_locations">
                    <div class="bh-chip-list" data-bh-chip-list>
                        <?php foreach ( $preferred_locations as $location ) : ?>
                            <span class="bh-preference-chip"><span><?php echo esc_html($location); ?></span><button type="button" data-bh-remove aria-label="Remove <?php echo esc_attr($location); ?>">×</button><input type="hidden" name="preferred_locations[]" value="<?php echo esc_attr($location); ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="bh-chip-input-row">
                        <input type="text" data-bh-chip-input list="bh-location-suggestions" placeholder="e.g. Torbay, Exeter, Paignton" autocomplete="off">
                        <button type="button" class="bh-chip-add" data-bh-add>Add location</button>
                    </div>
                    <datalist id="bh-location-suggestions"><?php foreach ( $location_terms as $term ) : ?><option value="<?php echo esc_attr($term->name); ?>"></option><?php endforeach; ?></datalist>
                </div>
            </div>

            <div class="bh-preference-grid">
                <div class="bh-preference-section">
                    <div class="bh-preference-heading"><h4>Preferred Age Range</h4><p>Choose the age range you want to see in your group suggestions.</p></div>
                    <label class="bh-preference-field"><span>Age range</span><select name="preferred_age_range"><?php foreach($age_options as $value=>$label): ?><option value="<?php echo esc_attr($value); ?>" <?php selected($age_range,$value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="bh-preference-section">
                    <div class="bh-preference-heading"><h4>Preferred Session Length</h4><p>Choose the session length that suits your family.</p></div>
                    <label class="bh-preference-field"><span>Session length</span><select name="preferred_session_length"><?php foreach($session_options as $value=>$label): ?><option value="<?php echo esc_attr($value); ?>" <?php selected($session_length,$value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="bh-preference-section">
                    <div class="bh-preference-heading"><h4>Preferred Price Bracket</h4><p>Choose your preferred price range per session.</p></div>
                    <label class="bh-preference-field"><span>Price bracket</span><select name="preferred_price_bracket"><?php foreach($price_options as $value=>$label): ?><option value="<?php echo esc_attr($value); ?>" <?php selected($price_bracket,$value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                </div>
            </div>

            <div class="bh-profile-actions"><button type="submit">Save interests & preferences</button></div>
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
    <?php return ob_get_clean();
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
        <div class="bh-account-grid" role="navigation" aria-label="Account settings">
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'profile'))); ?>">
                <span class="bh-account-icon" aria-hidden="true">👤</span><span class="bh-account-card-body"><h3>Edit my profile</h3><p>Personal details, contact information, addresses and search radius.</p></span><span class="bh-account-card-arrow" aria-hidden="true">→</span>
            </a>
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'pro'))); ?>">
                <span class="bh-account-icon" aria-hidden="true">⭐</span><span class="bh-account-card-body"><h3>Manage my Pro Account</h3><p><?php echo $is_pro ? 'Manage your active membership and billing.' : 'View Pro options and membership information.'; ?></p></span><span class="bh-account-card-arrow" aria-hidden="true">→</span>
            </a>
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'interests'))); ?>">
                <span class="bh-account-icon" aria-hidden="true">✨</span><span class="bh-account-card-body"><h3>My Interests & Groups</h3><p>Choose interests from tags already used by Bubba Hub groups.</p></span><span class="bh-account-card-arrow" aria-hidden="true">→</span>
            </a>
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'notifications'))); ?>">
                <span class="bh-account-icon" aria-hidden="true">🔔</span><span class="bh-account-card-body"><h3>Notification preferences</h3><p>Manage class, community, email and SMS notification choices.</p></span><span class="bh-account-card-arrow" aria-hidden="true">→</span>
            </a>
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'consent'))); ?>">
                <span class="bh-account-icon" aria-hidden="true">🛡️</span><span class="bh-account-card-body"><h3>Class Consent & Safety</h3><p>Manage safety information, contact consent and media permissions.</p></span><span class="bh-account-card-arrow" aria-hidden="true">→</span>
            </a>
            <a class="bh-account-card" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'payments'))); ?>">
                <span class="bh-account-icon" aria-hidden="true">💳</span><span class="bh-account-card-body"><h3>Payment methods</h3><p>Open the connected GetPaid / Stripe payment area securely.</p></span><span class="bh-account-card-arrow" aria-hidden="true">→</span>
            </a>
        </div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Connected account</h3><span>Ultimate Member / WordPress</span></div><div class="bh-connected-row"><div><strong><?php echo esc_html($user->display_name); ?></strong><small><?php echo esc_html($user->user_email); ?></small></div><a href="<?php echo esc_url($um_url); ?>">Open Ultimate Member →</a></div></div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Child profiles</h3><a href="<?php echo esc_url(add_query_arg('bh_add_child','1')); ?>">＋ Manage children</a></div><?php if($children): ?><div class="bh-account-children-list"><?php foreach($children as $child): $name=function_exists('bubbahub_profile_field')?bubbahub_profile_field($child->ID,'child_name',$child->post_title):$child->post_title; $status=function_exists('bubbahub_profile_field')?bubbahub_profile_field($child->ID,'child_status','born'):'born'; ?><div><span class="bh-mini-avatar"><?php echo esc_html(strtoupper(substr((string)$name,0,1))); ?></span><div><strong><?php echo esc_html($name); ?></strong><small><?php echo 'expecting'===$status?'Expecting':'Child profile'; ?></small></div><a href="<?php echo esc_url(add_query_arg(array('bh_add_child'=>1,'child_id'=>$child->ID))); ?>">Edit</a></div><?php endforeach; ?></div><?php else: ?><p class="bh-muted">No child profiles have been added yet.</p><?php endif; ?></div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Payment account</h3><span>GetPaid connection</span></div><div class="bh-connected-row"><div><strong><?php echo $is_pro ? 'Pro / payment account available' : 'Payment history and invoices'; ?></strong><small>Use the connected payment area for invoices, subscriptions and secure payment details.</small></div><a href="<?php echo esc_url($payment_url); ?>">Open payments →</a></div></div>
    </div>
    <?php return ob_get_clean();
}
