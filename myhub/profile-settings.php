<?php
/**
 * BubbaHub My Hub - Account Settings + Child Profiles.
 *
 * Stage 1: uses the existing bh_child CPT and ACF field names already consumed
 * by the My Hub dashboard. No second child data store is created.
 *
 * Shortcode: [bubbahub_account_settings]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_profile_settings_register', 35 );
add_action( 'wp_enqueue_scripts', 'bubbahub_profile_settings_assets' );
add_action( 'init', 'bubbahub_profile_handle_child_post', 1 );

function bubbahub_profile_settings_register() {
    add_shortcode( 'bubbahub_account_settings', 'bubbahub_account_settings_shortcode' );
}

function bubbahub_profile_settings_assets() {
    if ( ! defined( 'BUBBAHUB_MYHUB_URL' ) ) return;
    wp_register_style( 'bubbahub-profile-settings', BUBBAHUB_MYHUB_URL . 'profile-settings.css', array(), defined( 'BUBBAHUB_MYHUB_VERSION' ) ? BUBBAHUB_MYHUB_VERSION : '1.3.0' );
    wp_register_script( 'bubbahub-profile-settings', BUBBAHUB_MYHUB_URL . 'profile-settings.js', array(), defined( 'BUBBAHUB_MYHUB_VERSION' ) ? BUBBAHUB_MYHUB_VERSION : '1.3.0', true );
}

/* -------------------------------------------------------------------------
 * ACF helpers
 * ---------------------------------------------------------------------- */
function bubbahub_profile_field( $post_id, $field, $default = '' ) {
    $post_id = absint( $post_id );
    if ( ! $post_id || ! $field ) return $default;

    if ( function_exists( 'get_field' ) ) {
        $value = get_field( $field, $post_id, false );
        if ( '' !== $value && null !== $value && false !== $value ) return $value;
    }

    $value = get_post_meta( $post_id, $field, true );
    return ( '' !== $value && null !== $value && false !== $value ) ? $value : $default;
}

function bubbahub_profile_update_field( $post_id, $field, $value ) {
    if ( function_exists( 'update_field' ) ) {
        update_field( $field, $value, $post_id );
    } else {
        update_post_meta( $post_id, $field, $value );
    }
}

function bubbahub_profile_delete_field( $post_id, $field ) {
    if ( function_exists( 'delete_field' ) ) {
        delete_field( $field, $post_id );
    } else {
        delete_post_meta( $post_id, $field );
    }
}

function bubbahub_profile_child_owned( $child_id ) {
    $child = get_post( absint( $child_id ) );
    return $child && 'bh_child' === $child->post_type && (int) $child->post_author === get_current_user_id();
}

function bubbahub_profile_date_value( $value ) {
    if ( empty( $value ) ) return '';
    $value = sanitize_text_field( $value );
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) return $value;
    if ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m ) ) return $m[3] . '-' . $m[2] . '-' . $m[1];
    return '';
}

function bubbahub_profile_age_group( $dob ) {
    $dob = bubbahub_profile_date_value( $dob );
    if ( ! $dob ) return '';
    $birth = DateTime::createFromFormat( 'Y-m-d', $dob );
    if ( ! $birth || $birth->format( 'Y-m-d' ) !== $dob ) return '';
    $today = new DateTime( 'today' );
    if ( $birth > $today ) return '';
    $age = $birth->diff( $today );
    $months = ( (int) $age->y * 12 ) + (int) $age->m;

    if ( $months < 3 ) return '0-3';
    if ( $months < 6 ) return '3-6';
    if ( $months < 9 ) return '6-9';
    if ( $months < 12 ) return '9-12';
    if ( $months < 24 ) return '1-3';
    if ( $months < 36 ) return '2-4';
    if ( $months < 60 ) return '3-5';
    return '5-plus';
}

function bubbahub_profile_um_url() {
    if ( function_exists( 'um_get_core_page' ) ) {
        $url = um_get_core_page( 'account' );
        if ( $url ) return esc_url_raw( $url );
    }
    return home_url( '/account/' );
}

