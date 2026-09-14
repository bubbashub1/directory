<?php
/**
 * BubbaHub My Hub
 *
 * Front-end family dashboard for logged-in parents.
 * Shortcode: [bubbahub_my_hub]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BUBBAHUB_MYHUB_VERSION' ) ) define( 'BUBBAHUB_MYHUB_VERSION', '1.0.0' );
if ( ! defined( 'BUBBAHUB_MYHUB_PATH' ) ) define( 'BUBBAHUB_MYHUB_PATH', plugin_dir_path( __FILE__ ) );
if ( ! defined( 'BUBBAHUB_MYHUB_URL' ) ) define( 'BUBBAHUB_MYHUB_URL', plugin_dir_url( __FILE__ ) );

add_action( 'init', 'bubbahub_myhub_register_child_post_type' );
add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_register_assets' );
add_shortcode( 'bubbahub_my_hub', 'bubbahub_myhub_shortcode' );
add_action( 'init', 'bubbahub_myhub_handle_child_form' );

function bubbahub_myhub_register_child_post_type() {
    register_post_type( 'bh_child', array(
        'labels' => array(
            'name' => 'Family Children',
            'singular_name' => 'Family Child',
            'add_new_item' => 'Add Child',
            'edit_item' => 'Edit Child',
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-groups',
        'supports' => array( 'title', 'author' ),
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ) );
}

function bubbahub_myhub_register_assets() {
    wp_register_style( 'bubbahub-myhub', BUBBAHUB_MYHUB_URL . 'myhub.css', array(), BUBBAHUB_MYHUB_VERSION );
}

function bubbahub_myhub_field( $post_id, $field, $default = '' ) {
    if ( function_exists( 'get_field' ) ) {
        $value = get_field( $field, $post_id );
        if ( $value !== null && $value !== false && $value !== '' ) return $value;
    }
    $value = get_post_meta( $post_id, $field, true );
    return ( $value !== '' && $value !== false ) ? $value : $default;
}

function bubbahub_myhub_child_query() {
    if ( ! is_user_logged_in() ) return new WP_Query();
    return new WP_Query( array(
        'post_type' => 'bh_child',
        'post_status' => 'publish',
        'author' => get_current_user_id(),
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'ASC',
        'no_found_rows' => true,
    ) );
}

function bubbahub_myhub_age( $dob ) {
    if ( ! $dob ) return '';
    $birth = DateTime::createFromFormat( 'Y-m-d', sanitize_text_field( $dob ) );
    if ( ! $birth ) return '';
    $today = new DateTime( 'today' );
    if ( $birth > $today ) return '';
    $age = $birth->diff( $today );
    $parts = array();
    if ( $age->y ) $parts[] = $age->y . 'y';
    if ( $age->m || ! $parts ) $parts[] = $age->m . 'm';
    return implode( ' ', $parts );
}

function bubbahub_myhub_countdown( $date ) {
    if ( ! $date ) return '';
    $deadline = DateTime::createFromFormat( 'Y-m-d', sanitize_text_field( $date ) );
    if ( ! $deadline ) return '';
    $today = new DateTime( 'today' );
    if ( $deadline <= $today ) return 'Deadline passed';
    $diff = $today->diff( $deadline );
    $parts = array();
    if ( $diff->y ) $parts[] = $diff->y . 'y';
    if ( $diff->m ) $parts[] = $diff->m . 'm';
    if ( $diff->d || ! $parts ) $parts[] = $diff->d . 'd';
    return implode( ' ', $parts ) . ' left to apply';
}

function bubbahub_myhub_format_date( $date ) {
    if ( ! $date ) return '';
    $timestamp = strtotime( $date );
    return $timestamp ? wp_date( 'j M Y', $timestamp ) : '';
}

function bubbahub_myhub_bookings() {
    if ( ! is_user_logged_in() ) return array();
    $ids = get_posts( array(
        'post_type' => 'bh_booking',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            array( 'key' => '_bh_user_id', 'value' => get_current_user_id(), 'compare' => '=' ),
            array( 'key' => '_bh_status', 'value' => array( 'confirmed', 'reserved' ), 'compare' => 'IN' ),
        ),
        'no_found_rows' => true,
    ) );
    $items = array();
    foreach ( $ids as $booking_id ) {
        $session_id = absint( get_post_meta( $booking_id, '_bh_session_id', true ) );
        if ( ! $session_id || get_post_type( $session_id ) !== 'bh_session' ) continue;
        $date = get_post_meta( $session_id, '_bh_date', true );
        $start = get_post_meta( $session_id, '_bh_start_time', true );
        $timestamp = strtotime( trim( $date . ' ' . $start ) );
        if ( ! $timestamp || $timestamp < current_time( 'timestamp' ) ) continue;
        $group_id = absint( get_post_meta( $session_id, '_bh_group_id', true ) );
        $venue_id = absint( get_post_meta( $session_id, '_bh_venue_id', true ) );
        $items[] = array(
            'id' => $booking_id,
            'session_id' => $session_id,
            'title' => get_the_title( $session_id ),
            'date' => $date,
            'start' => $start,
            'timestamp' => $timestamp,
            'group' => $group_id ? get_the_title( $group_id ) : '',
            'venue' => $venue_id ? get_the_title( $venue_id ) : '',
            'status' => get_post_meta( $booking_id, '_bh_status', true ),
        );
    }
    usort( $items, function( $a, $b ) { return $a['timestamp'] <=> $b['timestamp']; } );
    return $items;
}

function bubbahub_myhub_booking_date_label( $date, $time ) {
    $timestamp = strtotime( trim( $date . ' ' . $time ) );
    if ( ! $timestamp ) return bubbahub_myhub_format_date( $date ) . ( $time ? ', ' . $time : '' );
    $today = current_time( 'timestamp' );
    $tomorrow = strtotime( '+1 day', strtotime( wp_date( 'Y-m-d', $today ) ) );
    $day = wp_date( 'Y-m-d', $timestamp );
    if ( $day === wp_date( 'Y-m-d', $today ) ) $prefix = 'Today';
    elseif ( $day === wp_date( 'Y-m-d', $tomorrow ) ) $prefix = 'Tomorrow';
    else $prefix = wp_date( 'D j M', $timestamp );
    return $prefix . ( $time ? ', ' . wp_date( 'g:i A', strtotime( $time ) ) : '' );
}

function bubbahub_myhub_handle_child_form() {
    if ( ! is_user_logged_in() || empty( $_POST['bubbahub_myhub_child_nonce'] ) ) return;
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bubbahub_myhub_child_nonce'] ) ), 'bubbahub_myhub_child' ) ) return;
    if ( ! current_user_can( 'read' ) ) return;

    $action = isset( $_POST['bh_child_action'] ) ? sanitize_key( wp_unslash( $_POST['bh_child_action'] ) ) : '';
    if ( $action === 'delete' ) {
        $child_id = absint( $_POST['child_id'] ?? 0 );
        if ( $child_id && get_post_type( $child_id ) === 'bh_child' && (int) get_post_field( 'post_author', $child_id ) === get_current_user_id() ) {
            wp_trash_post( $child_id );
            wp_safe_redirect( add_query_arg( 'bh_child_saved', 'deleted', wp_get_referer() ?: home_url( '/my-hub/' ) ) );
            exit;
        }
        return;
    }

    $name = isset( $_POST['child_name'] ) ? sanitize_text_field( wp_unslash( $_POST['child_name'] ) ) : '';
    $dob = isset( $_POST['child_date_of_birth'] ) ? sanitize_text_field( wp_unslash( $_POST['child_date_of_birth'] ) ) : '';
    $school_status = isset( $_POST['school_application_status'] ) ? sanitize_key( wp_unslash( $_POST['school_application_status'] ) ) : 'not_started';
    $deadline = isset( $_POST['school_application_deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['school_application_deadline'] ) ) : '';
    $school = isset( $_POST['school_name'] ) ? sanitize_text_field( wp_unslash( $_POST['school_name'] ) ) : '';
    if ( '' === $name ) return;

    $child_id = absint( $_POST['child_id'] ?? 0 );
    if ( $child_id && ( get_post_type( $child_id ) !== 'bh_child' || (int) get_post_field( 'post_author', $child_id ) !== get_current_user_id() ) ) $child_id = 0;
    $post_data = array( 'post_type' => 'bh_child', 'post_status' => 'publish', 'post_title' => $name, 'post_author' => get_current_user_id() );
    if ( $child_id ) { $post_data['ID'] = $child_id; $saved_id = wp_update_post( $post_data, true ); }
    else $saved_id = wp_insert_post( $post_data, true );
    if ( is_wp_error( $saved_id ) ) return;

    $fields = array(
        'child_name' => $name,
        'child_date_of_birth' => $dob,
        'school_application_status' => $school_status,
        'school_application_deadline' => $deadline,
        'school_name' => $school,
    );
    foreach ( $fields as $key => $value ) {
        if ( function_exists( 'update_field' ) ) update_field( $key, $value, $saved_id );
        else update_post_meta( $saved_id, $key, $value );
    }
    wp_safe_redirect( add_query_arg( 'bh_child_saved', '1', wp_get_referer() ?: home_url( '/my-hub/' ) ) );
    exit;
}

function bubbahub_myhub_render_booking_card( $booking ) {
    ob_start(); ?>
    <article class="bh-myhub-booking-card">
        <div class="bh-myhub-booking-icon" aria-hidden="true">📅</div>
        <div class="bh-myhub-booking-content">
            <div class="bh-myhub-eyebrow">Upcoming Booking</div>
            <h2><?php echo esc_html( $booking['title'] ?: $booking['group'] ); ?></h2>
            <div class="bh-myhub-booking-date"><?php echo esc_html( bubbahub_myhub_booking_date_label( $booking['date'], $booking['start'] ) ); ?></div>
            <?php if ( $booking['venue'] ) : ?><div class="bh-myhub-booking-venue">⌖ <?php echo esc_html( $booking['venue'] ); ?></div><?php endif; ?>
        </div>
    </article>
    <?php return ob_get_clean();
}

function bubbahub_myhub_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div class="bh-myhub-login"><h2>Welcome to My Hub</h2><p>Please log in to see your family dashboard.</p><a class="bh-myhub-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Log in</a></div>';
    }

    wp_enqueue_style( 'bubbahub-myhub' );
    $user = wp_get_current_user();
    $first_name = $user->first_name ? $user->first_name : $user->display_name;
    $bookings = bubbahub_myhub_bookings();
    $children = bubbahub_myhub_child_query();
    $bookings_url = apply_filters( 'bubbahub_myhub_bookings_url', home_url( '/my-bookings/' ) );
    $add_url = add_query_arg( 'bh_add_child', '1', get_permalink() );

    ob_start(); ?>
    <div class="bh-myhub" id="bubbahub-myhub">
        <section class="bh-myhub-hero">
            <div>
                <div class="bh-myhub-kicker">MY HUB</div>
                <h1>Welcome back, <?php echo esc_html( $first_name ); ?></h1>
                <p>Your family overview, active school tracker, and local activities — all in one place.</p>
            </div>
            <div class="bh-myhub-next">
                <?php if ( ! empty( $bookings[0] ) ) : ?>
                    <?php echo bubbahub_myhub_render_booking_card( $bookings[0] ); ?>
                    <a class="bh-myhub-text-link" href="<?php echo esc_url( $bookings_url ); ?>">View all bookings <span>→</span></a>
                <?php else : ?>
                    <div class="bh-myhub-empty-booking"><div class="bh-myhub-booking-icon">📅</div><div><div class="bh-myhub-eyebrow">Upcoming Booking</div><strong>No upcoming bookings</strong><p>Find a local activity your family will love.</p></div></div>
                    <a class="bh-myhub-text-link" href="<?php echo esc_url( home_url( '/directory/' ) ); ?>">Explore activities <span>→</span></a>
                <?php endif; ?>
            </div>
        </section>

        <?php if ( isset( $_GET['bh_child_saved'] ) ) : ?><div class="bh-myhub-notice">Your family profile has been updated.</div><?php endif; ?>

        <section class="bh-myhub-section">
            <div class="bh-myhub-section-heading">
                <div><div class="bh-myhub-kicker">YOUR FAMILY</div><h2>My Family</h2><p>Keep your children's details together and track important school dates.</p></div>
                <a class="bh-myhub-button" href="<?php echo esc_url( $add_url ); ?>">＋ Add child</a>
            </div>

            <?php if ( $children->have_posts() ) : ?>
                <div class="bh-myhub-family-grid">
                    <?php while ( $children->have_posts() ) : $children->the_post();
                        $child_id = get_the_ID();
                        $name = bubbahub_myhub_field( $child_id, 'child_name', get_the_title() );
                        $dob = bubbahub_myhub_field( $child_id, 'child_date_of_birth' );
                        $school_status = bubbahub_myhub_field( $child_id, 'school_application_status', 'not_started' );
                        $deadline = bubbahub_myhub_field( $child_id, 'school_application_deadline' );
                        $school = bubbahub_myhub_field( $child_id, 'school_name' );
                        $edit_url = add_query_arg( array( 'bh_add_child' => '1', 'child_id' => $child_id ), get_permalink() );
                    ?>
                        <article class="bh-myhub-child-card">
                            <div class="bh-myhub-child-top"><div class="bh-myhub-avatar"><?php echo esc_html( strtoupper( mb_substr( $name, 0, 1 ) ) ); ?></div><div><h3><?php echo esc_html( $name ); ?></h3><p><?php echo esc_html( bubbahub_myhub_format_date( $dob ) ); ?><?php if ( $dob ) : ?> · <?php echo esc_html( bubbahub_myhub_age( $dob ) ); ?><?php endif; ?></p></div></div>
                            <?php if ( in_array( $school_status, array( 'active', 'submitted' ), true ) ) : ?>
                                <div class="bh-myhub-tracker active"><div class="bh-myhub-tracker-title">🏫 School Application <?php echo $school_status === 'submitted' ? 'Submitted' : 'Active'; ?></div><?php if ( $deadline ) : ?><strong><?php echo esc_html( bubbahub_myhub_countdown( $deadline ) ); ?></strong><span>Application deadline: <?php echo esc_html( bubbahub_myhub_format_date( $deadline ) ); ?></span><?php endif; ?><?php if ( $school ) : ?><span><?php echo esc_html( $school ); ?></span><?php endif; ?></div>
                            <?php elseif ( $school_status === 'complete' ) : ?>
                                <div class="bh-myhub-tracker complete"><div class="bh-myhub-tracker-title">✓ School Application Complete</div><?php if ( $school ) : ?><span><?php echo esc_html( $school ); ?></span><?php endif; ?></div>
                            <?php else : ?>
                                <div class="bh-myhub-tracker"><div class="bh-myhub-tracker-title">🏫 School Application</div><span>Not started yet</span></div>
                            <?php endif; ?>
                            <div class="bh-myhub-child-actions"><a href="<?php echo esc_url( $edit_url ); ?>">Edit profile</a><form method="post" onsubmit="return confirm('Remove this child profile?');"><input type="hidden" name="bubbahub_myhub_child_nonce" value="<?php echo esc_attr( wp_create_nonce( 'bubbahub_myhub_child' ) ); ?>"><input type="hidden" name="bh_child_action" value="delete"><input type="hidden" name="child_id" value="<?php echo esc_attr( $child_id ); ?>"><button type="submit">Remove</button></form></div>
                        </article>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            <?php else : ?>
                <div class="bh-myhub-empty-family"><div class="bh-myhub-empty-icon">👋</div><div><h3>Start your family profile</h3><p>Add your children's details so Bubba Hub can keep useful school and activity information in one place.</p><a class="bh-myhub-button secondary" href="<?php echo esc_url( $add_url ); ?>">Add your first child</a></div></div>
            <?php endif; ?>
        </section>

        <?php if ( isset( $_GET['bh_add_child'] ) ) :
            $edit_child_id = absint( $_GET['child_id'] ?? 0 );
            if ( $edit_child_id && ( get_post_type( $edit_child_id ) !== 'bh_child' || (int) get_post_field( 'post_author', $edit_child_id ) !== get_current_user_id() ) ) $edit_child_id = 0;
            $edit_name = $edit_child_id ? bubbahub_myhub_field( $edit_child_id, 'child_name', get_the_title( $edit_child_id ) ) : '';
            $edit_dob = $edit_child_id ? bubbahub_myhub_field( $edit_child_id, 'child_date_of_birth' ) : '';
            $edit_status = $edit_child_id ? bubbahub_myhub_field( $edit_child_id, 'school_application_status', 'not_started' ) : 'not_started';
            $edit_deadline = $edit_child_id ? bubbahub_myhub_field( $edit_child_id, 'school_application_deadline' ) : '';
            $edit_school = $edit_child_id ? bubbahub_myhub_field( $edit_child_id, 'school_name' ) : '';
        ?>
            <section class="bh-myhub-form-section" id="family-profile-form"><div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">FAMILY PROFILE</div><h2><?php echo $edit_child_id ? 'Edit child profile' : 'Add a child'; ?></h2><p>Only you can see these details.</p></div></div>
                <form class="bh-myhub-child-form" method="post">
                    <input type="hidden" name="bubbahub_myhub_child_nonce" value="<?php echo esc_attr( wp_create_nonce( 'bubbahub_myhub_child' ) ); ?>"><input type="hidden" name="bh_child_action" value="save"><input type="hidden" name="child_id" value="<?php echo esc_attr( $edit_child_id ); ?>">
                    <div class="bh-myhub-form-grid">
                        <label>Child's name<input type="text" name="child_name" value="<?php echo esc_attr( $edit_name ); ?>" required></label>
                        <label>Date of birth<input type="date" name="child_date_of_birth" value="<?php echo esc_attr( $edit_dob ); ?>"></label>
                        <label>School application status<select name="school_application_status"><option value="not_started" <?php selected( $edit_status, 'not_started' ); ?>>Not started</option><option value="active" <?php selected( $edit_status, 'active' ); ?>>Active</option><option value="submitted" <?php selected( $edit_status, 'submitted' ); ?>>Submitted</option><option value="complete" <?php selected( $edit_status, 'complete' ); ?>>Complete</option></select></label>
                        <label>Application deadline<input type="date" name="school_application_deadline" value="<?php echo esc_attr( $edit_deadline ); ?>"></label>
                        <label class="full">School / setting name<input type="text" name="school_name" value="<?php echo esc_attr( $edit_school ); ?>" placeholder="Optional"></label>
                    </div>
                    <div class="bh-myhub-form-actions"><button class="bh-myhub-button" type="submit">Save child profile</button><a class="bh-myhub-button secondary" href="<?php echo esc_url( remove_query_arg( array( 'bh_add_child', 'child_id' ) ) ); ?>">Cancel</a></div>
                </form>
            </section>
        <?php endif; ?>

        <section class="bh-myhub-section bh-myhub-quick-links"><div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">YOUR BUBBA HUB</div><h2>Family shortcuts</h2></div></div><div class="bh-myhub-links-grid"><a href="<?php echo esc_url( $bookings_url ); ?>"><span>📅</span><strong>My bookings</strong><small>See upcoming activities</small></a><a href="<?php echo esc_url( home_url( '/directory/' ) ); ?>"><span>🔎</span><strong>Find activities</strong><small>Discover local groups</small></a><a href="<?php echo esc_url( $add_url ); ?>"><span>👨‍👩‍👧</span><strong>My family</strong><small>Update child profiles</small></a></div></section>
    </div>
    <?php return ob_get_clean();
}
