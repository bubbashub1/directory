<?php
/**
 * Plugin Name: BubbaHub Group Page
 * Description: Custom single-page template for Group listings in BubbaHub Directory.
 * Version: 1.0.0
 * Author: BubbaHub
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BUBBAHUB_GROUP_PAGE_VERSION', '1.0.0' );
define( 'BUBBAHUB_GROUP_PAGE_URL', plugin_dir_url( __FILE__ ) );

add_filter( 'template_include', 'bubbahub_group_page_template', 99 );
add_action( 'wp_enqueue_scripts', 'bubbahub_group_page_assets' );
add_action( 'wp_ajax_bubbahub_group_alternatives', 'bubbahub_group_alternatives_ajax' );
add_action( 'wp_ajax_nopriv_bubbahub_group_alternatives', 'bubbahub_group_alternatives_ajax' );

function bubbahub_group_page_template( $template ) {
    if ( is_singular( 'group' ) ) {
        $custom = plugin_dir_path( __FILE__ ) . 'templates/single-group.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    return $template;
}

function bubbahub_group_page_assets() {
    if ( ! is_singular( 'group' ) ) return;
    wp_enqueue_style( 'bubbahub-group-page', BUBBAHUB_GROUP_PAGE_URL . 'assets/group-page.css', array(), BUBBAHUB_GROUP_PAGE_VERSION );
    wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
    wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
    wp_enqueue_script( 'bubbahub-group-page', BUBBAHUB_GROUP_PAGE_URL . 'assets/group-page.js', array( 'jquery', 'leaflet' ), BUBBAHUB_GROUP_PAGE_VERSION, true );
    wp_localize_script( 'bubbahub-group-page', 'BubbaHubGroupPage', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'bubbahub_group_page' ),
        'backUrl' => function_exists( 'wp_get_referer' ) && wp_get_referer() ? wp_get_referer() : home_url( '/directory/' ),
    ) );
}

function bubbahub_group_get_field( $post_id, $name, $default = '' ) {
    if ( function_exists( 'get_field' ) ) {
        $value = get_field( $name, $post_id );
        if ( $value !== null && $value !== false && $value !== '' ) return $value;
    }
    $value = get_post_meta( $post_id, $name, true );
    return ( $value !== '' && $value !== false ) ? $value : $default;
}

function bubbahub_group_term_time( $post_id ) {
    $value = bubbahub_group_get_field( $post_id, 'term_time', false );
    if ( is_bool( $value ) ) return $value;
    if ( is_numeric( $value ) ) return (bool) $value;
    if ( is_string( $value ) ) return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on', 'term time', 'term-time' ), true );
    return ! empty( $value );
}

function bubbahub_group_image( $post_id ) {
    $gallery = bubbahub_group_get_field( $post_id, 'image', array() );
    if ( is_array( $gallery ) ) {
        $first = reset( $gallery );
        if ( is_array( $first ) && ! empty( $first['url'] ) ) return $first['url'];
        if ( is_numeric( $first ) ) { $url = wp_get_attachment_image_url( (int) $first, 'full' ); if ( $url ) return $url; }
        if ( is_object( $first ) && ! empty( $first->ID ) ) { $url = wp_get_attachment_image_url( (int) $first->ID, 'full' ); if ( $url ) return $url; }
    }
    return get_the_post_thumbnail_url( $post_id, 'full' ) ?: '';
}

function bubbahub_group_normalise_map( $map ) {
    if ( is_array( $map ) ) {
        $lat = isset( $map['lat'] ) ? $map['lat'] : ( isset( $map['latitude'] ) ? $map['latitude'] : '' );
        $lng = isset( $map['lng'] ) ? $map['lng'] : ( isset( $map['longitude'] ) ? $map['longitude'] : '' );
        if ( $lat !== '' && $lng !== '' ) return array( 'lat' => (float) $lat, 'lng' => (float) $lng );
    }
    if ( ! is_string( $map ) || $map === '' ) return null;
    $source = '';
    if ( preg_match( '/<iframe[^>]+src=[\"\']([^\"\']+)[\"\']/i', $map, $match ) ) {
        $source = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
    } else {
        $source = $map;
    }
    $decoded = urldecode( $source );
    if ( preg_match( '/(?:[?&])marker=([-+]?\d+(?:\.\d+)?)[, ]([-+]?\d+(?:\.\d+)?)/i', $decoded, $m ) ) {
        return array( 'lat' => (float) $m[1], 'lng' => (float) $m[2] );
    }
    $mlat = $mlon = '';
    if ( preg_match( '/(?:[?&])mlat=([-+]?\d+(?:\.\d+)?)/i', $decoded, $m ) ) $mlat = $m[1];
    if ( preg_match( '/(?:[?&])mlon=([-+]?\d+(?:\.\d+)?)/i', $decoded, $m ) ) $mlon = $m[1];
    if ( $mlat !== '' && $mlon !== '' ) return array( 'lat' => (float) $mlat, 'lng' => (float) $mlon );
    return null;
}

function bubbahub_group_venue_id( $post_id ) {
    $venue = bubbahub_group_get_field( $post_id, 'venue', '' );
    if ( is_object( $venue ) && ! empty( $venue->ID ) ) return (int) $venue->ID;
    if ( is_array( $venue ) && isset( $venue['ID'] ) ) return (int) $venue['ID'];
    if ( is_array( $venue ) && isset( $venue[0] ) ) return (int) $venue[0];
    return is_numeric( $venue ) ? (int) $venue : 0;
}

function bubbahub_group_venue_address( $venue_id ) {
    if ( ! $venue_id ) return '';
    $address = bubbahub_group_get_field( $venue_id, 'address', '' );
    if ( is_array( $address ) ) {
        $parts = array();
        foreach ( array( 'address', 'street', 'city', 'region', 'postcode', 'zip' ) as $key ) if ( ! empty( $address[$key] ) ) $parts[] = $address[$key];
        $address = implode( ', ', $parts );
    }
    if ( is_string( $address ) && trim( $address ) !== '' ) return $address;
    return get_the_title( $venue_id );
}

function bubbahub_group_address( $post_id, $venue_id ) {
    $address = $venue_id ? bubbahub_group_venue_address( $venue_id ) : bubbahub_group_get_field( $post_id, 'address', '' );
    if ( is_array( $address ) ) {
        $parts = array();
        foreach ( array( 'address', 'street', 'city', 'region', 'postcode', 'zip' ) as $key ) if ( ! empty( $address[$key] ) ) $parts[] = $address[$key];
        $address = implode( ', ', $parts );
    }
    return is_string( $address ) ? trim( $address ) : '';
}

function bubbahub_group_venue_url( $venue_id ) {
    return $venue_id ? get_permalink( $venue_id ) : '';
}

function bubbahub_group_can_edit( $post_id ) {
    if ( ! is_user_logged_in() ) return false;
    $user = wp_get_current_user();
    if ( ! $user || ! $user->ID ) return false;
    $roles = array_map( 'strtolower', (array) $user->roles );
    if ( ! array_intersect( array( 'leader', 'leaderpro' ), $roles ) ) return false;
    $author = (int) get_post_field( 'post_author', $post_id );
    return $author === (int) $user->ID || current_user_can( 'edit_post', $post_id );
}

function bubbahub_group_category( $post_id ) {
    $taxonomies = array( 'group_category', 'category' );
    foreach ( $taxonomies as $taxonomy ) {
        if ( ! taxonomy_exists( $taxonomy ) ) continue;
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) return implode( ', ', wp_list_pluck( $terms, 'name' ) );
    }
    return '';
}

function bubbahub_group_tags( $post_id ) {
    $terms = get_the_terms( $post_id, 'post_tag' );
    return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms : array();
}

function bubbahub_group_format_value( $value ) {
    if ( is_array( $value ) ) {
        $out = array();
        foreach ( $value as $item ) {
            if ( is_array( $item ) ) $item = implode( ', ', array_filter( array_map( 'strval', $item ) ) );
            elseif ( is_object( $item ) && isset( $item->post_title ) ) $item = $item->post_title;
            if ( is_scalar( $item ) && trim( (string) $item ) !== '' ) $out[] = (string) $item;
        }
        return implode( ', ', $out );
    }
    return is_scalar( $value ) ? (string) $value : '';
}

function bubbahub_group_get_organiser_venues( $organiser_id ) {
    if ( ! $organiser_id || ! post_type_exists( 'venue' ) ) return array();
    return get_posts( array( 'post_type' => 'venue', 'post_status' => 'publish', 'posts_per_page' => -1, 'author' => $organiser_id, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
}

function bubbahub_group_related_query( $post_id, $organiser_id, $venue_id = 0 ) {
    $meta_query = array();
    if ( $venue_id ) $meta_query[] = array( 'key' => 'venue', 'value' => '"' . $venue_id . '"', 'compare' => 'LIKE' );
    $args = array( 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => 8, 'post__not_in' => array( $post_id ), 'author' => $organiser_id, 'orderby' => 'date', 'order' => 'DESC' );
    if ( $meta_query ) $args['meta_query'] = $meta_query;
    return new WP_Query( $args );
}

function bubbahub_group_related_markup( $query ) {
    ob_start();
    if ( $query->have_posts() ) :
        while ( $query->have_posts() ) : $query->the_post();
            $id = get_the_ID(); $image = bubbahub_group_image( $id );
            ?>
            <article class="bhg-related-card">
                <a href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener">
                    <div class="bhg-related-image"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title( $id ) ); ?>" loading="lazy"><?php endif; ?></div>
                    <div class="bhg-related-body"><h3><?php echo esc_html( get_the_title( $id ) ); ?></h3><span>Open class ↗</span></div>
                </a>
            </article>
            <?php
        endwhile;
    else :
        echo '<p class="bhg-muted">No other classes are available for this organiser.</p>';
    endif;
    wp_reset_postdata();
    return ob_get_clean();
}

function bubbahub_group_alternatives_ajax() {
    check_ajax_referer( 'bubbahub_group_page', 'nonce' );
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $venue_id = isset( $_POST['venue_id'] ) ? absint( $_POST['venue_id'] ) : 0;
    if ( ! $post_id || get_post_type( $post_id ) !== 'group' ) wp_send_json_error( array( 'message' => 'Invalid group.' ), 400 );
    $organiser_id = (int) get_post_field( 'post_author', $post_id );
    $query = bubbahub_group_related_query( $post_id, $organiser_id, $venue_id );
    wp_send_json_success( array( 'html' => bubbahub_group_related_markup( $query ) ) );
}
