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
            <p>Upload a CSV or paste a public Google Sheets CSV/export URL. A row with an existing <strong>id</strong> updates that listing. Leave <strong>id</strong> blank to create a new listing.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="bubbahub_directory_csv_import">
                <?php wp_nonce_field( 'bubbahub_csv_import' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bh_csv_file">CSV file</label></th>
                        <td><input id="bh_csv_file" name="bh_csv_file" type="file" accept=".csv,text/csv"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bh_csv_url">Google Sheets / CSV URL</label></th>
                        <td>
                            <input id="bh_csv_url" name="bh_csv_url" type="url" class="regular-text code" style="width:100%;max-width:700px;" placeholder="https://docs.google.com/spreadsheets/d/...">
                            <p class="description">The sheet must be publicly accessible or otherwise return CSV data without requiring an interactive Google login.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Import / Update Listings', 'primary' ); ?>
            </form>
        </div>

        <div class="card" style="max-width:900px;padding:20px;margin-top:20px;">
            <h2>Recommended Google Sheets columns</h2>
            <p><code>id, post_title, post_content, post_status, post_author, post_name, region, address, price, age_range, session_length, business_hours, schedule, timetable, email, phone, website, facebook, instagram, twitter, tiktok, map, term_time</code></p>
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
    $headers = array( 'id','post_title','post_content','post_status','post_author','post_name','region','address','price','age_range','session_length','business_hours','schedule','timetable','email','phone','website','facebook','instagram','twitter','tiktok','map','term_time' );
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
    $fixed = array( 'id', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_author', 'post_name', 'post_date', 'region' );
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
        $row = array();
        foreach ( $headers as $column ) {
            switch ( $column ) {
                case 'id': $value = $id; break;
                case 'post_title': $value = $post->post_title; break;
                case 'post_content': $value = $post->post_content; break;
                case 'post_excerpt': $value = $post->post_excerpt; break;
                case 'post_status': $value = $post->post_status; break;
                case 'post_author': $value = $post->post_author; break;
                case 'post_name': $value = $post->post_name; break;
                case 'post_date': $value = $post->post_date; break;
                case 'region': $value = implode( ', ', is_wp_error( $terms ) ? array() : $terms ); break;
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

function bubbahub_directory_csv_import() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to import listings.' );
    check_admin_referer( 'bubbahub_csv_import' );

    $csv = '';
    $url = isset( $_POST['bh_csv_url'] ) ? esc_url_raw( wp_unslash( $_POST['bh_csv_url'] ) ) : '';

    if ( ! empty( $_FILES['bh_csv_file']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $_FILES['bh_csv_file']['error'] ) {
        $csv = file_get_contents( $_FILES['bh_csv_file']['tmp_name'] );
    } elseif ( $url ) {
        $remote = wp_safe_remote_get( bubbahub_directory_csv_google_url( $url ), array( 'timeout' => 30, 'redirection' => 3 ) );
        if ( is_wp_error( $remote ) ) bubbahub_directory_csv_import_redirect( 'error', 'Could not download the CSV: ' . $remote->get_error_message() );
        $code = wp_remote_retrieve_response_code( $remote );
        $csv = wp_remote_retrieve_body( $remote );
        if ( $code < 200 || $code >= 300 || '' === trim( $csv ) ) bubbahub_directory_csv_import_redirect( 'error', 'The CSV URL did not return usable CSV data.' );
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

        if ( array_key_exists( 'region', $data ) ) {
            $region_value = $data['region'];
            if ( is_array( $region_value ) ) $region_value = implode( ',', $region_value );
            $terms = array_filter( array_map( 'trim', preg_split( '/[,|]/', (string) $region_value ) ) );
            if ( taxonomy_exists( 'region' ) ) wp_set_object_terms( $saved_id, $terms, 'region', false );
        }

        $core = array( 'id', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_author', 'post_name', 'post_date', 'region' );
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
