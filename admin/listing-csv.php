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
add_action( 'admin_post_bubbahub_directory_resend_welcome_email', 'bubbahub_directory_resend_welcome_email' );

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
            <h2>Leader Welcome Emails</h2>
            <p>Resend the branded Bubba Hub welcome email to any leader. A fresh secure password-reset link is generated each time.</p>
            <?php
            $leader_ids = get_posts( array(
                'post_type' => 'group',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
            ) );
            $leader_ids = array_values( array_unique( array_filter( array_map( 'get_post_field', $leader_ids, array_fill( 0, count( $leader_ids ), 'post_author' ) ) ) ) );
            $leaders = array();
            foreach ( $leader_ids as $leader_id ) {
                $leader = get_userdata( (int) $leader_id );
                // Only users who own at least one Bubba Hub Group listing are treated as leaders.
                if ( $leader && is_email( $leader->user_email ) ) $leaders[ $leader->ID ] = $leader;
            }
            if ( $leaders ) : ?>
                <table class="widefat striped" style="max-width:850px;">
                    <thead><tr><th>Leader</th><th>Email</th><th>Welcome sent</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ( $leaders as $leader ) : ?>
                        <tr>
                            <td><?php echo esc_html( $leader->display_name ? $leader->display_name : $leader->user_login ); ?><br><small><?php echo esc_html( $leader->user_login ); ?></small></td>
                            <td><?php echo esc_html( $leader->user_email ); ?></td>
                            <td><?php $sent_at = get_user_meta( $leader->ID, '_bubbahub_welcome_sent', true ); echo $sent_at ? esc_html( $sent_at ) : 'Not recorded'; ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <input type="hidden" name="action" value="bubbahub_directory_resend_welcome_email">
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr( $leader->ID ); ?>">
                                    <?php wp_nonce_field( 'bubbahub_resend_welcome_' . $leader->ID ); ?>
                                    <?php submit_button( 'Resend Welcome Email', 'secondary', 'submit', false ); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No leaders with email addresses were found.</p>
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


function bubbahub_directory_resend_welcome_email() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to resend welcome emails.' );
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    if ( ! $user_id ) wp_die( 'Invalid user.' );
    check_admin_referer( 'bubbahub_resend_welcome_' . $user_id );
    $user = get_userdata( $user_id );
    if ( ! $user || ! is_email( $user->user_email ) ) {
        bubbahub_directory_csv_import_redirect( 'error', 'The selected leader does not have a valid email address.' );
    }

    // Restrict resend to Bubba Hub leaders: users who own at least one Group listing.
    $leader_listing_count = count_user_posts( $user_id, 'group', true );
    if ( ! $leader_listing_count ) {
        bubbahub_directory_csv_import_redirect( 'error', 'Welcome emails can only be resent to Bubba Hub leader users.' );
    }

    delete_user_meta( $user_id, '_bubbahub_welcome_sent' );
    if ( function_exists( 'bubbahub_directory_send_new_leader_welcome' ) ) {
        bubbahub_directory_send_new_leader_welcome( $user_id );
        $sent = get_user_meta( $user_id, '_bubbahub_welcome_sent', true );
        bubbahub_directory_csv_import_redirect( $sent ? 'success' : 'error', $sent ? 'Welcome email resent to ' . $user->user_email . '.' : 'The welcome email could not be sent to ' . $user->user_email . '.' );
    }
    bubbahub_directory_csv_import_redirect( 'error', 'The Bubba Hub welcome email function is not available.' );
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

    // Google Sheets published/export URLs are accepted and normalised.
    if ( false !== strpos( $url, 'docs.google.com/spreadsheets' ) ) {
        // Published-to-web URLs use /d/e/<published-id>/pub and must stay on the
        // published endpoint; convert them explicitly to CSV output.
        if ( preg_match( '#/spreadsheets/d/e/([^/]+)/pub#', $url, $published_match ) ) {
            $gid = '';
            if ( preg_match( '/[?&#]gid=([0-9]+)/', $url, $gid_match ) ) $gid = $gid_match[1];
            return 'https://docs.google.com/spreadsheets/d/e/' . rawurlencode( $published_match[1] ) . '/pub?output=csv' . ( $gid ? '&gid=' . rawurlencode( $gid ) : '' );
        }
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
    // Do not use a standard form nonce here: the saved URL is already protected by
    // manage_options, and long-lived admin pages can have an expired nonce after deployment.
    // This prevents WordPress from showing "The link you followed has expired."
    $saved_url = get_option( 'bubbahub_directory_csv_connected_url', '' );
    if ( ! $saved_url ) bubbahub_directory_csv_import_redirect( 'error', 'No connected CSV URL has been saved.' );

    // Reuse the normal importer by providing the saved URL as if it came from the form.
    $_POST['bh_csv_url'] = $saved_url;
    $_FILES = array();
    $GLOBALS['bubbahub_directory_csv_internal_import'] = true;
    bubbahub_directory_csv_import();
    unset( $GLOBALS['bubbahub_directory_csv_internal_import'] );
}

