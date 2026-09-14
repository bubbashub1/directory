<?php
/**
 * BubbaHub My Hub - Group collections.
 * Adds Favourite Groups, Visited Groups and Suggested Groups widgets plus the My Groups page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', 'bubbahub_myhub_groups_assets' );
add_action( 'wp_ajax_bubbahub_myhub_groups', 'bubbahub_myhub_groups_ajax' );
add_action( 'init', 'bubbahub_myhub_groups_create_page' );
add_shortcode( 'bubbahub_myhub_groups', 'bubbahub_myhub_groups_shortcode' );

function bubbahub_myhub_groups_assets() {
    if ( ! is_user_logged_in() ) return;
    wp_register_style( 'bubbahub-myhub-groups', BUBBAHUB_MYHUB_URL . 'myhub-groups.css', array(), BUBBAHUB_MYHUB_VERSION );
    wp_register_script( 'bubbahub-myhub-groups', BUBBAHUB_MYHUB_URL . 'myhub-groups.js', array( 'jquery' ), BUBBAHUB_MYHUB_VERSION, true );
    wp_localize_script( 'bubbahub-myhub-groups', 'BubbaHubMyHubGroups', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'bubbahub_myhub_groups' ),
        'pageUrl' => home_url( '/my-groups/' ),
    ) );
}

function bubbahub_myhub_groups_create_page() {
    if ( get_option( 'bubbahub_myhub_groups_page_created' ) ) return;
    $page = get_page_by_path( 'my-groups' );
    if ( ! $page ) {
        $page_id = wp_insert_post( array(
            'post_title' => 'My Groups',
            'post_name' => 'my-groups',
            'post_content' => '[bubbahub_myhub_groups]',
            'post_status' => 'publish',
            'post_type' => 'page',
        ), true );
        if ( ! is_wp_error( $page_id ) ) update_option( 'bubbahub_myhub_groups_page_created', 1, false );
    } else {
        update_option( 'bubbahub_myhub_groups_page_created', 1, false );
    }
}

function bubbahub_myhub_groups_clean_ids( $ids ) {
    $ids = is_array( $ids ) ? $ids : array();
    $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
    if ( ! $ids ) return array();
    $valid = get_posts( array(
        'post_type' => 'group', 'post_status' => 'publish', 'post__in' => $ids,
        'posts_per_page' => count( $ids ), 'fields' => 'ids', 'orderby' => 'post__in', 'no_found_rows' => true,
    ) );
    return array_map( 'absint', $valid );
}

function bubbahub_myhub_groups_card( $post_id ) {
    $title = get_the_title( $post_id );
    $url = get_permalink( $post_id );
    $image = function_exists( 'bubbahub_directory_image_url' ) ? bubbahub_directory_image_url( $post_id ) : get_the_post_thumbnail_url( $post_id, 'medium_large' );
    $region = function_exists( 'bubbahub_directory_region' ) ? bubbahub_directory_region( $post_id ) : '';
    $age = function_exists( 'bubbahub_directory_get_field' ) ? bubbahub_directory_get_field( $post_id, 'age_range' ) : get_post_meta( $post_id, 'age_range', true );
    $price = function_exists( 'bubbahub_directory_get_field' ) ? bubbahub_directory_get_field( $post_id, 'price' ) : get_post_meta( $post_id, 'price', true );
    if ( is_array( $age ) ) $age = implode( ', ', $age );
    if ( is_array( $price ) ) $price = implode( ', ', $price );
    ob_start(); ?>
    <article class="bh-myhub-group-card">
        <a class="bh-myhub-group-image" href="<?php echo esc_url( $url ); ?>">
            <?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy"><?php else : ?><span>BubbaHub</span><?php endif; ?>
        </a>
        <div class="bh-myhub-group-body">
            <h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
            <?php if ( $region ) : ?><div class="bh-myhub-group-meta">⌖ <?php echo esc_html( $region ); ?></div><?php endif; ?>
            <div class="bh-myhub-group-tags"><?php if ( $age ) : ?><span><?php echo esc_html( $age ); ?></span><?php endif; ?><?php if ( $price ) : ?><span><?php echo esc_html( $price ); ?></span><?php endif; ?></div>
            <a class="bh-myhub-group-view" href="<?php echo esc_url( $url ); ?>">View group <span>→</span></a>
        </div>
    </article>
    <?php return ob_get_clean();
}

function bubbahub_myhub_groups_ajax() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in.' ), 401 );
    check_ajax_referer( 'bubbahub_myhub_groups', 'nonce' );
    $type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'favourite';
    $ids = isset( $_POST['ids'] ) ? json_decode( wp_unslash( $_POST['ids'] ), true ) : array();
    $ids = bubbahub_myhub_groups_clean_ids( $ids );

    if ( $type === 'suggested' ) {
        $exclude = $ids;
        $favs = isset( $_POST['favourites'] ) ? json_decode( wp_unslash( $_POST['favourites'] ), true ) : array();
        $visited = isset( $_POST['visited'] ) ? json_decode( wp_unslash( $_POST['visited'] ), true ) : array();
        $exclude = array_unique( array_merge( $exclude, bubbahub_myhub_groups_clean_ids( $favs ), bubbahub_myhub_groups_clean_ids( $visited ) ) );
        $query = new WP_Query( array( 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => 24, 'post__not_in' => $exclude, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) );
        $html = '';
        while ( $query->have_posts() ) { $query->the_post(); $html .= bubbahub_myhub_groups_card( get_the_ID() ); }
        wp_reset_postdata();
        wp_send_json_success( array( 'html' => $html, 'count' => $query->post_count ) );
    }

    $html = '';
    foreach ( array_slice( $ids, 0, 24 ) as $id ) $html .= bubbahub_myhub_groups_card( $id );
    wp_send_json_success( array( 'html' => $html, 'count' => count( $ids ) ) );
}

function bubbahub_myhub_groups_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<div class="bh-myhub-login"><h2>My Groups</h2><p>Please log in to see your saved groups.</p></div>';
    }
    wp_enqueue_style( 'bubbahub-myhub' );
    wp_enqueue_style( 'bubbahub-myhub-groups' );
    wp_enqueue_script( 'bubbahub-myhub-groups' );
    $view = isset( $_GET['group_view'] ) ? sanitize_key( wp_unslash( $_GET['group_view'] ) ) : 'favourite';
    if ( ! in_array( $view, array( 'favourite', 'visited', 'suggested' ), true ) ) $view = 'favourite';
    $titles = array( 'favourite' => 'Favourite Groups', 'visited' => 'Visited Groups', 'suggested' => 'Suggested Groups' );
    ob_start(); ?>
    <div class="bh-myhub bh-myhub-groups-page" data-myhub-groups-page="<?php echo esc_attr( $view ); ?>">
        <div class="bh-myhub-groups-page-head">
            <div><div class="bh-myhub-kicker">YOUR BUBBA HUB</div><h1><?php echo esc_html( $titles[ $view ] ); ?></h1><p>Keep the local groups that matter to your family close at hand.</p></div>
            <a class="bh-myhub-button secondary" href="<?php echo esc_url( home_url( '/my-hub/' ) ); ?>">← Back to My Hub</a>
        </div>
        <div class="bh-myhub-groups-tabs">
            <?php foreach ( $titles as $key => $label ) : ?><a class="<?php echo $view === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'group_view', $key, home_url( '/my-groups/' ) ) ); ?>"><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
        </div>
        <div class="bh-myhub-groups-results" data-myhub-group-results data-group-view="<?php echo esc_attr( $view ); ?>"><div class="bh-myhub-groups-loading">Loading your groups…</div></div>
    </div>
    <?php return ob_get_clean();
}
