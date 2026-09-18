<?php
/**
 * Plugin Name: BubbaHub Directory
 * Description: Front-end directory for the Group custom post type with ACF-powered cards, advanced search, responsive grid controls, map view and a configurable drag-and-drop layout.
 * Version: 1.3.0
 * Author: BubbaHub
 * Requires PHP: 7.4
 */
if ( ! defined( 'ABSPATH' ) ) exit;
define( 'BUBBAHUB_DIRECTORY_VERSION', '1.3.0' );
define( 'BUBBAHUB_DIRECTORY_URL', plugin_dir_url( __FILE__ ) );

require_once plugin_dir_path( __FILE__ ) . 'bubbahub-group-template.php';

// Load the platform stack directly from the main plugin bootstrap. This keeps
// booking, subscription, schedule and My Hub shortcodes available even when
// the Leader Portal loader is not initialised on the current request.
$bh_platform_loader = plugin_dir_path( __FILE__ ) . 'modules/core/bubbahub-platform-loader.php';
if ( file_exists( $bh_platform_loader ) ) {
    require_once $bh_platform_loader;
}

require_once plugin_dir_path( __FILE__ ) . 'myhub/myhub.php';
require_once plugin_dir_path( __FILE__ ) . 'leader/bubbahub-leader-dashboard.php';

// Support Hub: family questions, specialist articles, useful links and apps.
$bh_support_module = plugin_dir_path( __FILE__ ) . 'modules/support/bubbahub-support.php';
if ( file_exists( $bh_support_module ) ) require_once $bh_support_module;

if ( is_admin() ) {
    require_once plugin_dir_path( __FILE__ ) . 'admin/directory-builder.php';
}

// Listing CSV / Google Sheets import and export.
if ( is_admin() ) {
    $bh_csv_module = plugin_dir_path( __FILE__ ) . 'admin/listing-csv.php';
    if ( file_exists( $bh_csv_module ) ) require_once $bh_csv_module;
}

