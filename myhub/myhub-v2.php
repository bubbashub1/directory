<?php
/**
 * BubbaHub My Hub – stable dashboard layer.
 *
 * This file is intentionally self-contained and safe to load from myhub.php.
 * Shortcodes: [bubbahub_my_hub] and [bubbahub-my-hub]
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* Register once, after the base My Hub loader has loaded. */
add_action( 'init', 'bubbahub_myhub_v3_register', 30 );

if ( ! function_exists( 'bubbahub_myhub_v3_register' ) ) {
    function bubbahub_myhub_v3_register() {
        remove_shortcode( 'bubbahub_my_hub' );
        remove_shortcode( 'bubbahub-my-hub' );
        add_shortcode( 'bubbahub_my_hub', 'bubbahub_myhub_v3_render' );
        add_shortcode( 'bubbahub-my-hub', 'bubbahub_myhub_v3_render' );
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_child_field' ) ) {
    function bubbahub_myhub_v3_child_field( $post_id, $field, $default = '' ) {
        $post_id = absint( $post_id );
        $field   = sanitize_key( $field );
        if ( ! $post_id || ! $field ) {
            return $default;
        }

        if ( function_exists( 'get_field' ) ) {
            $value = get_field( $field, $post_id, false );
            if ( null !== $value && false !== $value && '' !== $value ) {
                return $value;
            }
        }

        $value = get_post_meta( $post_id, $field, true );
        return ( '' !== $value && false !== $value ) ? $value : $default;
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_date' ) ) {
    function bubbahub_myhub_v3_date( $date ) {
        if ( empty( $date ) ) {
            return '';
        }
        $date = sanitize_text_field( $date );
        $timestamp = strtotime( $date );
        return $timestamp ? wp_date( 'j M Y', $timestamp ) : '';
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_school_status_label' ) ) {
    function bubbahub_myhub_v3_school_status_label( $status ) {
        $labels = array(
            'active'       => 'Active',
            'closed'       => 'Closed',
            'now_in_school'=> 'Now in School',
        );
        $status = sanitize_key( $status );
        return isset( $labels[ $status ] ) ? $labels[ $status ] : 'Active';
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_school_countdown' ) ) {
    function bubbahub_myhub_v3_school_countdown( $date ) {
        if ( empty( $date ) ) {
            return '';
        }

        $date = sanitize_text_field( $date );
        $deadline = DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $deadline || $deadline->format( 'Y-m-d' ) !== $date ) {
            return '';
        }

        $today = new DateTime( 'today' );
        if ( $deadline < $today ) {
            return 'Deadline passed';
        }

        $diff   = $today->diff( $deadline );
        $parts  = array();
        if ( $diff->y ) { $parts[] = $diff->y . 'y'; }
        if ( $diff->m ) { $parts[] = $diff->m . 'm'; }
        if ( $diff->d || ! $parts ) { $parts[] = $diff->d . 'd'; }

        return implode( ' ', $parts ) . ' left to apply';
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_auto_school_deadline' ) ) {
    function bubbahub_myhub_v3_auto_school_deadline( $dob ) {
        if ( empty( $dob ) ) {
            return '';
        }

        $dob = sanitize_text_field( $dob );
        $birth = DateTime::createFromFormat( 'Y-m-d', $dob );
        if ( ! $birth || $birth->format( 'Y-m-d' ) !== $dob ) {
            return '';
        }

        /* Keep the existing project rule, but avoid invalid dates. */
        $entry_year = (int) $birth->format( 'Y' ) + 5;
        if ( (int) $birth->format( 'm' ) > 8 || ( 8 === (int) $birth->format( 'm' ) && (int) $birth->format( 'd' ) > 31 ) ) {
            $entry_year++;
        }

        return $entry_year . '-01-15';
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_school_tracker' ) ) {
    function bubbahub_myhub_v3_school_tracker( $child_id, $status, $dob ) {
        if ( 'expecting' === sanitize_key( $status ) ) {
            return '';
        }

        $child_id    = absint( $child_id );
        $school_name = bubbahub_myhub_v3_child_field( $child_id, 'school_name' );
        $school_status = bubbahub_myhub_v3_child_field( $child_id, 'school_application_status', 'active' );
        $deadline    = bubbahub_myhub_v3_child_field( $child_id, 'school_application_deadline' );
        $school_year = bubbahub_myhub_v3_child_field( $child_id, 'school_year' );
        $ofsted      = bubbahub_myhub_v3_child_field( $child_id, 'ofsted_rating' );

        if ( ! $deadline && $dob ) {
            $deadline = bubbahub_myhub_v3_auto_school_deadline( $dob );
        }

        $status_label = bubbahub_myhub_v3_school_status_label( $school_status );
        $countdown    = bubbahub_myhub_v3_school_countdown( $deadline );
        $has_school   = ! empty( $school_name );
        $tracker_class = $has_school ? 'school-hub' : 'school-application';
        $title = $has_school ? '🏫 School & Childcare Hub' : '🏫 School Application';

        ob_start();
        ?>
        <div class="bh-myhub-tracker bh-myhub-school-tracker <?php echo esc_attr( $tracker_class ); ?>">
            <div class="bh-myhub-school-tracker-head">
                <div class="bh-myhub-tracker-title"><?php echo esc_html( $title ); ?></div>
                <span class="bh-myhub-school-status <?php echo esc_attr( sanitize_html_class( $school_status ) ); ?>"><?php echo esc_html( $status_label ); ?></span>
            </div>
            <?php if ( $has_school ) : ?>
                <strong><?php echo esc_html( $school_name ); ?></strong>
                <?php if ( $ofsted || $school_year ) : ?>
                    <div class="bh-myhub-school-meta">
                        <?php if ( $ofsted ) : ?><span>Ofsted: <?php echo esc_html( $ofsted ); ?></span><?php endif; ?>
                        <?php if ( $school_year ) : ?><span><?php echo esc_html( $school_year ); ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php elseif ( $deadline ) : ?>
                <strong>Application deadline: <?php echo esc_html( bubbahub_myhub_v3_date( $deadline ) ); ?></strong>
            <?php else : ?>
                <strong>School application details not added yet</strong>
            <?php endif; ?>
            <?php if ( $countdown && ! $has_school && 'now_in_school' !== $school_status && 'closed' !== $school_status ) : ?>
                <span class="bh-myhub-school-countdown">⏳ <?php echo esc_html( $countdown ); ?></span>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

if ( ! function_exists( 'bubbahub_myhub_v3_render' ) ) {
    function bubbahub_myhub_v3_render() {
        if ( ! is_user_logged_in() ) {
            return '<div class="bh-myhub-login"><h2>Welcome to My Hub</h2><p>Please log in to see your family dashboard.</p></div>';
        }

        $uid  = get_current_user_id();
        $user = wp_get_current_user();

        wp_enqueue_style( 'bubbahub-myhub' );
        wp_enqueue_style( 'bubbahub-myhub-groups' );
        wp_enqueue_script( 'bubbahub-myhub-groups' );

        $children = get_posts( array(
            'post_type'      => 'bh_child',
            'post_status'    => 'publish',
            'author'         => $uid,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ) );

        $selected_children = get_user_meta( $uid, 'bh_myhub_selected_children', true );
        if ( ! is_array( $selected_children ) ) {
            $selected_children = array();
        }
        $selected_children = array_map( 'absint', $selected_children );

        ob_start();
        ?>
        <div class="bh-myhub bh-myhub-v3" data-bh-myhub="1">
            <section class="bh-myhub-hero">
                <div>
                    <div class="bh-myhub-kicker">MY HUB</div>
                    <h1>Welcome back, <?php echo esc_html( $user->first_name ? $user->first_name : $user->display_name ); ?></h1>
                    <p>Your family overview, interests, local activities and pregnancy journey — all in one place.</p>
                </div>
                <div class="bh-myhub-next">
                    <div class="bh-myhub-eyebrow">YOUR FAMILY HUB</div>
                    <strong>Find activities that fit your family</strong>
                    <p>Your saved preferences help Bubba Hub personalise your group suggestions.</p>
                </div>
            </section>

            <section class="bh-myhub-section">
                <div class="bh-myhub-section-heading">
                    <div>
                        <div class="bh-myhub-kicker">YOUR FAMILY</div>
                        <h2>My Child Profiles</h2>
                        <p>Manage the children used to personalise your group suggestions.</p>
                    </div>
                    <a class="bh-myhub-button" href="<?php echo esc_url( add_query_arg( 'bh_add_child', '1', get_permalink() ) ); ?>">＋ Add child</a>
                </div>

                <div class="bh-myhub-family-grid">
                    <?php if ( $children ) : ?>
                        <?php foreach ( $children as $child ) :
                            $name   = bubbahub_myhub_v3_child_field( $child->ID, 'child_name', $child->post_title );
                            $status = bubbahub_myhub_v3_child_field( $child->ID, 'child_status', 'born' );
                            $dob    = bubbahub_myhub_v3_child_field( $child->ID, 'child_date_of_birth' );
                            $due    = bubbahub_myhub_v3_child_field( $child->ID, 'child_due_date' );
                            $is_expecting = ( 'expecting' === sanitize_key( $status ) && ! empty( $due ) );
                            $initial = function_exists( 'mb_substr' ) ? mb_substr( (string) $name, 0, 1 ) : substr( (string) $name, 0, 1 );
                            ?>
                            <article class="bh-myhub-child-card">
                                <div class="bh-myhub-child-top">
                                    <div class="bh-myhub-avatar"><?php echo esc_html( strtoupper( $initial ) ); ?></div>
                                    <div>
                                        <h3><?php echo esc_html( $name ); ?></h3>
                                        <?php if ( $is_expecting ) : ?>
                                            <p>Expecting · Due <?php echo esc_html( bubbahub_myhub_v3_date( $due ) ); ?></p>
                                        <?php elseif ( $dob ) : ?>
                                            <p><?php echo esc_html( bubbahub_myhub_v3_date( $dob ) ); ?></p>
                                        <?php else : ?>
                                            <p>Date of birth not added</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ( $is_expecting ) : ?>
                                    <div class="bh-myhub-tracker pregnancy">
                                        <div class="bh-myhub-tracker-title">🤰 Antenatal Groups Hub</div>
                                        <strong>Antenatal support and classes for your pregnancy journey</strong>
                                        <span>Due <?php echo esc_html( bubbahub_myhub_v3_date( $due ) ); ?></span>
                                    </div>
                                <?php else : ?>
                                    <?php echo bubbahub_myhub_v3_school_tracker( $child->ID, $status, $dob ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php endif; ?>

                                <label class="bh-myhub-child-select">
                                    <input type="checkbox" class="bh-myhub-selected-child" data-child-id="<?php echo esc_attr( $child->ID ); ?>" <?php checked( in_array( $child->ID, $selected_children, true ) ); ?>>
                                    Use <?php echo esc_html( $name ); ?> for group suggestions
                                </label>

                                <div class="bh-myhub-child-actions">
                                    <a href="<?php echo esc_url( add_query_arg( array( 'bh_add_child' => 1, 'child_id' => $child->ID ), get_permalink() ) ); ?>">Edit profile</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <div class="bh-myhub-empty-family">
                            <div class="bh-myhub-empty-icon">👋</div>
                            <div><h3>Start your family profile</h3><p>Add your first child so Bubba Hub can personalise activities for your family.</p></div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="bh-myhub-section">
                <div class="bh-myhub-section-heading">
                    <div><div class="bh-myhub-kicker">YOUR LOCAL ACTIVITIES</div><h2>Your Groups</h2><p>Recently viewed, favourites and visited groups.</p></div>
                </div>
                <div class="bh-myhub-groups-row">
                    <?php foreach ( array( 'recently_viewed' => 'Recently Viewed', 'favourite' => '♡ Fav Groups', 'visited' => '✓ Visited Groups' ) as $type => $title ) : ?>
                        <div class="bh-myhub-group-column">
                            <div class="bh-myhub-group-column-head">
                                <h3><?php echo esc_html( $title ); ?></h3>
                                <a data-group-view-more href="<?php echo esc_url( home_url( '/my-groups/?group_view=' . rawurlencode( $type ) ) ); ?>">View more →</a>
                            </div>
                            <div class="bh-myhub-group-widget" data-myhub-group-widget data-group-type="<?php echo esc_attr( $type ); ?>" data-view-more="1"><div class="bh-myhub-groups-loading">Loading…</div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="bh-myhub-section bh-myhub-suggested-section">
                <div class="bh-myhub-section-heading">
                    <div><div class="bh-myhub-kicker">PERSONALISED FOR YOUR FAMILY</div><h2>Suggested Groups For Your Family</h2><p>Matched using your saved preferences and selected children's age ranges.</p></div>
                </div>
                <div class="bh-myhub-group-widget bh-myhub-suggested-widget" data-myhub-group-widget data-group-type="suggested" data-view-more="1"><div class="bh-myhub-groups-loading">Building your suggestions…</div></div>
                <div class="bh-myhub-suggested-more"><a class="bh-myhub-button secondary" href="<?php echo esc_url( home_url( '/my-groups/?group_view=suggested' ) ); ?>">View all suggested groups →</a></div>
            </section>
        </div>
        <script>window.BubbaHubMyHubSelectedChildren=<?php echo wp_json_encode( $selected_children ); ?>;</script>
        <?php
        return ob_get_clean();
    }
}
