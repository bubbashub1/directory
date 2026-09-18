<?php
/**
 * BubbaHub Directory — Group single-page integration.
 *
 * This file is an internal include of the main BubbaHub Directory plugin.
 * It intentionally contains NO WordPress plugin header so WordPress does not
 * register it as a second plugin.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'BUBBAHUB_DIRECTORY_VERSION' ) ) {
    return;
}

if ( ! defined( 'BUBBAHUB_DIRECTORY_PATH' ) ) {
    define( 'BUBBAHUB_DIRECTORY_PATH', plugin_dir_path( __FILE__ ) );
}

/*
 * IMPORTANT:
 * The Group post type is registered by the existing site/ACF setup.
 * This file does NOT register another "group" post type.
 * It only replaces the single Group template when WordPress is displaying
 * an existing Group post, e.g. /group/demo-group/.
 */
add_filter( 'the_content', 'bubbahub_group_page_content', 20 );
add_action( 'wp_enqueue_scripts', 'bubbahub_group_page_assets', 20 );
add_filter( 'body_class', 'bubbahub_group_page_body_class' );
add_action( 'wp_ajax_bubbahub_group_alternatives', 'bubbahub_group_alternatives_ajax' );
add_action( 'wp_ajax_nopriv_bubbahub_group_alternatives', 'bubbahub_group_alternatives_ajax' );

function bubbahub_group_page_content( $content ) {
    if ( ! is_singular( 'group' ) || ! in_the_loop() || ! is_main_query() ) return $content;

    $custom = BUBBAHUB_DIRECTORY_PATH . 'templates/single-group.php';
    if ( ! is_readable( $custom ) ) return $content;

    ob_start();
    include $custom;
    return ob_get_clean();
}

function bubbahub_group_page_body_class( $classes ) {
    if ( is_singular( 'group' ) ) {
        $classes[] = 'bubbahub-group-page';
    }
    return $classes;
}

function bubbahub_group_page_assets() {
    if ( ! is_singular( 'group' ) ) return;

    wp_enqueue_style(
        'bubbahub-group-page',
        BUBBAHUB_DIRECTORY_URL . 'assets/group-page.css',
        array(),
        BUBBAHUB_DIRECTORY_VERSION
    );

    wp_enqueue_style(
        'bubbahub-leaflet',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        array(),
        '1.9.4'
    );

    wp_enqueue_script(
        'bubbahub-leaflet',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        array(),
        '1.9.4',
        true
    );

    wp_enqueue_script(
        'bubbahub-group-page',
        BUBBAHUB_DIRECTORY_URL . 'assets/group-page.js',
        array( 'jquery', 'bubbahub-leaflet' ),
        BUBBAHUB_DIRECTORY_VERSION,
        true
    );

    wp_localize_script(
        'bubbahub-group-page',
        'BubbaHubGroupPage',
        array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bubbahub_group_page' ),
            'backUrl' => bubbahub_group_page_directory_url(),
        )
    );
}

