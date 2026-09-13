<?php
/**
 * Plugin Name: BubbaHub Directory
 * Description: Front-end directory for the Group custom post type with ACF-powered cards, advanced search, responsive grid controls and map view.
 * Version: 1.1.0
 * Author: BubbaHub
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BUBBAHUB_DIRECTORY_VERSION', '1.1.0' );
define( 'BUBBAHUB_DIRECTORY_URL', plugin_dir_url( __FILE__ ) );

a dd_action( 'wp_enqueue_scripts', 'bubbahub_directory_assets' );
add_action( 'wp_ajax_bubbahub_directory_filter', 'bubbahub_directory_ajax_filter' );
add_action( 'wp_ajax_nopriv_bubbahub_directory_filter', 'bubbahub_directory_ajax_filter' );
add_shortcode( 'bubbahub_directory', 'bubbahub_directory_shortcode' );

function bubbahub_directory_assets() {
    wp_register_style( 'bubbahub-directory', BUBBAHUB_DIRECTORY_URL . 'assets/directory.css', array(), BUBBAHUB_DIRECTORY_VERSION );
    wp_register_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
    wp_register_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
    wp_register_script( 'bubbahub-directory', BUBBAHUB_DIRECTORY_URL . 'assets/directory.js', array( 'jquery', 'leaflet' ), BUBBAHUB_DIRECTORY_VERSION, true );
}

function bubbahub_directory_get_field( $post_id, $name, $default = '' ) {
    if ( function_exists( 'get_field' ) ) {
        $value = get_field( $name, $post_id );
        if ( $value !== null && $value !== false && $value !== '' ) return $value;
    }
    $value = get_post_meta( $post_id, $name, true );
    return ( $value !== '' && $value !== false ) ? $value : $default;
}

function bubbahub_directory_normalise_map( $map ) {
    if ( is_array( $map ) ) {
        $lat = isset( $map['lat'] ) ? $map['lat'] : ( isset( $map['latitude'] ) ? $map['latitude'] : '' );
        $lng = isset( $map['lng'] ) ? $map['lng'] : ( isset( $map['longitude'] ) ? $map['longitude'] : '' );
        if ( $lat !== '' && $lng !== '' ) return array( 'lat' => (float) $lat, 'lng' => (float) $lng );
    }
    if ( is_string( $map ) && preg_match( '/(-?\d+(?:\.\d+)?)[, ]+(-?\d+(?:\.\d+)?)/', $map, $m ) ) {
        return array( 'lat' => (float) $m[1], 'lng' => (float) $m[2] );
    }
    return null;
}

function bubbahub_directory_image_url( $post_id ) {
    $image = bubbahub_directory_get_field( $post_id, 'image' );
    if ( is_array( $image ) && ! empty( $image['url'] ) ) return $image['url'];
    if ( is_numeric( $image ) ) {
        $url = wp_get_attachment_image_url( (int) $image, 'large' );
        if ( $url ) return $url;
    }
    if ( is_string( $image ) && filter_var( $image, FILTER_VALIDATE_URL ) ) return $image;
    return get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';
}

function bubbahub_directory_badges( $post_id ) {
    $badges = bubbahub_directory_get_field( $post_id, 'badges', array() );
    $items = array();
    if ( is_array( $badges ) ) {
        foreach ( $badges as $badge ) {
            if ( is_array( $badge ) ) $badge = isset( $badge['label'] ) ? $badge['label'] : ( isset( $badge['name'] ) ? $badge['name'] : '' );
            if ( is_object( $badge ) && isset( $badge->name ) ) $badge = $badge->name;
            if ( is_string( $badge ) && trim( $badge ) !== '' ) $items[] = trim( $badge );
        }
    } elseif ( is_string( $badges ) && trim( $badges ) !== '' ) {
        $items = preg_split( '/[,|]+/', $badges );
    }
    return array_slice( array_filter( array_map( 'trim', $items ) ), 0, 4 );
}

function bubbahub_directory_region( $post_id ) {
    $terms = get_the_terms( $post_id, 'region' );
    if ( is_wp_error( $terms ) || empty( $terms ) ) return '';
    return implode( ', ', wp_list_pluck( $terms, 'name' ) );
}

function bubbahub_directory_age_values() {
    $values = array();
    $ids = get_posts( array( 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
    foreach ( $ids as $id ) {
        $value = bubbahub_directory_get_field( $id, 'age_range' );
        if ( is_array( $value ) ) $value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
        if ( is_string( $value ) && $value !== '' ) $values[] = $value;
    }
    $values = array_unique( $values );
    natcasesort( $values );
    return $values;
}

function bubbahub_directory_query( $filters = array() ) {
    $search = isset( $filters['search'] ) ? sanitize_text_field( $filters['search'] ) : '';
    $region = isset( $filters['region'] ) ? sanitize_title( $filters['region'] ) : '';
    $age = isset( $filters['age'] ) ? sanitize_text_field( $filters['age'] ) : '';
    $price = isset( $filters['price'] ) ? sanitize_text_field( $filters['price'] ) : '';
    $paged = isset( $filters['paged'] ) ? max( 1, (int) $filters['paged'] ) : 1;
    $per_page = isset( $filters['posts_per_page'] ) ? max( 1, min( 100, (int) $filters['posts_per_page'] ) ) : 12;

    $args = array(
        'post_type' => 'group',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $paged,
        's' => $search,
        'orderby' => 'date',
        'order' => 'DESC',
    );
    if ( $region ) $args['tax_query'] = array( array( 'taxonomy' => 'region', 'field' => 'slug', 'terms' => $region ) );
    $meta_query = array();
    if ( $age ) $meta_query[] = array( 'key' => 'age_range', 'value' => $age, 'compare' => 'LIKE' );
    if ( $price === 'free' ) $meta_query[] = array( 'key' => 'price', 'value' => 'Free', 'compare' => 'LIKE' );
    if ( $price === 'paid' ) $meta_query[] = array( 'key' => 'price', 'value' => 'Free', 'compare' => 'NOT LIKE' );
    if ( $meta_query ) $args['meta_query'] = $meta_query;
    return new WP_Query( $args );
}

function bubbahub_directory_render_cards( $query ) {
    ob_start();
    if ( $query->have_posts() ) :
        while ( $query->have_posts() ) : $query->the_post();
            $id = get_the_ID();
            $image = bubbahub_directory_image_url( $id );
            $map = bubbahub_directory_normalise_map( bubbahub_directory_get_field( $id, 'map' ) );
            $region = bubbahub_directory_region( $id );
            $age = bubbahub_directory_get_field( $id, 'age_range' );
            $price = bubbahub_directory_get_field( $id, 'price' );
            $badges = bubbahub_directory_badges( $id );
            if ( is_array( $age ) ) $age = implode( ', ', $age );
            if ( is_array( $price ) ) $price = implode( ', ', $price );
            ?>
            <article class="bh-card" data-lat="<?php echo $map ? esc_attr( $map['lat'] ) : ''; ?>" data-lng="<?php echo $map ? esc_attr( $map['lng'] ) : ''; ?>" data-title="<?php echo esc_attr( get_the_title() ); ?>" data-url="<?php echo esc_url( get_permalink() ); ?>">
                <div class="bh-card-image">
                    <?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy">
                    <?php else : ?><div class="bh-image-placeholder">BubbaHub</div><?php endif; ?>
                    <?php if ( $badges ) : ?><div class="bh-badges" aria-label="Badges"><?php foreach ( $badges as $badge ) : ?><span><?php echo esc_html( $badge ); ?></span><?php endforeach; ?></div><?php endif; ?>
                </div>
                <div class="bh-card-body">
                    <h2><a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a></h2>
                    <?php if ( $region ) : ?><div class="bh-meta"><span class="bh-icon" aria-hidden="true">⌖</span><?php echo esc_html( $region ); ?></div><?php endif; ?>
                    <div class="bh-card-meta">
                        <?php if ( $age ) : ?><span><?php echo esc_html( $age ); ?></span><?php endif; ?>
                        <?php if ( $price ) : ?><span><?php echo esc_html( $price ); ?></span><?php endif; ?>
                    </div>
                    <a class="bh-view-more" href="<?php echo esc_url( get_permalink() ); ?>">View More <span aria-hidden="true">→</span></a>
                </div>
            </article>
            <?php
        endwhile;
    else :
        ?><div class="bh-empty"><h2>No groups found</h2><p>Try changing your search or filters.</p><button type="button" class="bh-reset-inline">Clear filters</button></div><?php
    endif;
    wp_reset_postdata();
    return ob_get_clean();
}

function bubbahub_directory_render_pagination( $query ) {
    if ( $query->max_num_pages <= 1 ) return '';
    return wp_kses_post( paginate_links( array( 'total' => $query->max_num_pages, 'current' => max( 1, $query->get( 'paged' ) ), 'type' => 'list', 'prev_text' => '‹', 'next_text' => '›' ) ) );
}

function bubbahub_directory_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'posts_per_page' => 12 ), $atts, 'bubbahub_directory' );
    $filters = array(
        'search' => isset( $_GET['bh_search'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_search'] ) ) : '',
        'region' => isset( $_GET['bh_region'] ) ? sanitize_title( wp_unslash( $_GET['bh_region'] ) ) : '',
        'age' => isset( $_GET['bh_age'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_age'] ) ) : '',
        'price' => isset( $_GET['bh_price'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_price'] ) ) : '',
        'paged' => max( 1, get_query_var( 'paged', 1 ) ),
        'posts_per_page' => (int) $atts['posts_per_page'],
    );
    $query = bubbahub_directory_query( $filters );
    $regions = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => true ) );
    $age_values = bubbahub_directory_age_values();

    wp_enqueue_style( 'bubbahub-directory' );
    wp_enqueue_style( 'leaflet' );
    wp_enqueue_script( 'leaflet' );
    wp_enqueue_script( 'bubbahub-directory' );
    wp_localize_script( 'bubbahub-directory', 'BubbaHubDirectory', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'bubbahub_directory' ),
        'postsPerPage' => (int) $atts['posts_per_page'],
    ) );

    ob_start(); ?>
    <div class="bh-directory" data-default-columns="4">
        <form class="bh-searchbar" method="get">
            <div class="bh-search-main">
                <label class="bh-search-field"><span class="bh-sr-only">Search</span><input type="search" name="bh_search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Search groups, classes & activities..."></label>
                <button class="bh-search-button" type="submit">Search</button>
                <button class="bh-advanced-toggle" type="button" aria-expanded="false">Advanced search <span aria-hidden="true">⌄</span></button>
            </div>
            <div class="bh-advanced" hidden>
                <div><label for="bh-region">Location</label><select id="bh-region" name="bh_region"><option value="">All locations</option><?php if ( ! is_wp_error( $regions ) ) foreach ( $regions as $term ) : ?><option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $filters['region'], $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select></div>
                <div><label for="bh-age">Age range</label><select id="bh-age" name="bh_age"><option value="">All ages</option><?php foreach ( $age_values as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['age'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select></div>
                <div><label for="bh-price">Price</label><select id="bh-price" name="bh_price"><option value="">Any price</option><option value="free" <?php selected( $filters['price'], 'free' ); ?>>Free</option><option value="paid" <?php selected( $filters['price'], 'paid' ); ?>>Paid</option></select></div>
                <button type="submit" class="bh-apply">Apply filters</button>
            </div>
        </form>
        <div class="bh-toolbar">
            <div class="bh-results-count" aria-live="polite"><strong><?php echo esc_html( number_format_i18n( $query->found_posts ) ); ?></strong> groups</div>
            <div class="bh-view-controls" role="group" aria-label="Directory view"><span class="bh-view-label">View</span><?php foreach ( array( 2,3,4,5,6 ) as $columns ) : ?><button type="button" class="bh-cols <?php echo $columns === 4 ? 'is-active' : ''; ?>" data-columns="<?php echo $columns; ?>" aria-label="<?php echo $columns; ?> columns"><?php echo $columns; ?></button><?php endforeach; ?><button type="button" class="bh-map-toggle" data-view="map">Map</button></div>
        </div>
        <div class="bh-loading" hidden><span class="bh-spinner" aria-hidden="true"></span> Updating groups…</div>
        <div class="bh-content"><div class="bh-grid" data-columns="4"><?php echo bubbahub_directory_render_cards( $query ); ?></div><div class="bh-map" id="bh-directory-map" hidden aria-label="Map showing groups"></div></div>
        <nav class="bh-pagination" aria-label="Directory pagination"><?php echo bubbahub_directory_render_pagination( $query ); ?></nav>
    </div>
    <?php return ob_get_clean();
}

function bubbahub_directory_ajax_filter() {
    check_ajax_referer( 'bubbahub_directory', 'nonce' );
    $filters = array(
        'search' => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
        'region' => isset( $_POST['region'] ) ? sanitize_title( wp_unslash( $_POST['region'] ) ) : '',
        'age' => isset( $_POST['age'] ) ? sanitize_text_field( wp_unslash( $_POST['age'] ) ) : '',
        'price' => isset( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : '',
        'paged' => isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1,
        'posts_per_page' => isset( $_POST['postsPerPage'] ) ? (int) $_POST['postsPerPage'] : 12,
    );
    $query = bubbahub_directory_query( $filters );
    wp_send_json_success( array(
        'html' => bubbahub_directory_render_cards( $query ),
        'pagination' => bubbahub_directory_render_pagination( $query ),
        'count' => (int) $query->found_posts,
    ) );
}
