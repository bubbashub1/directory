<?php
/**
 * BubbaHub family favourites + lightweight personalised recommendations.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'bubbahub_family_recommendations', 'bubbahub_family_recommendations_shortcode' );
add_action( 'wp_ajax_bubbahub_toggle_favourite', 'bubbahub_toggle_favourite_ajax' );
add_action( 'wp_ajax_nopriv_bubbahub_toggle_favourite', 'bubbahub_toggle_favourite_guest' );
add_action( 'wp_enqueue_scripts', 'bubbahub_family_favourites_assets', 35 );

function bubbahub_family_favourites_assets() {
    if ( ! is_singular( 'group' ) && ! is_page( 'my-hub' ) && ! is_page( 'myhub' ) ) return;
    wp_enqueue_style( 'bubbahub-family-favourites', BUBBAHUB_DIRECTORY_URL . 'assets/family-favourites.css', array(), BUBBAHUB_DIRECTORY_VERSION );
    wp_enqueue_script( 'bubbahub-family-favourites', BUBBAHUB_DIRECTORY_URL . 'assets/family-favourites.js', array(), BUBBAHUB_DIRECTORY_VERSION, true );
    wp_localize_script( 'bubbahub-family-favourites', 'BubbaHubFavourites', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'bubbahub_favourite' ),
        'loginMessage' => 'Please log in to save groups to My Hub.',
    ) );
}

function bubbahub_user_favourite_ids( $user_id = 0 ) {
    $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
    if ( ! $user_id ) return array();
    $ids = get_user_meta( $user_id, 'bh_favourite_groups', true );
    if ( ! is_array( $ids ) ) $ids = array();
    return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}

function bubbahub_toggle_favourite_guest() {
    wp_send_json_error( array( 'message' => 'Please log in to save groups to My Hub.' ), 401 );
}

function bubbahub_toggle_favourite_ajax() {
    check_ajax_referer( 'bubbahub_favourite', 'nonce' );
    if ( ! is_user_logged_in() ) bubbahub_toggle_favourite_guest();
    $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
    if ( ! $group_id || 'group' !== get_post_type( $group_id ) ) wp_send_json_error( array( 'message' => 'Invalid group.' ), 400 );
    $ids = bubbahub_user_favourite_ids();
    $key = array_search( $group_id, $ids, true );
    if ( false !== $key ) { unset( $ids[ $key ] ); $saved = false; } else { $ids[] = $group_id; $saved = true; }
    $ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
    update_user_meta( get_current_user_id(), 'bh_favourite_groups', $ids );
    wp_send_json_success( array( 'saved' => $saved, 'count' => count( $ids ), 'label' => $saved ? 'Saved to My Hub' : 'Save to My Hub' ) );
}

function bubbahub_group_interest_ids() {
    $wanted = array();
    if ( ! is_user_logged_in() ) return $wanted;
    $values = get_user_meta( get_current_user_id(), 'user-interests', true );
    if ( function_exists( 'get_field' ) ) {
        $acf = get_field( 'user-interests', 'user_' . get_current_user_id(), false );
        if ( null !== $acf && false !== $acf && '' !== $acf ) $values = $acf;
    }
    if ( ! is_array( $values ) ) $values = ( '' === (string) $values ) ? array() : preg_split( '/\s*,\s*/', (string) $values );
    foreach ( $values as $value ) {
        if ( is_object( $value ) && isset( $value->term_id ) ) $wanted[] = absint( $value->term_id );
        elseif ( is_array( $value ) && isset( $value['term_id'] ) ) $wanted[] = absint( $value['term_id'] );
        elseif ( is_numeric( $value ) ) $wanted[] = absint( $value );
        else { $term = get_term_by( 'slug', sanitize_title( $value ), 'user-interests' ); if ( $term ) $wanted[] = (int) $term->term_id; }
    }
    return array_values( array_unique( array_filter( $wanted ) ) );
}