function bubbahub_group_page_directory_url() {
    $referer = wp_get_referer();

    if ( $referer ) {
        $referer_path = wp_parse_url( $referer, PHP_URL_PATH );
        if ( $referer_path && strpos( $referer_path, '/group/' ) === false ) {
            return $referer;
        }
    }

    return home_url( '/directory/' );
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

function bubbahub_group_images( $post_id ) {
    $ids = get_post_meta( $post_id, '_bubbahub_gallery_ids', true );
    $ids = is_array( $ids ) ? array_map( 'absint', $ids ) : array();

    $gallery = bubbahub_group_get_field( $post_id, 'image', array() );
    if ( is_array( $gallery ) ) {
        foreach ( $gallery as $item ) {
            $id = 0;
            if ( is_numeric( $item ) ) $id = absint( $item );
            elseif ( is_array( $item ) && ! empty( $item['ID'] ) ) $id = absint( $item['ID'] );
            elseif ( is_array( $item ) && ! empty( $item['id'] ) ) $id = absint( $item['id'] );
            elseif ( is_object( $item ) && ! empty( $item->ID ) ) $id = absint( $item->ID );
            if ( $id ) $ids[] = $id;
        }
    }

    $thumb = get_post_thumbnail_id( $post_id );
    if ( $thumb ) array_unshift( $ids, (int) $thumb );

    $ids = array_values( array_unique( array_filter( $ids ) ) );
    $images = array();

    foreach ( $ids as $id ) {
        $url = wp_get_attachment_image_url( $id, 'full' );
        if ( $url ) $images[] = array( 'id' => $id, 'url' => $url );
    }

    if ( ! $images ) {
        $legacy = get_post_meta( $post_id, 'image_url', true );
        if ( is_string( $legacy ) && filter_var( trim( $legacy ), FILTER_VALIDATE_URL ) ) {
            $images[] = array( 'id' => 0, 'url' => trim( $legacy ) );
        }
    }

    return $images;
}

function bubbahub_group_image( $post_id ) {
    $images = bubbahub_group_images( $post_id );
    return ! empty( $images[0]['url'] ) ? $images[0]['url'] : '';
}

function bubbahub_group_normalise_map( $map ) {
    // Bubba Hub uses OpenStreetMap/Leaflet. Accept OSM coordinate formats as
    // well as ACF-style arrays so the public map can use the exact saved
    // latitude/longitude without requiring Google Maps.
    if ( is_array( $map ) ) {
        $lat_keys = array( 'lat', 'latitude', 'osm_lat', 'osm_latitude', 'map_lat', 'location_lat', 'venue_lat' );
        $lng_keys = array( 'lng', 'lon', 'longitude', 'osm_lng', 'osm_lon', 'osm_longitude', 'map_lng', 'location_lng', 'venue_lng' );
        $lat = $lng = '';
        foreach ( $lat_keys as $key ) {
            if ( isset( $map[ $key ] ) && is_numeric( $map[ $key ] ) ) { $lat = $map[ $key ]; break; }
        }
        foreach ( $lng_keys as $key ) {
            if ( isset( $map[ $key ] ) && is_numeric( $map[ $key ] ) ) { $lng = $map[ $key ]; break; }
        }
        if ( $lat !== '' && $lng !== '' ) return array( 'lat' => (float) $lat, 'lng' => (float) $lng );

        // Some OSM/address fields are nested arrays. Walk the complete
        // structure instead of assuming the coordinates are at the first level.
        foreach ( $map as $nested ) {
            $coords = bubbahub_group_normalise_map( $nested );
            if ( $coords ) return $coords;
        }
        return null;
    }

    if ( is_string( $map ) && $map !== '' ) {
        $trimmed = trim( $map );
        $json = json_decode( $trimmed, true );
        if ( is_array( $json ) ) {
            $coords = bubbahub_group_normalise_map( $json );
            if ( $coords ) return $coords;
        }
        $unserialised = maybe_unserialize( $trimmed );
        if ( is_array( $unserialised ) ) {
            $coords = bubbahub_group_normalise_map( $unserialised );
            if ( $coords ) return $coords;
        }
    }

    if ( ! is_string( $map ) || $map === '' ) return null;

    $source = '';
    if ( preg_match( '/<iframe[^>]+src=[\\"\\']([^\\"\\']+)[\\"\\']/i', $map, $match ) ) {
        $source = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
    } elseif ( preg_match( '/https?:\\/\\/[^\\s\\"\\']*openstreetmap\\.org[^\\s\\"\\']*/i', $map, $url_match ) ) {
        $source = html_entity_decode( $url_match[0], ENT_QUOTES, 'UTF-8' );
    } else {
        $source = $map;
    }

    $decoded = urldecode( $source );

    if ( preg_match( '/(?:[?&]|%3F|%26)marker=([-+]?\\d+(?:\\.\\d+)?)[, ]([-+]?\\d+(?:\\.\\d+)?)/i', $decoded, $m ) ) {
        return array( 'lat' => (float) $m[1], 'lng' => (float) $m[2] );
    }

    $mlat = $mlon = '';
    if ( preg_match( '/(?:[?&])mlat=([-+]?\\d+(?:\\.\\d+)?)/i', $decoded, $m ) ) $mlat = $m[1];
    if ( preg_match( '/(?:[?&])mlon=([-+]?\\d+(?:\\.\\d+)?)/i', $decoded, $m ) ) $mlon = $m[1];
    if ( $mlat !== '' && $mlon !== '' ) return array( 'lat' => (float) $mlat, 'lng' => (float) $mlon );

    if ( preg_match( '/^\\s*([-+]?\\d+(?:\\.\\d+)?)\\s*[, ]\\s*([-+]?\\d+(?:\\.\\d+)?)\\s*$/', trim( $decoded ), $coords ) ) {
        return array( 'lat' => (float) $coords[1], 'lng' => (float) $coords[2] );
    }

    return null;
}

function bubbahub_group_resolve_map( $post_id ) {
    // OpenStreetMap fields commonly used by the directory/importer.
    $map_fields = array( 'openstreetmap', 'open_street_map', 'osm', 'osm_map', 'osm_location', 'location', 'venue_location', 'map', 'map_location', 'location_map', 'google_map' );
    foreach ( $map_fields as $field_name ) {
        $value = bubbahub_group_get_field( $post_id, $field_name, '' );
        $coords = bubbahub_group_normalise_map( $value );
        if ( $coords ) return $coords;
    }

    // Explicit OSM/import coordinate fields.
    $lat_fields = array( 'latitude', 'lat', 'osm_lat', 'osm_latitude', 'map_lat', 'location_lat', 'venue_lat' );
    $lng_fields = array( 'longitude', 'lng', 'lon', 'osm_lng', 'osm_longitude', 'map_lng', 'location_lng', 'venue_lng' );
    $lat = $lng = '';
    foreach ( $lat_fields as $field_name ) {
        $lat = bubbahub_group_get_field( $post_id, $field_name, '' );
        if ( $lat !== '' ) break;
    }
    foreach ( $lng_fields as $field_name ) {
        $lng = bubbahub_group_get_field( $post_id, $field_name, '' );
        if ( $lng !== '' ) break;
    }
    if ( $lat !== '' && $lng !== '' && is_numeric( $lat ) && is_numeric( $lng ) ) {
        return array( 'lat' => (float) $lat, 'lng' => (float) $lng );
    }

    // Inspect all ACF fields, including deeply nested OSM/address structures.
    if ( function_exists( 'get_fields' ) ) {
        $all_fields = get_fields( $post_id );
        if ( is_array( $all_fields ) ) {
            $coords = bubbahub_group_normalise_map( $all_fields );
            if ( $coords ) return $coords;
        }
    }

    // Inspect raw post meta for imported/serialized OSM coordinates.
    $all_meta = get_post_meta( $post_id );
    if ( is_array( $all_meta ) ) {
        $coords = bubbahub_group_normalise_map( $all_meta );
        if ( $coords ) return $coords;
    }

    return null;
}

function bubbahub_group_venue_id( $post_id ) {
    $venue = bubbahub_group_get_field( $post_id, 'venue', '' );

    if ( is_object( $venue ) && ! empty( $venue->ID ) ) return (int) $venue->ID;
    if ( is_array( $venue ) && isset( $venue['ID'] ) ) return (int) $venue['ID'];
    if ( is_array( $venue ) && isset( $venue[0] ) ) return is_object( $venue[0] ) && isset( $venue[0]->ID ) ? (int) $venue[0]->ID : (int) $venue[0];

    return is_numeric( $venue ) ? (int) $venue : 0;
}

function bubbahub_group_venue_address( $venue_id ) {
    if ( ! $venue_id ) return '';

    $address = bubbahub_group_get_field( $venue_id, 'address', '' );

    if ( is_array( $address ) ) {
        $parts = array();
        foreach ( array( 'address', 'street', 'city', 'region', 'postcode', 'zip' ) as $key ) {
            if ( ! empty( $address[$key] ) ) $parts[] = $address[$key];
        }
        $address = implode( ', ', $parts );
    }

    if ( is_string( $address ) && trim( $address ) !== '' ) return $address;

    return get_the_title( $venue_id );
}

function bubbahub_group_address( $post_id, $venue_id ) {
    $address = $venue_id ? bubbahub_group_venue_address( $venue_id ) : bubbahub_group_get_field( $post_id, 'address', '' );

    if ( is_array( $address ) ) {
        $parts = array();
        foreach ( array( 'address', 'street', 'city', 'region', 'postcode', 'zip' ) as $key ) {
            if ( ! empty( $address[$key] ) ) $parts[] = $address[$key];
        }
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
    foreach ( array( 'group_category', 'category' ) as $taxonomy ) {
        if ( ! taxonomy_exists( $taxonomy ) ) continue;

        $terms = get_the_terms( $post_id, $taxonomy );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            return implode( ', ', wp_list_pluck( $terms, 'name' ) );
        }
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
            if ( is_array( $item ) ) {
                $item = implode( ', ', array_filter( array_map( 'strval', $item ) ) );
            } elseif ( is_object( $item ) && isset( $item->post_title ) ) {
                $item = $item->post_title;
            }

            if ( is_scalar( $item ) && trim( (string) $item ) !== '' ) $out[] = (string) $item;
        }

        return implode( ', ', $out );
    }

    return is_scalar( $value ) ? (string) $value : '';
}

function bubbahub_group_get_organiser_venues( $organiser_id ) {
    if ( ! $organiser_id || ! post_type_exists( 'venue' ) ) return array();

    return get_posts( array(
        'post_type'      => 'venue',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'author'         => $organiser_id,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ) );
}

function bubbahub_group_related_query( $post_id, $organiser_id, $venue_id = 0 ) {
    $args = array(
        'post_type'      => 'group',
        'post_status'    => 'publish',
        'posts_per_page' => 8,
        'post__not_in'   => array( $post_id ),
        'author'         => $organiser_id,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    if ( $venue_id ) {
        $args['meta_query'] = array(
            array(
                'key'     => 'venue',
                'value'   => '\"' . $venue_id . '\"',
                'compare' => 'LIKE',
            ),
        );
    }

    return new WP_Query( $args );
}

function bubbahub_group_related_markup( $query ) {
    ob_start();

    if ( $query->have_posts() ) :
        while ( $query->have_posts() ) : $query->the_post();
            $id    = get_the_ID();
            $image = bubbahub_group_image( $id );
            ?>
            <article class="bhg-related-card">
                <a href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener">
                    <div class="bhg-related-image">
                        <?php if ( $image ) : ?>
                            <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title( $id ) ); ?>" loading="lazy">
                        <?php endif; ?>
                    </div>
                    <div class="bhg-related-body">
                        <h3><?php echo esc_html( get_the_title( $id ) ); ?></h3>
                        <span>Open class ↗</span>
                    </div>
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

    $post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $venue_id = isset( $_POST['venue_id'] ) ? absint( $_POST['venue_id'] ) : 0;

    if ( ! $post_id || get_post_type( $post_id ) !== 'group' ) {
        wp_send_json_error( array( 'message' => 'Invalid group.' ), 400 );
    }

    $organiser_id = (int) get_post_field( 'post_author', $post_id );
    $query        = bubbahub_group_related_query( $post_id, $organiser_id, $venue_id );

    wp_send_json_success( array( 'html' => bubbahub_group_related_markup( $query ) ) );
}