function bubbahub_profile_payment_url() {
    $url = apply_filters( 'bubbahub_getpaid_account_url', '' );
    return $url ? esc_url_raw( $url ) : home_url( '/my-bookings/' );
}

/* -------------------------------------------------------------------------
 * Front-end child form POST bootstrap
 * ---------------------------------------------------------------------- */
function bubbahub_profile_handle_child_post() {
    if ( ! is_user_logged_in() || empty( $_POST['bh_profile_child_action'] ) ) return;
    $result = bubbahub_profile_handle_child_action();
    if ( empty( $result ) ) return;

    $return_to = isset( $_POST['bh_profile_return_to'] ) ? esc_url_raw( wp_unslash( $_POST['bh_profile_return_to'] ) ) : wp_get_referer();
    if ( ! $return_to ) $return_to = home_url( '/my-hub/' );

    $return_to = remove_query_arg( array( 'child_saved', 'child_error' ), $return_to );
    if ( ! empty( $result['success'] ) ) {
        $return_to = add_query_arg( 'child_saved', '1', $return_to );
    } elseif ( ! empty( $result['error'] ) ) {
        $return_to = add_query_arg( 'child_error', rawurlencode( $result['error'] ), $return_to );
    }
    wp_safe_redirect( $return_to );
    exit;
}

/* -------------------------------------------------------------------------
 * Child save/delete handler
 * ---------------------------------------------------------------------- */
function bubbahub_profile_handle_child_action() {
    if ( ! is_user_logged_in() ) return array();
    if ( empty( $_POST['bh_profile_child_action'] ) ) return array();

    $action = sanitize_key( wp_unslash( $_POST['bh_profile_child_action'] ) );
    $nonce  = isset( $_POST['bh_profile_child_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_profile_child_nonce'] ) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'bh_profile_child_save' ) ) {
        return array( 'error' => 'Security check failed. Please try again.' );
    }

    $uid      = get_current_user_id();
    $child_id = isset( $_POST['child_id'] ) ? absint( $_POST['child_id'] ) : 0;

    if ( 'delete' === $action ) {
        if ( ! $child_id || ! bubbahub_profile_child_owned( $child_id ) ) {
            return array( 'error' => 'That child profile could not be found.' );
        }
        wp_trash_post( $child_id );
        return array( 'success' => 'Child profile moved to the bin.' );
    }

    if ( 'save' !== $action ) return array();

    $name     = isset( $_POST['child_name'] ) ? sanitize_text_field( wp_unslash( $_POST['child_name'] ) ) : '';
    $nickname = isset( $_POST['child_nickname'] ) ? sanitize_text_field( wp_unslash( $_POST['child_nickname'] ) ) : '';
    $status   = isset( $_POST['child_status'] ) ? sanitize_key( wp_unslash( $_POST['child_status'] ) ) : 'born';
    $dob      = bubbahub_profile_date_value( isset( $_POST['child_date_of_birth'] ) ? wp_unslash( $_POST['child_date_of_birth'] ) : '' );
    $due      = bubbahub_profile_date_value( isset( $_POST['child_due_date'] ) ? wp_unslash( $_POST['child_due_date'] ) : '' );
    $avatar   = isset( $_POST['avatar_url'] ) ? esc_url_raw( wp_unslash( $_POST['avatar_url'] ) ) : '';

    if ( ! $name ) return array( 'error' => 'Please enter the child\'s name.' );
    if ( ! in_array( $status, array( 'born', 'expecting' ), true ) ) $status = 'born';

    if ( 'expecting' === $status ) {
        $dob = '';
    } else {
        $due = '';
    }

    if ( $child_id ) {
        if ( ! bubbahub_profile_child_owned( $child_id ) ) {
            return array( 'error' => 'You do not have permission to edit that child profile.' );
        }
        $result = wp_update_post( array(
            'ID'         => $child_id,
            'post_title' => $name,
        ), true );
    } else {
        $result = wp_insert_post( array(
            'post_type'   => 'bh_child',
            'post_status' => 'publish',
            'post_author' => $uid,
            'post_title'  => $name,
        ), true );
    }

    if ( is_wp_error( $result ) ) {
        return array( 'error' => 'The child profile could not be saved. ' . $result->get_error_message() );
    }

    $child_id = absint( $result );

    /* Existing ACF structure used by My Hub v2. */
    bubbahub_profile_update_field( $child_id, 'child_name', $name );
    bubbahub_profile_update_field( $child_id, 'child_nickname', $nickname );
    bubbahub_profile_update_field( $child_id, 'child_status', $status );
    bubbahub_profile_update_field( $child_id, 'child_date_of_birth', $dob );
    bubbahub_profile_update_field( $child_id, 'child_due_date', $due );
    bubbahub_profile_update_field( $child_id, 'avatar_url', $avatar );

    /* Keep the existing age-group logic used by family suggestions in sync. */
    $age_group = bubbahub_profile_age_group( $dob );
    bubbahub_profile_update_field( $child_id, 'age_group', $age_group );

    /* Existing school tracker fields are preserved; only update fields supplied by this form. */
    foreach ( array( 'school_name', 'school_application_status', 'school_application_deadline', 'school_year', 'ofsted_rating' ) as $school_field ) {
        if ( isset( $_POST[ $school_field ] ) ) {
            $value = sanitize_text_field( wp_unslash( $_POST[ $school_field ] ) );
            if ( false !== strpos( $school_field, 'deadline' ) ) $value = bubbahub_profile_date_value( $value );
            bubbahub_profile_update_field( $child_id, $school_field, $value );
        }
    }

    $nap = array();
    foreach ( array( 'Mon','Tue','Wed','Thu','Fri','Sat','Sun' ) as $day ) {
        $row = isset( $_POST['nap'][ $day ] ) && is_array( $_POST['nap'][ $day ] ) ? $_POST['nap'][ $day ] : array();
        $nap[ $day ] = array(
            'enabled' => ! empty( $row['enabled'] ),
            'napStart' => isset( $row['start'] ) ? sanitize_text_field( wp_unslash( $row['start'] ) ) : '13:00',
            'napEnd' => isset( $row['end'] ) ? sanitize_text_field( wp_unslash( $row['end'] ) ) : '15:00',
        );
    }
    bubbahub_profile_update_field( $child_id, 'nap_schedule', $nap );

    return array( 'success' => 'Child profile saved successfully.', 'child_id' => $child_id );
}