add_action( 'wp_enqueue_scripts', 'bubbahub_directory_assets' );
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
    if ( function_exists( 'get_field' ) ) { $value = get_field( $name, $post_id ); if ( $value !== null && $value !== false && $value !== '' ) return $value; }
    $value = get_post_meta( $post_id, $name, true );
    return ( $value !== '' && $value !== false ) ? $value : $default;
}
function bubbahub_directory_image_url( $post_id ) {
    $gallery = bubbahub_directory_get_field( $post_id, 'image', array() );
    if ( is_array( $gallery ) ) {
        $first = reset( $gallery );
        if ( is_array( $first ) && ! empty( $first['url'] ) ) return $first['url'];
        if ( is_numeric( $first ) ) { $url = wp_get_attachment_image_url( (int) $first, 'large' ); if ( $url ) return $url; }
        if ( is_object( $first ) && ! empty( $first->ID ) ) { $url = wp_get_attachment_image_url( (int) $first->ID, 'large' ); if ( $url ) return $url; }
    }
    return get_the_post_thumbnail_url( $post_id, 'large' ) ?: '';
}
function bubbahub_directory_normalise_map( $map ) {
    if ( is_array( $map ) ) {
        $lat = isset( $map['lat'] ) ? $map['lat'] : ( isset( $map['latitude'] ) ? $map['latitude'] : '' );
        $lng = isset( $map['lng'] ) ? $map['lng'] : ( isset( $map['longitude'] ) ? $map['longitude'] : '' );
        if ( $lat !== '' && $lng !== '' ) return array( 'lat' => (float) $lat, 'lng' => (float) $lng );
    }
    if ( ! is_string( $map ) || $map === '' ) return null;
    $source = '';
    if ( preg_match( '/<iframe[^>]+src=[\"\']([^\"\']+)[\"\']/i', $map, $iframe_match ) ) $source = html_entity_decode( $iframe_match[1], ENT_QUOTES, 'UTF-8' );
    elseif ( preg_match( '/https?:\/\/[^\s\"\']*openstreetmap\.org[^\s\"\']*/i', $map, $url_match ) ) $source = html_entity_decode( $url_match[0], ENT_QUOTES, 'UTF-8' );
    else $source = $map;
    $decoded = urldecode( $source );
    if ( preg_match( '/(?:[?&]|%3F|%26)marker=([-+]?\d+(?:\.\d+)?)[, ]([-+]?\d+(?:\.\d+)?)/i', $decoded, $marker ) ) return array( 'lat' => (float) $marker[1], 'lng' => (float) $marker[2] );
    $mlat = ''; $mlon = '';
    if ( preg_match( '/(?:[?&])mlat=([-+]?\d+(?:\.\d+)?)/i', $decoded, $lat_match ) ) $mlat = $lat_match[1];
    if ( preg_match( '/(?:[?&])mlon=([-+]?\d+(?:\.\d+)?)/i', $decoded, $lon_match ) ) $mlon = $lon_match[1];
    if ( $mlat !== '' && $mlon !== '' ) return array( 'lat' => (float) $mlat, 'lng' => (float) $mlon );
    if ( preg_match( '/^\s*([-+]?\d+(?:\.\d+)?)\s*[, ]\s*([-+]?\d+(?:\.\d+)?)\s*$/', trim( $decoded ), $coords ) ) return array( 'lat' => (float) $coords[1], 'lng' => (float) $coords[2] );
    return null;
}
function bubbahub_directory_region( $post_id ) {
    $terms = get_the_terms( $post_id, 'region' );
    if ( is_wp_error( $terms ) || empty( $terms ) ) return '';
    return implode( ', ', wp_list_pluck( $terms, 'name' ) );
}
function bubbahub_directory_age_values() {
    $values = array();
    $ids = get_posts( array( 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
    foreach ( $ids as $id ) { $value = bubbahub_directory_get_field( $id, 'age_range' ); if ( is_array( $value ) ) $value = implode( ', ', array_map( 'sanitize_text_field', $value ) ); if ( is_string( $value ) && $value !== '' ) $values[] = $value; }
    $values = array_unique( $values ); natcasesort( $values ); return $values;
}
function bubbahub_directory_query( $filters = array() ) {
    $search = isset( $filters['search'] ) ? sanitize_text_field( $filters['search'] ) : '';
    $region = isset( $filters['region'] ) ? sanitize_title( $filters['region'] ) : '';
    $age = isset( $filters['age'] ) ? sanitize_text_field( $filters['age'] ) : '';
    $price = isset( $filters['price'] ) ? sanitize_text_field( $filters['price'] ) : '';
    $paged = isset( $filters['paged'] ) ? max( 1, (int) $filters['paged'] ) : 1;
    $per_page = isset( $filters['posts_per_page'] ) ? max( 1, min( 100, (int) $filters['posts_per_page'] ) ) : 12;
    $args = array( 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => $per_page, 'paged' => $paged, 's' => $search, 'orderby' => 'date', 'order' => 'DESC' );
    if ( $region ) $args['tax_query'] = array( array( 'taxonomy' => 'region', 'field' => 'slug', 'terms' => $region ) );
    $meta_query = array();
    if ( $age ) $meta_query[] = array( 'key' => 'age_range', 'value' => $age, 'compare' => 'LIKE' );
    if ( $price === 'free' ) $meta_query[] = array( 'key' => 'price', 'value' => 'Free', 'compare' => 'LIKE' );
    if ( $price === 'paid' ) $meta_query[] = array( 'key' => 'price', 'value' => 'Free', 'compare' => 'NOT LIKE' );
    if ( $meta_query ) $args['meta_query'] = $meta_query;
    return new WP_Query( $args );
}
function bubbahub_directory_term_time( $post_id ) {
    $value = bubbahub_directory_get_field( $post_id, 'term_time', false );
    if ( is_bool( $value ) ) return $value;
    if ( is_numeric( $value ) ) return (bool) $value;
    if ( is_string( $value ) ) return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on', 'term time', 'term-time' ), true );
    return ! empty( $value );
}
function bubbahub_directory_format_value( $value ) {
    if ( is_array( $value ) ) {
        $out = array();
        foreach ( $value as $item ) {
            if ( is_array( $item ) ) {
                $parts = array();
                foreach ( $item as $key => $part ) { if ( is_scalar( $part ) && trim( (string) $part ) !== '' ) $parts[] = is_string( $key ) ? ucwords( str_replace( array( '_', '-' ), ' ', $key ) ) . ': ' . $part : (string) $part; }
                $item = implode( ' · ', $parts );
            } elseif ( is_object( $item ) && isset( $item->post_title ) ) { $item = $item->post_title; }
            if ( is_scalar( $item ) && trim( (string) $item ) !== '' ) $out[] = (string) $item;
        }
        return implode( ', ', $out );
    }
    return is_scalar( $value ) ? (string) $value : '';
}
function bubbahub_directory_layout() {
    $layout = get_option( 'bubbahub_directory_builder_layout', array() );
    if ( ! is_array( $layout ) || empty( $layout ) ) {
        $layout = array(
            array( 'type' => 'title' ), array( 'type' => 'image' ), array( 'type' => 'description' ),
            array( 'type' => 'location' ), array( 'type' => 'schedule' ), array( 'type' => 'price' ),
            array( 'type' => 'booking' ), array( 'type' => 'other_classes' ), array( 'type' => 'map' ),
        );
    }
    return $layout;
}
function bubbahub_directory_render_element( $type, $id, $data ) {
    $title = $data['title']; $url = $data['url'];
    switch ( $type ) {
        case 'image':
            return $data['image'] ? '<div class="bh-builder-element bh-builder-image"><a href="' . esc_url( $url ) . '"><img src="' . esc_url( $data['image'] ) . '" alt="' . esc_attr( $title ) . '" loading="lazy"></a></div>' : '';
        case 'title':
            return '<div class="bh-builder-element bh-builder-title"><h2><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></h2></div>';
        case 'description':
            $text = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $id ) ), 24 );
            return $text ? '<div class="bh-builder-element bh-builder-description">' . esc_html( $text ) . '</div>' : '';
        case 'location':
            $text = $data['region'];
            if ( $data['address'] ) $text = $text ? $text . ' · ' . $data['address'] : $data['address'];
            return $text ? '<div class="bh-builder-element bh-builder-location"><span class="bh-icon" aria-hidden="true">⌖</span>' . esc_html( $text ) . '</div>' : '';
        case 'schedule':
            $value = bubbahub_directory_get_field( $id, 'business_hours', '' );
            if ( $value === '' ) $value = bubbahub_directory_get_field( $id, 'schedule', '' );
            if ( $value === '' ) $value = bubbahub_directory_get_field( $id, 'timetable', '' );
            $text = bubbahub_directory_format_value( $value );
            return $text ? '<div class="bh-builder-element bh-builder-schedule"><strong>Schedule</strong><span>' . esc_html( $text ) . '</span></div>' : '';
        case 'price':
            return $data['price'] ? '<div class="bh-builder-element bh-builder-price"><span>' . esc_html( $data['price'] ) . '</span></div>' : '';
        case 'booking':
            if ( shortcode_exists( 'bubbahub_booking' ) ) return '<div class="bh-builder-element bh-builder-booking">' . do_shortcode( '[bubbahub_booking group_id="' . absint( $id ) . '"]' ) . '</div>';
            if ( shortcode_exists( 'bubbahub_bookings' ) ) return '<div class="bh-builder-element bh-builder-booking">' . do_shortcode( '[bubbahub_bookings group_id="' . absint( $id ) . '"]' ) . '</div>';
            return '<div class="bh-builder-element bh-builder-booking"><a class="bh-view-more" href="' . esc_url( $url ) . '">View booking options →</a></div>';
        case 'subscription':
            if ( shortcode_exists( 'bubbahub_subscription' ) ) return '<div class="bh-builder-element bh-builder-subscription">' . do_shortcode( '[bubbahub_subscription]' ) . '</div>';
            if ( shortcode_exists( 'bubbahub_subscriptions' ) ) return '<div class="bh-builder-element bh-builder-subscription">' . do_shortcode( '[bubbahub_subscriptions]' ) . '</div>';
            return '<div class="bh-builder-element bh-builder-subscription"><a class="bh-view-more" href="' . esc_url( $url ) . '">Membership options →</a></div>';
        case 'other_classes':
            $author = (int) get_post_field( 'post_author', $id );
            if ( ! $author ) return '';
            $related = new WP_Query( array( 'post_type' => 'group', 'post_status' => 'publish', 'posts_per_page' => 3, 'post__not_in' => array( $id ), 'author' => $author, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) );
            if ( ! $related->have_posts() ) { wp_reset_postdata(); return ''; }
            $html = '<div class="bh-builder-element bh-builder-other"><strong>Other classes</strong><ul>';
            while ( $related->have_posts() ) { $related->the_post(); $html .= '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>'; }
            $html .= '</ul></div>'; wp_reset_postdata(); return $html;
        case 'map':
            if ( ! $data['map'] ) return '';
            return '<div class="bh-builder-element bh-builder-map"><a href="https://www.openstreetmap.org/?mlat=' . rawurlencode( $data['map']['lat'] ) . '&mlon=' . rawurlencode( $data['map']['lng'] ) . '#map=16/' . rawurlencode( $data['map']['lat'] ) . '/' . rawurlencode( $data['map']['lng'] ) . '" target="_blank" rel="noopener">View location on map ↗</a></div>';
        case 'contact':
            $email = bubbahub_directory_get_field( $id, 'email', '' ); $phone = bubbahub_directory_get_field( $id, 'phone', '' ); $website = bubbahub_directory_get_field( $id, 'website', '' );
            $links = array();
            if ( $email && is_email( $email ) ) $links[] = '<a href="mailto:' . antispambot( $email ) . '">Email</a>';
            if ( $phone ) $links[] = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">Call</a>';
            if ( $website && filter_var( $website, FILTER_VALIDATE_URL ) ) $links[] = '<a href="' . esc_url( $website ) . '" target="_blank" rel="noopener">Website</a>';
            return $links ? '<div class="bh-builder-element bh-builder-contact">' . implode( ' · ', $links ) . '</div>' : '';
        case 'social':
            $socials = array(); foreach ( array( 'facebook', 'instagram', 'twitter', 'tiktok', 'social_url' ) as $field ) { $value = bubbahub_directory_get_field( $id, $field, '' ); if ( $value && filter_var( $value, FILTER_VALIDATE_URL ) ) $socials[] = '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener">' . esc_html( ucfirst( str_replace( '_', ' ', $field ) ) ) . '</a>'; }
            return $socials ? '<div class="bh-builder-element bh-builder-social">' . implode( ' · ', $socials ) . '</div>' : '';
        case 'search':
            return '<div class="bh-builder-element bh-builder-search-note">Search &amp; filters are available above the directory.</div>';
    }
    return '';
}
function bubbahub_directory_sub_region( $post_id ) {
    if ( ! taxonomy_exists( 'region' ) ) return '';
    $terms = get_the_terms( $post_id, 'region' );
    if ( is_wp_error( $terms ) || empty( $terms ) ) return '';
    $children = array();
    foreach ( $terms as $term ) {
        if ( (int) $term->parent > 0 ) $children[] = $term->name;
    }
    return implode( ', ', $children );
}