function bubbahub_directory_csv_parse_business_hours( $value ) {
    $days = array( 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday' );
    $result = array();
    foreach ( $days as $day ) $result[ $day ] = array( 'day_name' => $day, 'is_closed' => true, 'sessions' => array() );
    if ( is_array( $value ) ) {
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) || empty( $row['day_name'] ) ) continue;
            $day = ucwords( strtolower( trim( (string) $row['day_name'] ) ) );
            if ( ! isset( $result[ $day ] ) ) continue;
            $result[ $day ] = array(
                'day_name' => $day,
                'is_closed' => ! empty( $row['is_closed'] ),
                'sessions' => ! empty( $row['sessions'] ) && is_array( $row['sessions'] ) ? $row['sessions'] : array(),
            );
        }
        return array_values( $result );
    }
    $text = trim( (string) $value );
    if ( '' === $text ) return array_values( $result );
    $parts = preg_split( '/\\s*;\\s*/', $text );
    foreach ( $parts as $part ) {
        $part = trim( $part );
        if ( '' === $part ) continue;
        if ( preg_match( '/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\\s+closed$/i', $part, $m ) ) {
            $day = ucfirst( strtolower( $m[1] ) );
            $result[ $day ]['is_closed'] = true;
            $result[ $day ]['sessions'] = array();
            continue;
        }
        if ( ! preg_match( '/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\\s+(.+?)\\s*-\\s*(.+)$/i', $part, $m ) ) continue;
        $day = ucfirst( strtolower( $m[1] ) );
        $start = trim( $m[2] );
        $end = trim( $m[3] );
        if ( ! isset( $result[ $day ] ) ) continue;
        $result[ $day ]['is_closed'] = false;
        $result[ $day ]['sessions'][] = array( 'start_time' => $start, 'end_time' => $end );
    }
    return array_values( $result );
}

function bubbahub_directory_csv_map_session_fields( $session, $session_fields ) {
    $mapped = array();
    foreach ( $session_fields as $sf ) {
        $name = isset( $sf['name'] ) ? (string) $sf['name'] : '';
        if ( ! $name ) continue;
        $lower = strtolower( $name );
        if ( false !== strpos( $lower, 'start' ) || false !== strpos( $lower, 'open' ) || false !== strpos( $lower, 'from' ) ) $mapped[ $name ] = $session['start_time'];
        elseif ( false !== strpos( $lower, 'end' ) || false !== strpos( $lower, 'close' ) || false !== strpos( $lower, 'to' ) ) $mapped[ $name ] = $session['end_time'];
    }
    return $mapped ? $mapped : $session;
}

function bubbahub_directory_csv_save_acf_business_hours( $post_id, $value ) {
    if ( ! function_exists( 'update_field' ) ) return;
    $hours = bubbahub_directory_csv_parse_business_hours( $value );
    if ( function_exists( 'acf_get_field' ) ) {
        $field = acf_get_field( 'group_business_hours_repeater' );
        if ( is_array( $field ) && ! empty( $field['sub_fields'] ) ) {
            $session_fields = array();
            foreach ( $field['sub_fields'] as $sub ) {
                if ( ! empty( $sub['name'] ) && 'sessions' === $sub['name'] ) {
                    $session_fields = ! empty( $sub['sub_fields'] ) ? $sub['sub_fields'] : array();
                    break;
                }
            }
            foreach ( $hours as &$day ) {
                $mapped_sessions = array();
                foreach ( $day['sessions'] as $session ) {
                    $mapped_sessions[] = bubbahub_directory_csv_map_session_fields( $session, $session_fields );
                }
                $day['sessions'] = $mapped_sessions;
            }
            unset( $day );
        }
    }
    update_field( 'group_business_hours_repeater', $hours, $post_id );
    // Keep the legacy CSV/meta value too for backwards compatibility.
    if ( is_array( $value ) ) update_post_meta( $post_id, 'business_hours', wp_json_encode( $value ) );
    else update_post_meta( $post_id, 'business_hours', (string) $value );
}