/* -------------------------------------------------------------------------
 * Child editor
 * ---------------------------------------------------------------------- */
function bubbahub_profile_child_form( $child_id = 0 ) {
    if ( ! is_user_logged_in() ) return '<p>Please log in to manage your child profiles.</p>';

    $child_id = absint( $child_id );
    if ( $child_id && ! bubbahub_profile_child_owned( $child_id ) ) {
        return '<div class="bh-profile-error">That child profile could not be found.</div>';
    }

    $message = bubbahub_profile_handle_child_action();
    if ( ! empty( $message['child_id'] ) ) $child_id = absint( $message['child_id'] );

    $editing = $child_id && bubbahub_profile_child_owned( $child_id );
    $get = function( $field, $default = '' ) use ( $child_id ) {
        return $child_id ? bubbahub_profile_field( $child_id, $field, $default ) : $default;
    };

    $name = $get( 'child_name', $child_id ? get_the_title( $child_id ) : '' );
    $nickname = $get( 'child_nickname' );
    $status = $get( 'child_status', 'born' );
    $dob = $get( 'child_date_of_birth' );
    $due = $get( 'child_due_date' );
    $avatar = $get( 'avatar_url' );
    $nap = $get( 'nap_schedule', array() );

    $dob = bubbahub_profile_date_value( $dob );
    $due = bubbahub_profile_date_value( $due );

    ob_start(); ?>
    <div class="bh-profile-shell">
        <div class="bh-profile-header">
            <div>
                <span class="bh-profile-kicker">FAMILY PROFILE</span>
                <h2><?php echo $editing ? 'Edit Child Profile' : 'Add a Child'; ?></h2>
                <p>This information is saved to the child profile used by My Hub and group suggestions.</p>
            </div>
            <a class="bh-profile-back" href="<?php echo esc_url( remove_query_arg( array( 'bh_add_child', 'child_id' ) ) ); ?>">‹ Back to Account Settings</a>
        </div>

        <?php if ( ! empty( $message['success'] ) ) : ?><div class="bh-profile-success">✓ <?php echo esc_html( $message['success'] ); ?></div><?php endif; ?>
        <?php if ( ! empty( $message['error'] ) ) : ?><div class="bh-profile-error">⚠ <?php echo esc_html( $message['error'] ); ?></div><?php endif; ?>

        <form method="post" class="bh-profile-form">
            <?php wp_nonce_field( 'bh_profile_child_save', 'bh_profile_child_nonce' ); ?>
            <input type="hidden" name="bh_profile_child_action" value="save">
            <input type="hidden" name="child_id" value="<?php echo esc_attr( $child_id ); ?>">
            <input type="hidden" name="bh_profile_return_to" value="<?php echo esc_url( wp_unslash( wp_get_referer() ? wp_get_referer() : home_url( '/my-hub/' ) ) ); ?>">

            <div class="bh-profile-card">
                <div class="bh-profile-card-heading"><h3>About your child</h3><span>Core profile</span></div>
                <div class="bh-profile-grid two">
                    <label><span>Name</span><input name="child_name" value="<?php echo esc_attr( $name ); ?>" required></label>
                    <label><span>Nickname</span><input name="child_nickname" value="<?php echo esc_attr( $nickname ); ?>" placeholder="Optional"></label>
                    <label><span>Profile photo URL</span><input name="avatar_url" type="url" value="<?php echo esc_attr( $avatar ); ?>" placeholder="Optional"></label>
                    <label><span>Profile type</span><select name="child_status"><option value="born" <?php selected( $status, 'born' ); ?>>Child</option><option value="expecting" <?php selected( $status, 'expecting' ); ?>>Expecting / Pregnancy</option></select></label>
                    <label><span>Date of birth</span><input name="child_date_of_birth" type="date" value="<?php echo esc_attr( $dob ); ?>"></label>
                    <label><span>Expected due date</span><input name="child_due_date" type="date" value="<?php echo esc_attr( $due ); ?>"></label>
                </div>
            </div>

            <div class="bh-profile-card">
                <div class="bh-profile-card-heading"><h3>Nap schedule</h3><span>Saved per day</span></div>
                <div class="bh-nap-grid">
                    <?php foreach ( array( 'Mon','Tue','Wed','Thu','Fri','Sat','Sun' ) as $day ) : $row = isset( $nap[ $day ] ) && is_array( $nap[ $day ] ) ? $nap[ $day ] : array(); ?>
                        <div class="bh-nap-row">
                            <strong><?php echo esc_html( $day ); ?></strong>
                            <label class="bh-switch"><input type="checkbox" name="nap[<?php echo esc_attr( $day ); ?>][enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?>><span></span></label>
                            <input type="time" name="nap[<?php echo esc_attr( $day ); ?>][start]" value="<?php echo esc_attr( isset( $row['napStart'] ) ? $row['napStart'] : '13:00' ); ?>">
                            <span>to</span>
                            <input type="time" name="nap[<?php echo esc_attr( $day ); ?>][end]" value="<?php echo esc_attr( isset( $row['napEnd'] ) ? $row['napEnd'] : '15:00' ); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bh-profile-actions">
                <button type="submit">Save child profile</button>
                <?php if ( $editing ) : ?>
                    <button type="submit" class="bh-profile-delete" name="bh_profile_child_action" value="delete" onclick="return confirm('Move this child profile to the bin?');">Delete child</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Account settings page
 * ---------------------------------------------------------------------- */
function bubbahub_account_settings_shortcode() {
    if ( ! is_user_logged_in() ) return '<p>Please log in to manage your account settings.</p>';

    wp_enqueue_style( 'bubbahub-profile-settings' );
    wp_enqueue_script( 'bubbahub-profile-settings' );

    if ( isset( $_GET['bh_add_child'] ) ) {
        return bubbahub_profile_child_form( isset( $_GET['child_id'] ) ? absint( $_GET['child_id'] ) : 0 );
    }

    $user = wp_get_current_user();
    $um_url = bubbahub_profile_um_url();
    $payment_url = bubbahub_profile_payment_url();
    $children = get_posts( array(
        'post_type' => 'bh_child',
        'post_status' => 'publish',
        'author' => get_current_user_id(),
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'ASC',
        'no_found_rows' => true,
    ) );

    ob_start(); ?>
    <div class="bh-profile-shell">
        <div class="bh-profile-header dark">
            <div><span class="bh-profile-kicker">ACCOUNT SETTINGS</span><h1>Your Account Settings</h1><p>Manage your Bubba Hub profile, children, membership and payment details in one place.</p></div>
            <a class="bh-profile-back light" href="<?php echo esc_url( remove_query_arg( 'bh_account_settings' ) ); ?>">‹ Back to My Hub</a>
        </div>

        <div class="bh-account-grid">
            <a class="bh-account-card" href="<?php echo esc_url( $um_url ); ?>"><span class="bh-account-icon">👤</span><div><h3>Ultimate Member Account</h3><p>Update your name, email, password, privacy and account details using Ultimate Member.</p></div><span>→</span></a>
            <a class="bh-account-card" href="<?php echo esc_url( $payment_url ); ?>"><span class="bh-account-icon">💳</span><div><h3>Payments & invoices</h3><p>View your Bubba Hub bookings and payment activity.</p></div><span>→</span></a>
            <a class="bh-account-card" href="<?php echo esc_url( add_query_arg( 'bh_add_child', '1' ) ); ?>"><span class="bh-account-icon">👶</span><div><h3>Child profiles</h3><p><?php echo esc_html( count( $children ) ); ?> child profile<?php echo 1 === count( $children ) ? '' : 's'; ?> saved. Add, edit or update family information.</p></div><span>→</span></a>
        </div>

        <div class="bh-profile-card">
            <div class="bh-profile-card-heading"><h3>Connected account</h3><span>WordPress / Ultimate Member</span></div>
            <div class="bh-connected-row"><div><strong><?php echo esc_html( $user->display_name ); ?></strong><small><?php echo esc_html( $user->user_email ); ?></small></div><a href="<?php echo esc_url( $um_url ); ?>">Open Ultimate Member →</a></div>
        </div>

        <?php if ( function_exists( 'bubbahub_notification_preferences_shortcode' ) ) : ?>
            <?php echo bubbahub_notification_preferences_shortcode(); ?>
            <?php if ( function_exists( 'bubbahub_notification_feed_shortcode' ) ) echo bubbahub_notification_feed_shortcode(); ?>
        <?php endif; ?>

        <div class="bh-profile-card">
            <div class="bh-profile-card-heading"><h3>Child profiles</h3><a href="<?php echo esc_url( add_query_arg( 'bh_add_child', '1' ) ); ?>">＋ Add child</a></div>
            <?php if ( $children ) : ?>
                <div class="bh-account-children-list">
                    <?php foreach ( $children as $child ) :
                        $name = bubbahub_profile_field( $child->ID, 'child_name', $child->post_title );
                        $status = bubbahub_profile_field( $child->ID, 'child_status', 'born' );
                        $dob = bubbahub_profile_field( $child->ID, 'child_date_of_birth' );
                        $due = bubbahub_profile_field( $child->ID, 'child_due_date' );
                        ?>
                        <div><span class="bh-mini-avatar"><?php echo esc_html( strtoupper( substr( (string) $name, 0, 1 ) ) ); ?></span><div><strong><?php echo esc_html( $name ); ?></strong><small><?php echo 'expecting' === $status ? 'Expecting' . ( $due ? ' · Due ' . wp_date( 'j M Y', strtotime( $due ) ) : '' ) : ( $dob ? 'Born ' . wp_date( 'j M Y', strtotime( $dob ) ) : 'Child profile' ); ?></small></div><a href="<?php echo esc_url( add_query_arg( array( 'bh_add_child' => 1, 'child_id' => $child->ID ) ) ); ?>">Edit</a></div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <p class="bh-muted">No child profiles have been added yet.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}