function bubbahub_family_recommendation_ids( $limit = 6 ) {
    if ( ! is_user_logged_in() ) return array();
    $favourites = bubbahub_user_favourite_ids();
    $interest = bubbahub_group_interest_ids();
    $tax_query = array();
    if ( $interest && taxonomy_exists( 'user-interests' ) ) $tax_query[] = array( 'taxonomy' => 'user-interests', 'field' => 'term_id', 'terms' => $interest );
    $args = array( 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => max( 12, $limit * 3 ), 'post__not_in' => $favourites, 'no_found_rows' => true );
    if ( $tax_query ) $args['tax_query'] = $tax_query;
    $posts = get_posts( $args );
    if ( count( $posts ) < $limit ) {
        $fallback = get_posts( array_merge( $args, array( 'tax_query' => array(), 'posts_per_page' => $limit * 2 ) ) );
        $seen = array_map( 'absint', wp_list_pluck( $posts, 'ID' ) );
        foreach ( $fallback as $post ) if ( ! in_array( $post->ID, $seen, true ) ) { $posts[] = $post; $seen[] = $post->ID; if ( count( $posts ) >= $limit ) break; }
    }
    return array_slice( $posts, 0, $limit );
}

function bubbahub_family_favourite_button( $group_id = 0 ) {
    $group_id = absint( $group_id ?: get_the_ID() );
    if ( ! $group_id || 'group' !== get_post_type( $group_id ) ) return '';
    $saved = is_user_logged_in() && in_array( $group_id, bubbahub_user_favourite_ids(), true );
    return '<button type="button" class="bh-favourite-button' . ( $saved ? ' is-saved' : '' ) . '" data-bh-favourite="' . esc_attr( $group_id ) . '" aria-pressed="' . ( $saved ? 'true' : 'false' ) . '"><span aria-hidden="true">' . ( $saved ? '♥' : '♡' ) . '</span> <span class="bh-favourite-label">' . esc_html( $saved ? 'Saved to My Hub' : 'Save to My Hub' ) . '</span></button>';
}

function bubbahub_family_recommendations_shortcode( $atts = array() ) {
    if ( ! is_user_logged_in() ) return '<div class="bh-family-recommendations"><h2>Personalised for your family</h2><p>Log in to see recommendations based on your saved interests and My Hub preferences.</p></div>';
    $atts = shortcode_atts( array( 'limit' => 6 ), $atts, 'bubbahub_family_recommendations' );
    $posts = bubbahub_family_recommendation_ids( min( 12, max( 1, absint( $atts['limit'] ) ) ) );
    ob_start(); ?>
    <section class="bh-family-recommendations"><div class="bh-family-recommendations-head"><div><span class="bh-family-kicker">MY HUB</span><h2>Suggested for your family</h2><p>Recommendations use your saved interests and can grow as you save groups.</p></div><a href="<?php echo esc_url( home_url( '/my-hub/' ) ); ?>">Open My Hub →</a></div><div class="bh-family-recommendation-grid">
    <?php if ( $posts ) : foreach ( $posts as $post ) : $image = get_the_post_thumbnail_url( $post->ID, 'medium' ); ?><article class="bh-family-recommendation-card"><a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy"><?php else : ?><span class="bh-family-recommendation-placeholder" aria-hidden="true">♡</span><?php endif; ?><strong><?php echo esc_html( get_the_title( $post->ID ) ); ?></strong></a><?php echo bubbahub_family_favourite_button( $post->ID ); ?></article><?php endforeach; else : ?><p>No suggestions yet. Add interests in My Hub or save a few groups and Bubba Hub will use them to personalise this area.</p><?php endif; ?></div></section>
    <?php return ob_get_clean();
}

add_filter( 'the_content', 'bubbahub_family_favourites_group_content', 25 );
function bubbahub_family_favourites_group_content( $content ) {
    if ( ! is_singular( 'group' ) || ! in_the_loop() || ! is_main_query() ) return $content;
    return $content . '<div class="bh-group-favourite-wrap">' . bubbahub_family_favourite_button( get_the_ID() ) . '</div>';
}
