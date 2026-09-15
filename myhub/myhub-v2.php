<?php
/**
 * BubbaHub My Hub v3 – stable dashboard layer.
 * Provides [bubbahub_my_hub] and keeps the legacy [bubbahub-my-hub] alias.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_myhub_v3_register', 30 );

function bubbahub_myhub_v3_register() {
    remove_shortcode( 'bubbahub_my_hub' );
    remove_shortcode( 'bubbahub-my-hub' );
    add_shortcode( 'bubbahub_my_hub', 'bubbahub_myhub_v3_render' );
    add_shortcode( 'bubbahub-my-hub', 'bubbahub_myhub_v3_render' );
}

function bubbahub_myhub_v3_render() {
    if ( ! is_user_logged_in() ) {
        return '<div class="bh-myhub-login"><h2>Welcome to My Hub</h2><p>Please log in to see your family dashboard.</p></div>';
    }

    $uid = get_current_user_id();
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

    ob_start();
    ?>
    <div class="bh-myhub bh-myhub-v3" data-bh-myhub="1">
        <section class="bh-myhub-hero">
            <div>
                <div class="bh-myhub-kicker">MY HUB</div>
                <h1>Welcome back, <?php echo esc_html( $user->first_name ?: $user->display_name ); ?></h1>
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
                <?php if ( $children ) : foreach ( $children as $child ) :
                    $name = function_exists( 'get_field' ) ? get_field( 'child_name', $child->ID ) : get_post_meta( $child->ID, 'child_name', true );
                    $name = $name ?: $child->post_title;
                    $status = function_exists( 'get_field' ) ? get_field( 'child_status', $child->ID ) : get_post_meta( $child->ID, 'child_status', true );
                    $dob = function_exists( 'get_field' ) ? get_field( 'child_date_of_birth', $child->ID ) : get_post_meta( $child->ID, 'child_date_of_birth', true );
                    $due = function_exists( 'get_field' ) ? get_field( 'child_due_date', $child->ID ) : get_post_meta( $child->ID, 'child_due_date', true );
                    ?>
                    <article class="bh-myhub-child-card">
                        <div class="bh-myhub-child-top">
                            <div class="bh-myhub-avatar"><?php echo esc_html( strtoupper( function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 ) ) ); ?></div>
                            <div>
                                <h3><?php echo esc_html( $name ); ?></h3>
                                <?php if ( 'expecting' === $status && $due ) : ?>
                                    <p>Expecting · Due <?php echo esc_html( wp_date( 'j M Y', strtotime( $due ) ) ); ?></p>
                                <?php elseif ( $dob ) : ?>
                                    <p><?php echo esc_html( wp_date( 'j M Y', strtotime( $dob ) ) ); ?></p>
                                <?php else : ?>
                                    <p>Date of birth not added</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <label class="bh-myhub-child-select">
                            <input type="checkbox" class="bh-myhub-selected-child" data-child-id="<?php echo esc_attr( $child->ID ); ?>">
                            Use <?php echo esc_html( $name ); ?> for group suggestions
                        </label>
                        <div class="bh-myhub-child-actions">
                            <a href="<?php echo esc_url( add_query_arg( array( 'bh_add_child' => 1, 'child_id' => $child->ID ) ) ); ?>">Edit profile</a>
                        </div>
                    </article>
                <?php endforeach; else : ?>
                    <div class="bh-myhub-empty-family"><div class="bh-myhub-empty-icon">👋</div><div><h3>Start your family profile</h3><p>Add your first child so Bubba Hub can personalise activities for your family.</p></div></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="bh-myhub-section">
            <div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">YOUR LOCAL ACTIVITIES</div><h2>Your Groups</h2><p>Recently viewed, favourites and visited groups.</p></div></div>
            <div class="bh-myhub-groups-row">
                <?php foreach ( array( 'recently_viewed' => 'Recently Viewed', 'favourite' => '♡ Fav Groups', 'visited' => '✓ Visited Groups' ) as $type => $title ) : ?>
                    <div class="bh-myhub-group-column">
                        <div class="bh-myhub-group-column-head"><h3><?php echo esc_html( $title ); ?></h3><a data-group-view-more href="<?php echo esc_url( home_url( '/my-groups/?group_view=' . $type ) ); ?>">View more →</a></div>
                        <div class="bh-myhub-group-widget" data-myhub-group-widget data-group-type="<?php echo esc_attr( $type ); ?>" data-view-more="1"><div class="bh-myhub-groups-loading">Loading…</div></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bh-myhub-section bh-myhub-suggested-section">
            <div class="bh-myhub-section-heading"><div><div class="bh-myhub-kicker">PERSONALISED FOR YOUR FAMILY</div><h2>Suggested Groups For Your Family</h2><p>Matched using your saved preferences and selected children's age ranges.</p></div></div>
            <div class="bh-myhub-group-widget bh-myhub-suggested-widget" data-myhub-group-widget data-group-type="suggested" data-view-more="1"><div class="bh-myhub-groups-loading">Building your suggestions…</div></div>
            <div class="bh-myhub-suggested-more"><a class="bh-myhub-button secondary" href="<?php echo esc_url( home_url( '/my-groups/?group_view=suggested' ) ); ?>">View all suggested groups →</a></div>
        </section>
    </div>
    <script>window.BubbaHubMyHubSelectedChildren = <?php echo wp_json_encode( array_map( 'absint', (array) get_user_meta( $uid, 'bh_myhub_selected_children', true ) ) ); ?>;</script>
    <?php
    return ob_get_clean();
}

bubbahub_myhub_v3_register();