function bubbahub_directory_open_days( $post_id ) {
    $hours = bubbahub_directory_get_field( $post_id, 'business_hours', '' );
    if ( is_array( $hours ) ) $hours = implode( '; ', $hours );
    if ( ! is_string( $hours ) || '' === trim( $hours ) ) return '';
    $days = array();
    if ( preg_match_all( '/(?:^|[;\\n|])\\s*(Mon|Monday|Tue|Tuesday|Wed|Wednesday|Thu|Thursday|Fri|Friday|Sat|Saturday|Sun|Sunday)\\b/i', $hours, $matches ) ) {
        foreach ( $matches[1] as $day ) {
            $key = strtolower( substr( $day, 0, 3 ) );
            $days[ $key ] = ucfirst( $key );
        }
    }
    return implode( ', ', array_values( $days ) );
}

function bubbahub_directory_render_cards( $query ) {
    ob_start();
    if ( $query->have_posts() ) : while ( $query->have_posts() ) : $query->the_post();
        $id = get_the_ID();
        $title = get_the_title();
        $url = get_permalink();
        $image = bubbahub_directory_image_url( $id );
        $region = '';
        $terms = taxonomy_exists( 'region' ) ? get_the_terms( $id, 'region' ) : array();
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            $parents = array();
            foreach ( $terms as $term ) {
                if ( (int) $term->parent === 0 ) $parents[] = $term->name;
            }
            $region = implode( ', ', $parents );
            if ( '' === $region ) $region = $terms[0]->name;
        }
        $sub_region = bubbahub_directory_sub_region( $id );
        $days = bubbahub_directory_open_days( $id );
        $age = bubbahub_directory_format_value( bubbahub_directory_get_field( $id, 'age_range', '' ) );
        $price = bubbahub_directory_format_value( bubbahub_directory_get_field( $id, 'price', '' ) );
        ?>
        <article class="bh-card bh-card-simple">
            <a class="bh-card-link" href="<?php echo esc_url( $url ); ?>">
                <div class="bh-card-image">
                    <?php if ( $image ) : ?>
                        <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
                    <?php else : ?>
                        <div class="bh-image-placeholder">BubbaHub</div>
                    <?php endif; ?>
                </div>
                <div class="bh-card-body">
                    <h2><?php echo esc_html( $title ); ?></h2>
                    <dl class="bh-card-details">
                        <?php if ( $region ) : ?><div><dt>Region</dt><dd><?php echo esc_html( $region ); ?></dd></div><?php endif; ?>
                        <?php if ( $sub_region ) : ?><div><dt>Sub-Region</dt><dd><?php echo esc_html( $sub_region ); ?></dd></div><?php endif; ?>
                        <?php if ( $days ) : ?><div><dt>Days Open</dt><dd><?php echo esc_html( $days ); ?></dd></div><?php endif; ?>
                        <?php if ( $age ) : ?><div><dt>Age Range</dt><dd><?php echo esc_html( $age ); ?></dd></div><?php endif; ?>
                        <?php if ( $price !== '' ) : ?><div><dt>Price (£)</dt><dd><?php echo esc_html( $price ); ?></dd></div><?php endif; ?>
                    </dl>
                </div>
            </a>
        </article>
        <?php
    endwhile; else : ?>
        <div class="bh-empty"><h2>No groups found</h2><p>Try changing your search or filters.</p><button type="button" class="bh-reset-inline">Clear filters</button></div>
    <?php endif;
    wp_reset_postdata();
    return ob_get_clean();
}
function bubbahub_directory_render_pagination( $query ) {
    if ( $query->max_num_pages <= 1 ) return '';
    return wp_kses_post( paginate_links( array( 'total' => $query->max_num_pages, 'current' => max( 1, $query->get( 'paged' ) ), 'type' => 'list', 'prev_text' => '‹', 'next_text' => '›' ) ) );
}
function bubbahub_directory_ajax_filter() {
    check_ajax_referer( 'bubbahub_directory', 'nonce' );
    $filters = array( 'search' => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '', 'region' => isset( $_POST['region'] ) ? sanitize_title( wp_unslash( $_POST['region'] ) ) : '', 'age' => isset( $_POST['age'] ) ? sanitize_text_field( wp_unslash( $_POST['age'] ) ) : '', 'price' => isset( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : '', 'paged' => isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1, 'posts_per_page' => isset( $_POST['postsPerPage'] ) ? max( 1, min( 100, (int) $_POST['postsPerPage'] ) ) : 12 );
    $query = bubbahub_directory_query( $filters );
    wp_send_json_success( array( 'html' => bubbahub_directory_render_cards( $query ), 'pagination' => bubbahub_directory_render_pagination( $query ), 'count' => (int) $query->found_posts ) );
}
function bubbahub_directory_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'posts_per_page' => 12 ), $atts, 'bubbahub_directory' );
    $filters = array( 'search' => isset( $_GET['bh_search'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_search'] ) ) : '', 'region' => isset( $_GET['bh_region'] ) ? sanitize_title( wp_unslash( $_GET['bh_region'] ) ) : '', 'age' => isset( $_GET['bh_age'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_age'] ) ) : '', 'price' => isset( $_GET['bh_price'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_price'] ) ) : '', 'paged' => max( 1, get_query_var( 'paged', 1 ) ), 'posts_per_page' => (int) $atts['posts_per_page'] );
    $query = bubbahub_directory_query( $filters ); $regions = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false ) ); $ages = bubbahub_directory_age_values();
    ob_start();
    wp_enqueue_style( 'bubbahub-directory' ); wp_enqueue_script( 'bubbahub-directory' ); wp_enqueue_style( 'leaflet' ); wp_enqueue_script( 'leaflet' );
    $nonce = wp_create_nonce( 'bubbahub_directory' );
    ?>
    <div class="bh-directory" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
        <form class="bh-search-form" method="get"><label for="bh-search">Search groups</label><input id="bh-search" name="bh_search" type="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Search by group, class or area"><select name="bh_region"><option value="">All areas</option><?php if ( ! is_wp_error( $regions ) ) foreach ( $regions as $term ) : ?><option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $filters['region'], $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option><?php endforeach; ?></select><select name="bh_age"><option value="">All ages</option><?php foreach ( $ages as $value ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['age'], $value ); ?>><?php echo esc_html( $value ); ?></option><?php endforeach; ?></select><select name="bh_price"><option value="">Any price</option><option value="free" <?php selected( $filters['price'], 'free' ); ?>>Free</option><option value="paid" <?php selected( $filters['price'], 'paid' ); ?>>Paid</option></select><button type="submit">Search</button></form>
        <div class="bh-directory-toolbar"><strong class="bh-result-count"><?php echo esc_html( number_format_i18n( $query->found_posts ) ); ?> groups</strong><button type="button" class="bh-view-toggle" data-view="grid">Grid / Map</button></div>
        <div class="bh-directory-content"><div class="bh-directory-results"><?php echo bubbahub_directory_render_cards( $query ); ?><?php echo bubbahub_directory_render_pagination( $query ); ?></div><div class="bh-directory-map" aria-label="Group map"></div></div>
    </div>
    <?php return ob_get_clean();
}
