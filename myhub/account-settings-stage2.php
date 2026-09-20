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
    return '1.0';
}

function bubbahub_stage2_handle_consent() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_stage2_action'] ) || 'consent' !== $_POST['bh_stage2_action'] ) return '';
    if ( empty( $_POST['bh_stage2_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_stage2_nonce'] ) ), 'bh_stage2_settings' ) ) return 'Security check failed. Please try again.';

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
        if ( empty( $_POST[ $key ] ) ) return 'Please confirm all required consent and booking declarations before saving.';
        bubbahub_stage2_update_meta( 'bubbahub_consent_' . $key, '1' );
    }

    $child_profile_id = isset( $_POST['child_profile_id'] ) ? absint( $_POST['child_profile_id'] ) : 0;
    if ( $child_profile_id ) {
        $child_post = get_post( $child_profile_id );
        if ( ! $child_post || 'bh_child' !== $child_post->post_type || absint( $child_post->post_author ) !== get_current_user_id() ) {
            return 'Please select one of your saved child profiles.';
        }
    }
    bubbahub_stage2_update_meta( 'bubbahub_consent_child_profile_id', $child_profile_id );

    $fields = array(
        'relationship'        => 'sanitize_text_field',
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

    foreach ( array( 'media_social', 'media_promotional', 'media_head_office' ) as $key ) {
        bubbahub_stage2_update_meta( 'bubbahub_consent_' . $key, ! empty( $_POST[ $key ] ) ? '1' : '0' );
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
        'participant_name','allergies','medical_notes',
        'accessibility_notes','additional_notes','profile_shared_ack',
        'class_leader_contact','payment_agreement','liability_ack',
        'booking_terms_ack','data_processing_ack','accuracy_declaration',
        'media_social','media_promotional','media_head_office','child_profile_id','child_year_of_birth','version','saved_at',
    );
    $snapshot = array();
    foreach ( $keys as $key ) {
        $meta_key = 'bubbahub_consent_' . $key;
        $snapshot[ $key ] = get_user_meta( $uid, $meta_key, true );
    }

    $child_id = absint( get_user_meta( $uid, 'bubbahub_consent_child_profile_id', true ) );
    $child_year = '';
    $child_name = '';
    if ( $child_id && 'bh_child' === get_post_type( $child_id ) && absint( get_post_field( 'post_author', $child_id ) ) === $uid ) {
        $child_name = function_exists( 'bubbahub_profile_field' ) ? bubbahub_profile_field( $child_id, 'child_name', get_the_title( $child_id ) ) : get_the_title( $child_id );
        $child_dob = function_exists( 'bubbahub_profile_field' ) ? bubbahub_profile_field( $child_id, 'child_date_of_birth', '' ) : get_post_meta( $child_id, 'child_date_of_birth', true );
        if ( $child_dob ) {
            $child_year = wp_date( 'Y', strtotime( $child_dob ) );
        }
    }
    $snapshot['child_profile_id'] = $child_id;
    /* Provider-facing child profile data is deliberately limited to year of birth. */
    $share_child_yob = '1' === (string) get_user_meta( $uid, 'bubbahub_privacy_share_child_yob_leaders', true );
    $share_contact = '1' === (string) get_user_meta( $uid, 'bubbahub_privacy_share_contact_leaders', true );
    $leader_messages = '1' === (string) get_user_meta( $uid, 'bubbahub_privacy_leader_messages', true );

    $snapshot['child_profile'] = array(
        'year_of_birth' => $share_child_yob ? sanitize_text_field( $child_year ) : '',
    );
    $snapshot['child_year_of_birth'] = $share_child_yob ? sanitize_text_field( $child_year ) : '';
    $snapshot['provider_privacy'] = array(
        'share_contact' => $share_contact,
        'share_child_year_of_birth' => $share_child_yob,
        'allow_leader_messages' => $leader_messages,
    );
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
    return ! empty( $snapshot['version'] ) && ! empty( $snapshot['saved_at'] );
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
    update_post_meta( $post_id, '_bh_child_profile_id_internal', absint( $snapshot['child_profile_id'] ) );
    update_post_meta( $post_id, '_bh_child_year_of_birth', sanitize_text_field( $snapshot['child_year_of_birth'] ) );
    /* Provider-facing child profile data is limited to year of birth and respects the user's sharing preference. */
    update_post_meta( $post_id, '_bh_child_profile_for_provider', array(
        'year_of_birth' => sanitize_text_field( $snapshot['child_year_of_birth'] ),
    ) );
    update_post_meta( $post_id, '_bh_provider_privacy', isset( $snapshot['provider_privacy'] ) ? $snapshot['provider_privacy'] : array() );
    update_post_meta( $post_id, '_bh_consent_version', sanitize_text_field( $snapshot['version'] ) );
    update_post_meta( $post_id, '_bh_consent_captured_at', sanitize_text_field( $snapshot['captured_at'] ) );
    update_post_meta( $post_id, '_bh_consent_status', ! empty( $snapshot['accuracy_declaration'] ) ? 'accepted' : 'missing' );
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
    if ( ! is_user_logged_in() || 'preferences' !== ( $_POST['bh_stage2_action'] ?? '' ) ) return '';
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
    $hidden_days = array();
    $selected_hidden = isset( $_POST['calendar_hidden_days'] ) ? (array) $_POST['calendar_hidden_days'] : array();
    foreach ( $selected_hidden as $day ) {
        $day = sanitize_key( wp_unslash( $day ) );
        if ( in_array( $day, $allowed_days, true ) ) $hidden_days[] = $day;
    }
    $hidden_days = array_values( array_unique( $hidden_days ) );
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
    update_user_meta( $uid, 'bubbahub_privacy_share_contact_leaders', ! empty( $_POST['privacy_share_contact_leaders'] ) ? '1' : '0' );
    update_user_meta( $uid, 'bubbahub_privacy_share_child_yob_leaders', ! empty( $_POST['privacy_share_child_yob_leaders'] ) ? '1' : '0' );
    update_user_meta( $uid, 'bubbahub_privacy_personalised_recommendations', ! empty( $_POST['privacy_personalised_recommendations'] ) ? '1' : '0' );
    update_user_meta( $uid, 'bubbahub_privacy_anonymous_analytics', ! empty( $_POST['privacy_anonymous_analytics'] ) ? '1' : '0' );
    update_user_meta( $uid, 'bubbahub_privacy_leader_messages', ! empty( $_POST['privacy_leader_messages'] ) ? '1' : '0' );

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
    
    .bh-consent-card{overflow:hidden}.bh-consent-section{margin-top:18px;padding:18px;border:1px solid #e2ebe7;border-radius:18px;background:#fbfdfc}.bh-consent-section .bh-profile-card-heading{margin-bottom:12px}.bh-consent-section h4{margin:0;color:#28483f;font-size:15px}.bh-consent-section .bh-profile-grid{margin-top:0}.bh-consent-notice{margin-top:18px;padding:14px 16px;border-radius:14px;background:#eef7f2;border:1px solid #d5e9df;color:#48665d;font-size:12px;line-height:1.55}.bh-consent-notice strong{color:#294a40}@media(max-width:600px){.bh-consent-section{padding:14px;border-radius:15px}.bh-consent-section .bh-profile-grid.two{grid-template-columns:1fr}.bh-consent-section .bh-stage2-check{align-items:flex-start}.bh-consent-section .bh-stage2-check span{line-height:1.45}}
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
        <div class="bh-consent-section"><div class="bh-profile-card-heading"><h4>1. Parent / guardian details</h4><span>Booking contact</span></div>
          <div class="bh-profile-grid two">
            <label><span>Relationship to child / participant</span><input name="relationship" value="<?php echo esc_attr(bubbahub_stage2_user_meta('bubbahub_consent_relationship')); ?>" placeholder="Parent, guardian, carer..."></label>
            <label><span>Child profile for this consent</span><select name="child_profile_id">
              <option value="">Select a child profile</option>
              <?php foreach ( bubbahub_stage2_children() as $child ) :
                  $child_name = function_exists('bubbahub_profile_field') ? bubbahub_profile_field($child->ID, 'child_name', $child->post_title) : $child->post_title;
                  $selected_child = absint( bubbahub_stage2_user_meta('bubbahub_consent_child_profile_id') );
              ?>
                <option value="<?php echo absint($child->ID); ?>" <?php selected($selected_child, $child->ID); ?>><?php echo esc_html($child_name); ?></option>
              <?php endforeach; ?>
            </select><small class="bh-muted">The class provider will receive the child's year of birth only, not the full date of birth.</small></label>
            <label><span>Participant / child name</span><input name="participant_name" value="<?php echo esc_attr(bubbahub_stage2_user_meta('bubbahub_consent_participant_name')); ?>"></label>
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
        <div class="bh-consent-section"><div class="bh-profile-card-heading"><h4>4. Booking, contact & information sharing</h4><span>Required</span></div>
          <label class="bh-stage2-check"><input type="checkbox" name="profile_shared_ack" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_profile_shared_ack'),'1'); ?> required><span><strong>I confirm the information on my account is accurate and may be shared with the relevant class provider where needed for attendance, administration and safety.</strong></span></label>
          <label class="bh-stage2-check"><input type="checkbox" name="class_leader_contact" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_class_leader_contact'),'1'); ?> required><span><strong>I consent to the class leader / provider contacting me about my booking, changes, attendance and urgent matters.</strong></span></label>
          <label class="bh-stage2-check"><input type="checkbox" name="payment_agreement" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_payment_agreement'),'1'); ?> required><span><strong>I agree to the booking price, payment, refund and cancellation terms shown at the time of booking.</strong></span></label>
          <label class="bh-stage2-check"><input type="checkbox" name="booking_terms_ack" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_booking_terms_ack'),'1'); ?> required><span><strong>I understand that a booking is subject to the class provider's published rules, capacity, timetable and any session-specific requirements.</strong></span></label>
          <label class="bh-stage2-check"><input type="checkbox" name="data_processing_ack" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_data_processing_ack'),'1'); ?> required><span><strong>I understand that booking information will be processed and retained as needed to administer the booking, payment, safety and related communications.</strong></span></label>
        </div>
        <div class="bh-consent-section"><div class="bh-profile-card-heading"><h4>5. Safety & responsibility</h4><span>Required</span></div>
          <label class="bh-stage2-check"><input type="checkbox" name="liability_ack" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_liability_ack'),'1'); ?> required><span><strong>I acknowledge that I am responsible for providing accurate safety information and following the class provider's instructions and safety requirements.</strong></span></label>
          <label class="bh-stage2-check"><input type="checkbox" name="accuracy_declaration" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_accuracy_declaration'),'1'); ?> required><span><strong>I confirm that I am authorised to give this consent for the participant named above and that the information supplied is accurate to the best of my knowledge.</strong></span></label>
        </div>
        <div class="bh-consent-section"><div class="bh-profile-card-heading"><h4>6. Photo & media permissions</h4><span>Optional</span></div>
          <?php foreach(array('media_social'=>'Social media','media_promotional'=>'Promotional materials','media_head_office'=>'Bubba Hub / head office use') as $key=>$label): ?>
            <label class="bh-stage2-check"><input type="checkbox" name="<?php echo esc_attr($key); ?>" value="1" <?php checked(bubbahub_stage2_user_meta('bubbahub_consent_'.$key),'1'); ?>><span><strong>I consent to the participant being included in <?php echo esc_html(strtolower($label)); ?> where applicable.</strong></span></label>
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
    <?php return ob_get_clean();
}
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
              <?php foreach($days as $v=>$label): ?><label><input type="checkbox" name="calendar_hidden_days[]" value="<?php echo esc_attr($v); ?>" <?php checked(in_array($v,$hidden_days,true)); ?>><span><?php echo esc_html($label); ?></span></label><?php endforeach; ?>
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
      <form method="post" class="bh-stage2-form">
        <?php wp_nonce_field('bh_stage2_settings','bh_stage2_nonce'); ?><input type="hidden" name="bh_stage2_action" value="privacy">
        <div class="bh-privacy-section">
          <div class="bh-privacy-section-heading"><span>👩‍🏫</span><div><h4>Sharing with class leaders</h4><p>Control optional information sharing with group and class leaders.</p></div></div>
          <?php
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
    elseif ( 'preferences' === $section || 'interests' === $section || 'family_needs' === $section ) $content = bubbahub_stage2_render_preferences();
    elseif ( 'calendar' === $section ) $content = bubbahub_stage2_render_calendar_settings();
    elseif ( 'privacy' === $section ) $content = bubbahub_stage2_render_privacy();
    elseif ( 'notification_test' === $section ) $content = bubbahub_stage2_render_notification_test();
    elseif ( 'pro' === $section ) $content = bubbahub_stage2_render_pro();
    elseif ( 'payments' === $section ) $content = bubbahub_stage2_render_payments();
    else $content = '';

    if ( $content ) {
        $back = add_query_arg( 'bh_account_settings', '1', remove_query_arg( 'bh_settings_section' ) );
        ob_start(); ?>
        <div id="bh-account-settings-screen" class="bh-profile-shell"><div class="bh-profile-header dark"><div><span class="bh-profile-kicker">ACCOUNT SETTINGS</span><h1>Your Account Settings</h1><p>Manage your Bubba Hub profile, children, membership, interests, notifications and payments.</p></div><a class="bh-profile-back light" href="<?php echo esc_url($back); ?>">‹ Back to settings</a></div><?php if($message): ?><div class="bh-profile-success">✓ <?php echo esc_html($message); ?></div><?php endif; ?><?php echo $content; ?></div><?php return ob_get_clean();
    }

    ob_start(); ?>
    <div id="bh-account-settings-screen" class="bh-profile-shell">
        <div class="bh-profile-header dark"><div><span class="bh-profile-kicker">ACCOUNT SETTINGS</span><h1>Your Account Settings</h1><p>Manage your Bubba Hub profile, children, membership and payment details in one place.</p><?php if($is_pro): ?><div class="bh-pro-pill">⭐ Pro Member Active</div><?php endif; ?></div><a class="bh-profile-back light" href="<?php echo esc_url(remove_query_arg('bh_account_settings')); ?>">‹ Back to My Hub</a></div>
        <?php if($message): ?><div class="bh-profile-success">✓ <?php echo esc_html($message); ?></div><?php endif; ?>
        <div id="bh-account-settings-list" class="bh-settings-list" role="navigation" aria-label="Account settings">
            <a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'profile'))); ?>" style="display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;min-width:0!important;min-height:78px!important;height:auto!important;margin:0!important;padding:16px 18px!important;box-sizing:border-box!important;overflow:hidden!important;position:static!important;float:none!important;">
                <span class="bh-account-settings-icon" aria-hidden="true">👤</span><span class="bh-account-settings-content"><h3>Edit my profile</h3><p>Personal details, contact information, addresses and search radius.</p></span><span class="bh-account-settings-arrow" aria-hidden="true">→</span>
            </a>
            <a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'pro'))); ?>" style="display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;min-width:0!important;min-height:78px!important;height:auto!important;margin:0!important;padding:16px 18px!important;box-sizing:border-box!important;overflow:hidden!important;position:static!important;float:none!important;"><span class="bh-account-settings-icon" aria-hidden="true">⭐</span><span class="bh-account-settings-content"><h3>Manage my Pro Account</h3><p><?php echo $is_pro ? 'Manage your active membership and billing.' : 'View Pro options and membership information.'; ?></p></span><span class="bh-account-settings-arrow" aria-hidden="true">→</span></a>
            <a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'preferences'))); ?>" style="display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;min-width:0!important;min-height:78px!important;height:auto!important;margin:0!important;padding:16px 18px!important;box-sizing:border-box!important;overflow:hidden!important;position:static!important;float:none!important;"><span class="bh-account-settings-icon" aria-hidden="true">✨</span><span class="bh-account-settings-content"><h3>My Interests & Groups</h3><p>Choose interests from tags already used by Bubba Hub groups.</p></span><span class="bh-account-settings-arrow" aria-hidden="true">→</span></a>
            
             <a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'family_needs'))); ?>" style="display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;min-width:0!important;min-height:78px!important;height:auto!important;margin:0!important;padding:16px 18px!important;box-sizing:border-box!important;overflow:hidden!important;position:static!important;float:none!important;"><span class="bh-account-settings-icon" aria-hidden="true">👨‍👩‍👧</span><span class="bh-account-settings-content"><h3>My Family &amp; Search Preferences</h3><p>Set family needs, preferred locations, days, times, age, session length, price and free-activity preferences.</p></span><span class="bh-account-settings-arrow" aria-hidden="true">→</span></a>
            <a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'calendar'))); ?>" style="display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;min-width:0!important;min-height:78px!important;height:auto!important;margin:0!important;padding:16px 18px!important;box-sizing:border-box!important;overflow:hidden!important;position:static!important;float:none!important;"><span class="bh-account-settings-icon" aria-hidden="true">🗓️</span><span class="bh-account-settings-content"><h3>Calendar Settings</h3><p>Choose your default view, visible days, time of day, free/paid activities, nap schedule and reminder settings.</p></span><span class="bh-account-settings-arrow" aria-hidden="true">→</span></a>
            <a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'notification_test'))); ?>" style="display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;min-width:0!important;min-height:78px!important;height:auto!important;margin:0!important;padding:16px 18px!important;box-sizing:border-box!important;overflow:hidden!important;position:static!important;float:none!important;"><span class="bh-account-settings-icon" aria-hidden="true">🧪</span><span class="bh-account-settings-content"><h3>Notification Test</h3><p>Send a test email to check your account notification delivery.</p></span><span class="bh-account-settings-arrow" aria-hidden="true">→</span></a>
            <a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'privacy'))); ?>" style="display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;min-width:0!important;min-height:78px!important;height:auto!important;margin:0!important;padding:16px 18px!important;box-sizing:border-box!important;overflow:hidden!important;position:static!important;float:none!important;"><span class="bh-account-settings-icon" aria-hidden="true">🔐</span><span class="bh-account-settings-content"><h3>Privacy & Security</h3><p>Open account security and submit data or deletion requests.</p></span><span class="bh-account-settings-arrow" aria-hidden="true">→</span></a>
            <a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'notifications'))); ?>" style="display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;min-width:0!important;min-height:78px!important;height:auto!important;margin:0!important;padding:16px 18px!important;box-sizing:border-box!important;overflow:hidden!important;position:static!important;float:none!important;"><span class="bh-account-settings-icon" aria-hidden="true">🔔</span><span class="bh-account-settings-content"><h3>Notification preferences</h3><p>Manage every optional notification type, including new groups, updates, suggestions, bookings and planner alerts.</p></span><span class="bh-account-settings-arrow" aria-hidden="true">→</span></a>
            <a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'consent'))); ?>" style="display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;min-width:0!important;min-height:78px!important;height:auto!important;margin:0!important;padding:16px 18px!important;box-sizing:border-box!important;overflow:hidden!important;position:static!important;float:none!important;"><span class="bh-account-settings-icon" aria-hidden="true">🛡️</span><span class="bh-account-settings-content"><h3>Class Consent & Safety</h3><p>Manage safety information, contact consent and media permissions.</p></span><span class="bh-account-settings-arrow" aria-hidden="true">→</span></a>
            <a class="bh-account-settings-item" href="<?php echo esc_url(add_query_arg(array('bh_account_settings'=>1,'bh_settings_section'=>'payments'))); ?>" style="display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:nowrap!important;width:100%!important;min-width:0!important;min-height:78px!important;height:auto!important;margin:0!important;padding:16px 18px!important;box-sizing:border-box!important;overflow:hidden!important;position:static!important;float:none!important;"><span class="bh-account-settings-icon" aria-hidden="true">💳</span><span class="bh-account-settings-content"><h3>Payment methods</h3><p>Open the connected GetPaid / Stripe payment area securely.</p></span><span class="bh-account-settings-arrow" aria-hidden="true">→</span></a>
        </div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Connected account</h3><span>Ultimate Member / WordPress</span></div><div class="bh-connected-row"><div><strong><?php echo esc_html($user->display_name); ?></strong><small><?php echo esc_html($user->user_email); ?></small></div><a href="<?php echo esc_url($um_url); ?>">Open Ultimate Member →</a></div></div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Child profiles</h3><a href="<?php echo esc_url(add_query_arg('bh_add_child','1')); ?>">＋ Manage children</a></div><?php if($children): ?><div class="bh-account-children-list"><?php foreach($children as $child): $name=function_exists('bubbahub_profile_field')?bubbahub_profile_field($child->ID,'child_name',$child->post_title):$child->post_title; $status=function_exists('bubbahub_profile_field')?bubbahub_profile_field($child->ID,'child_status','born'):'born'; ?><div><span class="bh-mini-avatar"><?php echo esc_html(strtoupper(substr((string)$name,0,1))); ?></span><div><strong><?php echo esc_html($name); ?></strong><small><?php echo 'expecting'===$status?'Expecting':'Child profile'; ?></small></div><a href="<?php echo esc_url(add_query_arg(array('bh_add_child'=>1,'child_id'=>$child->ID))); ?>">Edit</a></div><?php endforeach; ?></div><?php else: ?><p class="bh-muted">No child profiles have been added yet.</p><?php endif; ?></div>
        <div class="bh-profile-card"><div class="bh-profile-card-heading"><h3>Payment account</h3><span>GetPaid connection</span></div><div class="bh-connected-row"><div><strong><?php echo $is_pro ? 'Pro / payment account available' : 'Payment history and invoices'; ?></strong><small>Use the connected payment area for invoices, subscriptions and secure payment details.</small></div><a href="<?php echo esc_url($payment_url); ?>">Open payments →</a></div></div>
    </div>
    <?php return ob_get_clean();
}