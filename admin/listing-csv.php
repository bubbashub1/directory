<?php
/**
 * BubbaHub Directory - Listing CSV / Google Sheets Import & Export.
 *
 * CSV format is deliberately ID-first. The "id" column is the primary key:
 * an existing ID is updated, while a blank ID creates a new Group listing.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'bubbahub_directory_csv_admin_menu' );
add_action( 'admin_post_bubbahub_directory_csv_export', 'bubbahub_directory_csv_export' );
add_action( 'admin_post_bubbahub_directory_csv_import', 'bubbahub_directory_csv_import' );
add_action( 'admin_post_bubbahub_directory_csv_template', 'bubbahub_directory_csv_template' );
add_action( 'admin_post_bubbahub_directory_csv_save_url', 'bubbahub_directory_csv_save_url' );
add_action( 'admin_post_bubbahub_directory_csv_auto_import', 'bubbahub_directory_csv_auto_import' );

function bubbahub_directory_csv_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=group',
        'CSV / Google Sheets',
        'CSV / Google Sheets',
        'manage_options',
        'bubbahub-directory-csv',
        'bubbahub_directory_csv_admin_page'
    );
}

function bubbahub_directory_csv_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $notice = isset( $_GET['bh_csv_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['bh_csv_notice'] ) ) : '';
    $type   = isset( $_GET['bh_csv_type'] ) ? sanitize_key( $_GET['bh_csv_type'] ) : 'success';
    $saved_url = get_option( 'bubbahub_directory_csv_connected_url', '' );
    ?>
    <div class="wrap">
        <h1>BubbaHub Listing CSV / Google Sheets</h1>
        <p>Use the <strong>id</strong> column as the primary key. Existing listing IDs are updated; rows without an ID create new listings.</p>
        <?php if ( $notice ) : ?>
            <div class="notice notice-<?php echo esc_attr( in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'success' ); ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
        <?php endif; ?>

        <div class="card" style="max-width:900px;padding:20px;">
            <h2>Export listings</h2>
            <p>Exports every published Group listing, including the WordPress ID, core listing fields, region taxonomy and listing meta fields.</p>
            <a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bubbahub_directory_csv_export' ), 'bubbahub_csv_export' ) ); ?>">Download CSV</a>
            <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bubbahub_directory_csv_template' ), 'bubbahub_csv_template' ) ); ?>">Download Template</a>
        </div>

        <div class="card" style="max-width:900px;padding:20px;margin-top:20px;">
            <h2>Import listings</h2>
            <p><strong>Workflow:</strong> Export → edit in Google Sheets → paste the sheet CSV URL here → import. Existing <code>id</code> values update listings; blank IDs create listings.</p>
            <p>Choose one of the two import methods below. A row with an existing <strong>id</strong> updates that listing. Leave <strong>id</strong> blank to create a new listing.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="bubbahub_directory_csv_import">
                <?php wp_nonce_field( 'bubbahub_csv_import' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bh_csv_file">Import from CSV File</label></th>
                        <td><input id="bh_csv_file" name="bh_csv_file" type="file" accept=".csv,text/csv"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bh_csv_url">Import from Google Sheets</label></th>
                        <td>
                            <input id="bh_csv_url" name="bh_csv_url" type="url" class="regular-text code" style="width:100%;max-width:700px;" placeholder="Paste your published Google Sheets CSV URL">
                            <p class="description">The sheet must be publicly accessible or otherwise return CSV data without requiring an interactive Google login.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Import / Update Listings', 'primary' ); ?>
            </form>
        </div>

        <div class="card" style="max-width:900px;padding:20px;margin-top:20px;">
            <h2>Connected Google Sheet</h2>
            <p>Save your published CSV URL once. You can then import the latest version with one click without pasting the URL again.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="bubbahub_directory_csv_save_url">
                <?php wp_nonce_field( 'bubbahub_csv_save_url' ); ?>
                <input name="bh_connected_csv_url" type="url" class="regular-text code" style="width:100%;max-width:700px;" value="<?php echo esc_attr( $saved_url ); ?>" placeholder="Paste your published Google Sheets CSV URL">
                <?php submit_button( 'Save / Connect CSV URL', 'secondary', 'submit', false ); ?>
            </form>
            <?php if ( $saved_url ) : ?>
                <p style="margin-top:15px;"><strong>Connected:</strong> <?php echo esc_html( $saved_url ); ?></p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
                    <input type="hidden" name="action" value="bubbahub_directory_csv_auto_import">
                    <?php wp_nonce_field( 'bubbahub_csv_auto_import' ); ?>
                    <?php submit_button( 'Import Latest CSV Now', 'primary', 'submit', false ); ?>
                </form>
                <p class="description">This button always downloads the latest data from the connected Google Sheet and creates new listings or updates existing listings using the <code>id</code> column.</p>
            <?php endif; ?>
        </div>

        <div class="card" style="max-width:900px;padding:20px;margin-top:20px;">
            <h2>Recommended Google Sheets columns</h2>
            <p><code>id, post_title, post_content, post_status, post_author, post_name, category, tags, region, sub_region, address, postcode, price, age_range, session_length, business_hours, email, phone, website, facebook, instagram, latitude, longitude, map, term_time, image_url</code></p>
            <p>Any additional column is treated as a listing post-meta field, so the importer can carry new BubbaHub fields without changing this feature.</p>
        </div>
    </div>
    <?php
}

function bubbahub_directory_csv_value_encode( $value ) {
    if ( is_array( $value ) || is_object( $value ) ) {
        return 'json:' . wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }
    if ( is_bool( $value ) ) return $value ? '1' : '0';
    return is_scalar( $value ) ? (string) $value : '';
}

function bubbahub_directory_csv_value_decode( $value ) {
    $value = (string) $value;
    if ( 0 === strpos( $value, 'json:' ) ) {
        $decoded = json_decode( substr( $value, 5 ), true );
        if ( JSON_ERROR_NONE === json_last_error() ) return $decoded;
    }
    return $value;
}

function bubbahub_directory_csv_meta_keys() {
    global $wpdb;
    $keys = $wpdb->get_col( "SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'group' AND pm.meta_key IS NOT NULL AND pm.meta_key <> ''" );
    $keys = array_filter( array_map( 'sanitize_key', $keys ) );
    return array_values( array_unique( $keys ) );
}



function bubbahub_directory_csv_template() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to download the template.' );
    check_admin_referer( 'bubbahub_csv_template' );
    $headers = array( 'id','post_title','post_content','post_status','post_author','post_name','category','tags','region','sub_region','address','postcode','price','age_range','session_length','business_hours','email','phone','website','facebook','instagram','latitude','longitude','map','term_time','image_url' );
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="bubbahub-listing-template.csv"' );
    $out = fopen( 'php://output', 'w' );
    fwrite( $out, "\xEF\xBB\xBF" );
    fputcsv( $out, $headers );
    fputcsv( $out, array_fill( 0, count( $headers ), '' ) );
    fclose( $out );
    exit;
}

function bubbahub_directory_csv_export() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to export listings.' );
    check_admin_referer( 'bubbahub_csv_export' );

    $ids = get_posts( array(
        'post_type' => 'group',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'no_found_rows' => true,
    ) );

    $meta_keys = bubbahub_directory_csv_meta_keys();
    $fixed = array( 'id', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_author', 'post_name', 'post_date', 'category', 'tags', 'region', 'sub_region', 'address', 'postcode', 'latitude', 'longitude', 'map', 'business_hours', 'term_time', 'image_url' );
    $headers = array_values( array_unique( array_merge( $fixed, $meta_keys ) ) );

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="bubbahub-listings-' . gmdate( 'Y-m-d-His' ) . '.csv"' );

    $out = fopen( 'php://output', 'w' );
    // UTF-8 BOM makes the CSV open cleanly in Excel and Google Sheets.
    fwrite( $out, "\xEF\xBB\xBF" );
    fputcsv( $out, $headers );

    foreach ( $ids as $id ) {
        $post = get_post( $id );
        if ( ! $post ) continue;

        $terms = wp_get_post_terms( $id, 'region', array( 'fields' => 'slugs' ) );
        $latitude  = get_post_meta( $id, 'latitude', true );
        $longitude = get_post_meta( $id, 'longitude', true );
        $map_value = get_post_meta( $id, 'map', true );
        if ( ( '' === trim( (string) $map_value ) ) && '' !== trim( (string) $latitude ) && '' !== trim( (string) $longitude ) ) {
            $map_value = $latitude . ',' . $longitude;
        }
        if ( ( '' === trim( (string) $latitude ) || '' === trim( (string) $longitude ) ) && is_string( $map_value ) && preg_match( '/^\\s*(-?\\d+(?:\\.\\d+)?)\\s*,\\s*(-?\\d+(?:\\.\\d+)?)\\s*$/', $map_value, $coords ) ) {
            $latitude = $coords[1];
            $longitude = $coords[2];
        }
        $row = array();
        foreach ( $headers as $column ) {
            switch ( $column ) {
                case 'id': $value = $id; break;
                case 'post_title': $value = $post->post_title; break;
                case 'post_content': $value = $post->post_content; break;
                case 'post_excerpt': $value = $post->post_excerpt; break;
                case 'post_status': $value = $post->post_status; break;
                case 'post_author':
                    $author_user = get_userdata( (int) $post->post_author );
                    $value = $author_user ? $author_user->user_login : '';
                    break;
                case 'post_name': $value = $post->post_name; break;
                case 'post_date': $value = $post->post_date; break;
                case 'region': $value = implode( ', ', is_wp_error( $terms ) ? array() : $terms ); break;
                case 'sub_region': $value = ''; break;
                case 'address': $value = get_post_meta( $id, 'address', true ); break;
                case 'postcode': $value = get_post_meta( $id, 'postcode', true ); break;
                case 'latitude': $value = $latitude; break;
                case 'longitude': $value = $longitude; break;
                case 'map': $value = $map_value; break;
                case 'business_hours': $value = get_post_meta( $id, 'business_hours', true ); break;
                case 'term_time': $value = get_post_meta( $id, 'term_time', true ); break;
                case 'image_url': $value = ''; break;
                default: $value = get_post_meta( $id, $column, true ); break;
            }
            $row[] = bubbahub_directory_csv_value_encode( $value );
        }
        fputcsv( $out, $row );
    }
    fclose( $out );
    exit;
}

function bubbahub_directory_csv_google_url( $url ) {
    $url = trim( $url );
    if ( ! $url ) return '';

    // Google Sheets published/export URLs are accepted as-is.
    if ( false !== strpos( $url, 'docs.google.com/spreadsheets' ) ) {
        if ( false !== strpos( $url, '/export' ) || false !== strpos( $url, 'format=csv' ) ) return $url;
        if ( preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $match ) ) {
            $gid = '';
            if ( preg_match( '/[?&#]gid=([0-9]+)/', $url, $gid_match ) ) $gid = $gid_match[1];
            return 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $match[1] ) . '/export?format=csv' . ( $gid ? '&gid=' . rawurlencode( $gid ) : '' );
        }
    }
    return $url;
}

function bubbahub_directory_csv_save_url() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to save the CSV URL.' );
    check_admin_referer( 'bubbahub_csv_save_url' );
    $url = isset( $_POST['bh_connected_csv_url'] ) ? esc_url_raw( wp_unslash( $_POST['bh_connected_csv_url'] ) ) : '';
    if ( ! $url ) {
        delete_option( 'bubbahub_directory_csv_connected_url' );
        bubbahub_directory_csv_import_redirect( 'success', 'Connected CSV URL removed.' );
    }
    update_option( 'bubbahub_directory_csv_connected_url', $url, false );
    bubbahub_directory_csv_import_redirect( 'success', 'CSV URL connected and saved. You can now use Import Latest CSV Now.' );
}

function bubbahub_directory_csv_auto_import() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to import listings.' );
    check_admin_referer( 'bubbahub_csv_auto_import' );
    $saved_url = get_option( 'bubbahub_directory_csv_connected_url', '' );
    if ( ! $saved_url ) bubbahub_directory_csv_import_redirect( 'error', 'No connected CSV URL has been saved.' );

    // Reuse the normal importer by providing the saved URL as if it came from the form.
    $_POST['bh_csv_url'] = $saved_url;
    $_FILES = array();
    bubbahub_directory_csv_import();
}

function bubbahub_directory_csv_import() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to import listings.' );
    check_admin_referer( 'bubbahub_csv_import' );

    $csv = '';
    $url = isset( $_POST['bh_csv_url'] ) ? esc_url_raw( wp_unslash( $_POST['bh_csv_url'] ) ) : '';

    if ( ! empty( $_FILES['bh_csv_file']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $_FILES['bh_csv_file']['error'] ) {
        $csv = file_get_contents( $_FILES['bh_csv_file']['tmp_name'] );
    } elseif ( $url ) {
        $csv_url = bubbahub_directory_csv_google_url( $url );
        $remote = wp_safe_remote_get( $csv_url, array( 'timeout' => 30, 'redirection' => 5, 'headers' => array( 'Accept' => 'text/csv,text/plain,*/*' ) ) );
        if ( is_wp_error( $remote ) ) bubbahub_directory_csv_import_redirect( 'error', 'Could not download the CSV: ' . $remote->get_error_message() );
        $code = wp_remote_retrieve_response_code( $remote );
        $csv = wp_remote_retrieve_body( $remote );

        // Published Google Sheets URLs can occasionally return a redirect/HTML wrapper.
        // Retry published /pub URLs using Google's explicit CSV endpoint.
        if ( ( $code < 200 || $code >= 300 || '' === trim( $csv ) || false === strpos( ltrim( (string) $csv ), ',' ) ) && false !== strpos( $csv_url, 'docs.google.com/spreadsheets/d/e/' ) && false !== strpos( $csv_url, '/pub' ) ) {
            $parts = wp_parse_url( $csv_url );
            $query = array();
            if ( ! empty( $parts['query'] ) ) parse_str( $parts['query'], $query );
            $published_id = '';
            if ( ! empty( $parts['path'] ) && preg_match( '#/spreadsheets/d/e/([^/]+)/pub#', $parts['path'], $published_match ) ) {
                $published_id = $published_match[1];
            }
            $fallback = $published_id ? 'https://docs.google.com/spreadsheets/d/e/' . rawurlencode( $published_id ) . '/pub?output=csv' : $csv_url;
            if ( ! empty( $query['gid'] ) ) $fallback .= ( false === strpos( $fallback, '?' ) ? '?' : '&' ) . 'gid=' . rawurlencode( $query['gid'] );
            $remote = wp_safe_remote_get( $fallback, array( 'timeout' => 30, 'redirection' => 5, 'headers' => array( 'Accept' => 'text/csv,text/plain,*/*' ) ) );
            if ( ! is_wp_error( $remote ) ) {
                $code = wp_remote_retrieve_response_code( $remote );
                $csv = wp_remote_retrieve_body( $remote );
            }
        }

        if ( $code < 200 || $code >= 300 || '' === trim( $csv ) ) {
            if ( 404 === (int) $code && false !== strpos( $csv_url, 'docs.google.com/spreadsheets' ) ) {
                bubbahub_directory_csv_import_redirect( 'error', 'Google returned HTTP 404. The spreadsheet or selected sheet tab is not currently published to the web as CSV. In Google Sheets use File → Share → Publish to web, select the correct sheet/tab and choose Comma-separated values (.csv), then republish and paste the new URL here.' );
            }
            bubbahub_directory_csv_import_redirect( 'error', 'The CSV URL did not return usable CSV data (HTTP ' . (int) $code . '). Make sure the Google Sheet is published to the web and the URL ends with output=csv.' );
        }
    } else {
        bubbahub_directory_csv_import_redirect( 'error', 'Choose a CSV file or enter a Google Sheets / CSV URL.' );
    }

    $csv = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $csv );
    $stream = fopen( 'php://temp', 'r+' );
    fwrite( $stream, $csv );
    rewind( $stream );

    $headers = fgetcsv( $stream );
    if ( false === $headers || empty( $headers ) ) {
        fclose( $stream );
        bubbahub_directory_csv_import_redirect( 'error', 'The CSV contains no listing rows.' );
    }

    $headers = array_map( function( $header ) {
        $header = trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header ) );
        return sanitize_key( $header );
    }, $headers );

    $rows = array();
    while ( false !== ( $values = fgetcsv( $stream ) ) ) {
        $has_value = false;
        foreach ( $values as $value ) {
            if ( '' !== trim( (string) $value ) ) { $has_value = true; break; }
        }
        if ( $has_value ) $rows[] = $values;
    }
    fclose( $stream );

    if ( empty( $rows ) ) bubbahub_directory_csv_import_redirect( 'error', 'The CSV contains no listing rows.' );

    if ( ! in_array( 'id', $headers, true ) ) bubbahub_directory_csv_import_redirect( 'error', 'The CSV must contain an id column. It is the primary key for updates.' );

    $created = 0; $updated = 0; $skipped = 0; $errors = array();

    foreach ( $rows as $row_number => $values ) {
        $data = array();
        foreach ( $headers as $index => $key ) $data[ $key ] = isset( $values[ $index ] ) ? bubbahub_directory_csv_value_decode( $values[ $index ] ) : '';

        $id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
        $existing = $id ? get_post( $id ) : null;

        if ( $existing && 'group' !== $existing->post_type ) {
            $skipped++;
            $errors[] = 'Row ' . ( $row_number + 2 ) . ': ID ' . $id . ' exists but is not a Group listing.';
            continue;
        }

        $postarr = array( 'post_type' => 'group' );
        foreach ( array( 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_author', 'post_name', 'post_date' ) as $field ) {
            if ( array_key_exists( $field, $data ) && '' !== (string) $data[ $field ] ) $postarr[ $field ] = $data[ $field ];
        }

        // post_author accepts a WordPress username. Numeric IDs remain supported for backwards compatibility.
        if ( isset( $postarr['post_author'] ) && '' !== trim( (string) $postarr['post_author'] ) ) {
            $author_value = trim( (string) $postarr['post_author'] );
            if ( is_numeric( $author_value ) ) {
                $author_id = absint( $author_value );
            } else {
                $author_user = get_user_by( 'login', $author_value );
                $author_id = $author_user ? (int) $author_user->ID : 0;
            }
            if ( $author_id ) {
                $postarr['post_author'] = $author_id;
            } else {
                unset( $postarr['post_author'] );
            }
        }
        if ( empty( $postarr['post_status'] ) ) $postarr['post_status'] = 'publish';
        if ( ! empty( $existing ) ) $postarr['ID'] = $id;

        $saved_id = wp_insert_post( wp_slash( $postarr ), true );
        if ( is_wp_error( $saved_id ) ) {
            $skipped++;
            $errors[] = 'Row ' . ( $row_number + 2 ) . ': ' . $saved_id->get_error_message();
            continue;
        }

        $saved_id = absint( $saved_id );
        if ( $existing ) $updated++; else $created++;

        // Keep the map coordinates consistent. Prefer explicit latitude/longitude, but accept the legacy map="lat,long" format.
        $latitude = isset( $data['latitude'] ) ? trim( (string) $data['latitude'] ) : '';
        $longitude = isset( $data['longitude'] ) ? trim( (string) $data['longitude'] ) : '';
        $map = isset( $data['map'] ) ? trim( (string) $data['map'] ) : '';
        if ( ( '' === $latitude || '' === $longitude ) && preg_match( '/^\\s*(-?\\d+(?:\\.\\d+)?)\\s*,\\s*(-?\\d+(?:\\.\\d+)?)\\s*$/', $map, $coords ) ) {
            $latitude = $coords[1];
            $longitude = $coords[2];
        }
        $coordinates_valid = ( '' !== $latitude && '' !== $longitude && is_numeric( $latitude ) && is_numeric( $longitude ) && (float) $latitude >= -90 && (float) $latitude <= 90 && (float) $longitude >= -180 && (float) $longitude <= 180 );
        if ( $coordinates_valid ) {
            update_post_meta( $saved_id, 'latitude', (string) $latitude );
            update_post_meta( $saved_id, 'longitude', (string) $longitude );
            update_post_meta( $saved_id, 'map', $latitude . ',' . $longitude );
        }

        // Keep one simple schedule source: business_hours is the only CSV field used for hours.
        // Older schedule/timetable meta is retained on existing listings but is no longer exposed by the CSV template.
        if ( array_key_exists( 'business_hours', $data ) ) {
            $hours = is_array( $data['business_hours'] ) ? implode( '; ', $data['business_hours'] ) : trim( (string) $data['business_hours'] );
            if ( '' !== $hours ) update_post_meta( $saved_id, 'business_hours', $hours );
            else delete_post_meta( $saved_id, 'business_hours' );
        }

        // Taxonomy imports: create missing terms automatically.
        if ( array_key_exists( 'category', $data ) ) {
            $category_taxonomy = taxonomy_exists( 'group_category' ) ? 'group_category' : ( taxonomy_exists( 'category' ) ? 'category' : '' );
            if ( $category_taxonomy ) {
                $value = is_array( $data['category'] ) ? implode( ',', $data['category'] ) : (string) $data['category'];
                $terms = array_filter( array_map( 'trim', preg_split( '/[,|]/', $value ) ) );
                if ( $terms ) wp_set_object_terms( $saved_id, $terms, $category_taxonomy, false );
            }
        }

        if ( array_key_exists( 'tags', $data ) && taxonomy_exists( 'post_tag' ) ) {
            $value = is_array( $data['tags'] ) ? implode( ',', $data['tags'] ) : (string) $data['tags'];
            $terms = array_filter( array_map( 'trim', preg_split( '/[,|]/', $value ) ) );
            if ( $terms ) wp_set_object_terms( $saved_id, $terms, 'post_tag', false );
        }

        // Listing image gallery import. image_url accepts multiple URLs separated by comma, | or new lines.
        // The first successfully imported image becomes the featured image.
        if ( array_key_exists( 'image_url', $data ) ) {
            $image_value = is_array( $data['image_url'] ) ? implode( "\n", $data['image_url'] ) : (string) $data['image_url'];
            $image_urls = array_filter( array_map( 'trim', preg_split( '/[,|\\r\\n]+/', $image_value ) ) );
            if ( $image_urls ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $gallery_ids = array();
                foreach ( $image_urls as $image_url ) {
                    if ( ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) continue;
                    $attachment_id = media_sideload_image( esc_url_raw( $image_url ), $saved_id, null, 'id' );
                    if ( ! is_wp_error( $attachment_id ) ) $gallery_ids[] = (int) $attachment_id;
                }
                if ( $gallery_ids ) {
                    update_post_meta( $saved_id, '_bubbahub_gallery_ids', $gallery_ids );
                    set_post_thumbnail( $saved_id, $gallery_ids[0] );
                }
            }
        }

        if ( array_key_exists( 'region', $data ) && taxonomy_exists( 'region' ) ) {
            $region_value = is_array( $data['region'] ) ? implode( ',', $data['region'] ) : (string) $data['region'];
            $region_terms = array_filter( array_map( 'trim', preg_split( '/[,|]/', $region_value ) ) );
            if ( $region_terms ) wp_set_object_terms( $saved_id, $region_terms, 'region', false );

            // sub_region is a child of the first supplied region term.
            if ( array_key_exists( 'sub_region', $data ) ) {
                $sub_value = is_array( $data['sub_region'] ) ? implode( ',', $data['sub_region'] ) : (string) $data['sub_region'];
                $sub_names = array_filter( array_map( 'trim', preg_split( '/[,|]/', $sub_value ) ) );
                $parent_term = get_term_by( 'name', $region_terms[0], 'region' );
                $parent_id = $parent_term ? (int) $parent_term->term_id : 0;

                foreach ( $sub_names as $sub_name ) {
                    $existing_child = get_term_by( 'name', $sub_name, 'region' );
                    if ( $existing_child && $parent_id && (int) $existing_child->parent !== $parent_id ) {
                        wp_update_term( $existing_child->term_id, 'region', array( 'parent' => $parent_id ) );
                    }
                    if ( ! $existing_child ) {
                        $created_child = wp_insert_term( $sub_name, 'region', array( 'parent' => $parent_id ) );
                        if ( ! is_wp_error( $created_child ) ) $existing_child = get_term( $created_child['term_id'], 'region' );
                    }
                }
            }
        }

        $core = array( 'id', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_author', 'post_name', 'post_date', 'region', 'sub_region', 'address', 'postcode', 'latitude', 'longitude', 'map', 'business_hours', 'image_url', 'term_time' );
        foreach ( $data as $key => $value ) {
            if ( in_array( $key, $core, true ) || '' === $key ) continue;
            if ( is_string( $value ) && '' === trim( $value ) ) {
                delete_post_meta( $saved_id, $key );
            } else {
                update_post_meta( $saved_id, $key, $value );
            }
        }
    }

    $message = sprintf( 'Import complete: %d created, %d updated, %d skipped.', $created, $updated, $skipped );
    if ( $errors ) $message .= ' First error: ' . $errors[0];
    bubbahub_directory_csv_import_redirect( $errors ? 'warning' : 'success', $message );
}

function bubbahub_directory_csv_import_redirect( $type, $message ) {
    $url = add_query_arg(
        array(
            'post_type' => 'group',
            'page' => 'bubbahub-directory-csv',
            'bh_csv_type' => $type,
            'bh_csv_notice' => rawurlencode( wp_strip_all_tags( $message ) ),
        ),
        admin_url( 'edit.php' )
    );
    wp_safe_redirect( $url );
    exit;
}