function bubbahub_directory_csv_import_or_create_venue( $group_id, $author_id, $address, $postcode, $latitude, $longitude, $map ) {
    $address = trim( (string) $address );
    $postcode = trim( (string) $postcode );
    if ( ! post_type_exists( 'venue' ) || ( '' === $address && ( '' === $latitude || '' === $longitude ) ) ) return 0;
    $venue_id = 0;
    $venues = get_posts( array( 'post_type' => 'venue', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
    foreach ( $venues as $candidate ) {
        $c_lat = trim( (string) get_post_meta( $candidate, 'latitude', true ) );
        $c_lng = trim( (string) get_post_meta( $candidate, 'longitude', true ) );
        $c_address = trim( (string) get_post_meta( $candidate, 'address', true ) );
        if ( $latitude !== '' && $longitude !== '' && $c_lat !== '' && $c_lng !== '' && abs( (float) $c_lat - (float) $latitude ) < 0.00001 && abs( (float) $c_lng - (float) $longitude ) < 0.00001 ) { $venue_id = (int) $candidate; break; }
        if ( ! $venue_id && $address !== '' && $c_address !== '' && strtolower( $c_address ) === strtolower( $address ) ) { $venue_id = (int) $candidate; break; }
    }
    if ( ! $venue_id ) {
        $title = $address !== '' ? $address : 'Venue';
        $venue_id = wp_insert_post( array(
            'post_type' => 'venue',
            'post_title' => wp_strip_all_tags( $title ),
            'post_status' => 'publish',
            'post_author' => absint( $author_id ),
        ), true );
        if ( is_wp_error( $venue_id ) ) return 0;
        $venue_id = absint( $venue_id );
    } else {
        if ( $author_id && (int) get_post_field( 'post_author', $venue_id ) !== (int) $author_id ) wp_update_post( array( 'ID' => $venue_id, 'post_author' => absint( $author_id ) ) );
    }
    if ( $address !== '' ) update_post_meta( $venue_id, 'address', $address );
    if ( $postcode !== '' ) update_post_meta( $venue_id, 'postcode', $postcode );
    if ( $latitude !== '' ) update_post_meta( $venue_id, 'latitude', $latitude );
    if ( $longitude !== '' ) update_post_meta( $venue_id, 'longitude', $longitude );
    if ( $map !== '' ) update_post_meta( $venue_id, 'map', $map );
    if ( function_exists( 'update_field' ) ) {
        foreach ( array( 'address','postcode','latitude','longitude','map' ) as $field_name ) {
            $val = get_post_meta( $venue_id, $field_name, true );
            if ( '' !== (string) $val ) update_field( $field_name, $val, $venue_id );
        }
    }
    update_post_meta( $group_id, 'venue_id', $venue_id );
    update_post_meta( $group_id, 'venue', $venue_id );
    if ( function_exists( 'update_field' ) ) {
        update_field( 'venue_id', $venue_id, $group_id );
        update_field( 'venue', $venue_id, $group_id );
    }
    return $venue_id;
}

function bubbahub_directory_csv_import() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'You do not have permission to import listings.' );
    if ( empty( $GLOBALS['bubbahub_directory_csv_internal_import'] ) ) {
        check_admin_referer( 'bubbahub_csv_import' );
    }

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

    $created = 0; $updated = 0; $skipped = 0; $new_users = 0; $errors = array();

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

        // Resolve the CSV post_author to a WordPress user. New users are handled by
        // the central Bubba Hub welcome-email hook, which also covers Admin > Add New User.
        $author_username  = isset( $data['post_author'] ) ? sanitize_user( trim( (string) $data['post_author'] ), true ) : '';
        $author_email     = isset( $data['email'] ) ? sanitize_email( trim( (string) $data['email'] ) ) : '';
        $author_user      = null;
        $new_user_created = false;
        if ( $author_username && ! is_numeric( $author_username ) ) {
            $author_user = get_user_by( 'login', $author_username );
            if ( ! $author_user && $author_email && is_email( $author_email ) && ! email_exists( $author_email ) ) {
                // CSV-created accounts are leaders, so create them with the leader role
                // before user_register fires. This ensures they receive the leader welcome email,
                // rather than the parent/family welcome email.
                $new_user_id = wp_insert_user( array(
                    'user_login'    => $author_username,
                    'user_pass'     => wp_generate_password( 32, true, true ),
                    'user_email'    => $author_email,
                    'display_name'  => $author_username,
                    'role'          => 'leader',
                ) );
                if ( ! is_wp_error( $new_user_id ) ) {
                    $author_user = get_user_by( 'id', $new_user_id );
                    $new_user_created = true;
                }
            }
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
        if ( $new_user_created ) $new_users++;

        // Send the branded Bubba Hub welcome email after the listing exists.
        if ( $new_user_created && $welcome_user_id ) {
            $welcome_user = get_user_by( 'id', $welcome_user_id );
            if ( $welcome_user ) {
                $reset_key = get_password_reset_key( $welcome_user );
                $reset_url = ! is_wp_error( $reset_key ) ? network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $reset_key ) . '&login=' . rawurlencode( $author_username ), 'login' ) : wp_lostpassword_url();
                $listing_url = get_permalink( $saved_id );
                $portal_url = home_url( '/leader-portal/' );
                $support_url = home_url( '/support/' );
                $subject = '🎉 Welcome to Bubba Hub! Your groups & classes are live!';
                $body = '<div style="font-family:Arial,sans-serif;max-width:680px;margin:0 auto;color:#333;line-height:1.6;">';
                $body .= '<div style="padding:24px;text-align:center;border-radius:14px 14px 0 0;background:#f8e8ef;"><h1 style="margin:0;">Bubba Hub 💛</h1></div>';
                $body .= '<div style="padding:30px;">';
                $body .= '<p>Hey there! 👋</p><h2>Welcome to Bubba Hub! 🎉</h2>';
                $body .= '<p>We’re so excited to have you on board. Your groups and classes have now been added to the Bubba Hub directory.</p>';
                $body .= '<p><strong>Username:</strong> ' . esc_html( $author_username ) . '</p>';
                $body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $listing_url ) . '" style="display:inline-block;padding:14px 24px;border-radius:8px;background:#f3a6b8;color:#fff;text-decoration:none;font-weight:bold;">View Your Listings</a></p>';
                $body .= '<p>Before you log in for the first time, set your password using the secure button below.</p>';
                $body .= '<p style="text-align:center;margin:28px 0;"><a href="' . esc_url( $reset_url ) . '" style="display:inline-block;padding:14px 24px;border-radius:8px;background:#8bc6c9;color:#fff;text-decoration:none;font-weight:bold;">Set Your Password & Access Your Account</a></p>';
                $body .= '<h3>🌟 What can you do next?</h3><ul><li>Manage and update your listings</li><li>Keep your classes and schedules up to date</li><li>Manage bookings and reservations</li><li>Connect with local families</li><li>Keep your venues and locations up to date</li></ul>';
                $body .= '<h3>🌈 Let’s build this together</h3><p>Bubba Hub is more than just a directory — we’re building a community that brings families, group leaders, businesses and local specialists together.</p>';
                $body .= '<p><a href="' . esc_url( $portal_url ) . '">Visit your Bubba Hub Leader Portal</a></p>';
                $body .= '<p><a href="' . esc_url( $support_url ) . '">Visit the Bubba Hub Support Centre</a></p>';
                $body .= '<p>If you need anything at all, just reply to this email — we’re always happy to help.</p>';
                $body .= '<p>Warmly,<br><strong>The Bubba Hub Team</strong> 💛</p>';
                $body .= '<p style="font-size:13px;color:#777;">Bubba Hub · bubbahub.co.uk · @bubbahubsw on Facebook & Instagram</p>';
                $body .= '</div></div>';

                $from_name = function() { return 'Bubba Hub'; };
                $from_email = function() { return 'contact@bubbahub.co.uk'; };
                add_filter( 'wp_mail_from_name', $from_name );
                add_filter( 'wp_mail_from', $from_email );
                wp_mail( $author_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
                remove_filter( 'wp_mail_from_name', $from_name );
                remove_filter( 'wp_mail_from', $from_email );
                update_user_meta( $welcome_user_id, '_bubbahub_welcome_sent', current_time( 'mysql' ) );
            }
        }

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

        // Import the weekly schedule into the nested ACF business-hours repeater.
        if ( array_key_exists( 'business_hours', $data ) ) {
            bubbahub_directory_csv_save_acf_business_hours( $saved_id, $data['business_hours'] );
        }

        // Create/reuse a Venue from the imported location and assign it to the same leader.
        $venue_id = bubbahub_directory_csv_import_or_create_venue(
            $saved_id,
            isset( $postarr['post_author'] ) ? absint( $postarr['post_author'] ) : 0,
            isset( $data['address'] ) ? $data['address'] : '',
            isset( $data['postcode'] ) ? $data['postcode'] : '',
            $latitude,
            $longitude,
            $map
        );

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

        $core = array( 'id', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_author', 'post_name', 'post_date', 'region', 'sub_region', 'address', 'postcode', 'latitude', 'longitude', 'map', 'business_hours', 'image_url', 'term_time', 'welcome_email' );
        foreach ( $data as $key => $value ) {
            if ( in_array( $key, $core, true ) || '' === $key ) continue;
            if ( is_string( $value ) && '' === trim( $value ) ) {
                delete_post_meta( $saved_id, $key );
            } else {
                update_post_meta( $saved_id, $key, $value );
            }
        }
    }

    $message = sprintf( 'Import complete: %d created, %d updated, %d skipped, %d new leader accounts created.', $created, $updated, $skipped, $new_users );
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
