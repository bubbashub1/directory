<?php
/**
 * BubbaHub Booking Engine — Booking Session admin UI.
 * Internal include loaded by the Booking Engine plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'add_meta_boxes_bh_session', 'bubbahub_booking_add_session_meta_box' );

function bubbahub_booking_add_session_meta_box( $post ) {
    add_meta_box( 'bubbahub_booking_session_details', 'Booking Session Details', 'bubbahub_booking_render_session_meta_box', 'bh_session', 'normal', 'high' );
}

function bubbahub_booking_render_session_meta_box( $post ) {
    wp_nonce_field( 'bubbahub_booking_save_session', 'bubbahub_booking_session_nonce' );

    $group_id        = absint( bubbahub_booking_meta( $post->ID, '_bh_group_id', 0 ) );
    $venue_id        = absint( bubbahub_booking_meta( $post->ID, '_bh_venue_id', 0 ) );
    $date            = bubbahub_booking_meta( $post->ID, '_bh_date', '' );
    $start_time      = bubbahub_booking_meta( $post->ID, '_bh_start_time', '' );
    $end_time        = bubbahub_booking_meta( $post->ID, '_bh_end_time', '' );
    $price           = bubbahub_booking_meta( $post->ID, '_bh_price', '' );
    $booking_method  = bubbahub_booking_meta( $post->ID, '_bh_booking_method', 'form' );
    $booking_action  = bubbahub_booking_meta( $post->ID, '_bh_booking_action', '' );
    $ninja_form_id   = absint( bubbahub_booking_meta( $post->ID, '_bh_ninja_form_id', 0 ) );
    $external_url    = bubbahub_booking_meta( $post->ID, '_bh_external_url', '' );
    $reserve_enabled = (bool) bubbahub_booking_meta( $post->ID, '_bh_reserve_enabled', false );
    $capacity        = absint( bubbahub_booking_meta( $post->ID, '_bh_capacity', 0 ) );
    $session_status  = bubbahub_booking_meta( $post->ID, '_bh_session_status', 'open' );
    $ticket_types    = get_post_meta( $post->ID, '_bh_ticket_types', true );

    if ( ! is_array( $ticket_types ) ) {
        $ticket_types = array();
    }

    // Convert existing legacy settings to the new clear action model.
    if ( ! in_array( $booking_action, array( 'book_now', 'reserve_spot', 'external', 'none' ), true ) ) {
        if ( 'external' === $booking_method ) {
            $booking_action = 'external';
        } elseif ( $reserve_enabled ) {
            $booking_action = 'reserve_spot';
        } elseif ( 'none' === $booking_method ) {
            $booking_action = 'none';
        } else {
            $booking_action = 'book_now';
        }
    }

    $groups = get_posts( array(
        'post_type'      => 'group',
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    $venues = get_posts( array(
        'post_type'      => 'venue',
        'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ) );

    $display_date = '';
    if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m ) ) {
        $display_date = $m[3] . '/' . $m[2] . '/' . $m[1];
    } elseif ( $date ) {
        $display_date = $date;
    }
    ?>
    <div class="bh-session-admin-fields">
        <p><strong>Use this panel to define one actual bookable occurrence.</strong><br>The Booking Engine uses these values for date selection, availability, capacity and booking actions.</p>

        <table class="form-table" role="presentation">
            <tr>
                <th><label for="bh_group_id">Group</label></th>
                <td>
                    <select name="bh_group_id" id="bh_group_id" class="regular-text" required>
                        <option value="">Select a group</option>
                        <?php foreach ( $groups as $group ) : ?>
                            <option value="<?php echo esc_attr( $group->ID ); ?>" <?php selected( $group_id, $group->ID ); ?>><?php echo esc_html( $group->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="bh_venue_id">Venue</label></th>
                <td>
                    <select name="bh_venue_id" id="bh_venue_id" class="regular-text">
                        <option value="0">No specific venue</option>
                        <?php foreach ( $venues as $venue ) : ?>
                            <option value="<?php echo esc_attr( $venue->ID ); ?>" <?php selected( $venue_id, $venue->ID ); ?>><?php echo esc_html( $venue->post_title ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="bh_date">Date</label></th>
                <td>
                    <input type="text" name="bh_date" id="bh_date" value="<?php echo esc_attr( $display_date ); ?>" class="regular-text" placeholder="DD/MM/YYYY" inputmode="numeric" autocomplete="off" required>
                    <p class="description">UK format: DD/MM/YYYY. Example: 15/09/2026.</p>
                </td>
            </tr>
            <tr>
                <th><label for="bh_start_time">Start time</label></th>
                <td><input type="time" name="bh_start_time" id="bh_start_time" value="<?php echo esc_attr( $start_time ); ?>" required></td>
            </tr>
            <tr>
                <th><label for="bh_end_time">End time</label></th>
                <td><input type="time" name="bh_end_time" id="bh_end_time" value="<?php echo esc_attr( $end_time ); ?>" required></td>
            </tr>
            <tr>
                <th><label for="bh_price">Default price</label></th>
                <td>
                    <input type="text" name="bh_price" id="bh_price" value="<?php echo esc_attr( $price ); ?>" class="regular-text" placeholder="e.g. 5 or Free">
                    <p class="description">Used when no ticket type is selected.</p>
                </td>
            </tr>
            <tr>
                <th><label for="bh_capacity">Session capacity</label></th>
                <td>
                    <input type="number" name="bh_capacity" id="bh_capacity" value="<?php echo esc_attr( $capacity ); ?>" min="0" step="1">
                    <p class="description">Overall session capacity. Use 0 for unlimited.</p>
                </td>
            </tr>
        </table>

        <hr>
        <h3>Booking action</h3>
        <p class="description">Choose exactly what parents should be able to do for this session.</p>

        <table class="form-table" role="presentation">
            <tr>
                <th><label for="bh_booking_action">What can the parent do?</label></th>
                <td>
                    <select name="bh_booking_action" id="bh_booking_action" class="regular-text">
                        <option value="book_now" <?php selected( $booking_action, 'book_now' ); ?>>Book Now</option>
                        <option value="reserve_spot" <?php selected( $booking_action, 'reserve_spot' ); ?>>Reserve Spot</option>
                        <option value="external" <?php selected( $booking_action, 'external' ); ?>>External Booking</option>
                        <option value="none" <?php selected( $booking_action, 'none' ); ?>>No Online Booking</option>
                    </select>
                </td>
            </tr>
            <tr id="bh-ninja-form-row">
                <th><label for="bh_ninja_form_id">Ninja Form ID</label></th>
                <td>
                    <input type="number" name="bh_ninja_form_id" id="bh_ninja_form_id" value="<?php echo esc_attr( $ninja_form_id ); ?>" class="small-text" min="0" step="1" placeholder="e.g. 12">
                    <p class="description">Used by Book Now. Leave blank if the booking form will be selected elsewhere.</p>
                </td>
            </tr>
            <tr id="bh-external-url-row">
                <th><label for="bh_external_url">External booking URL</label></th>
                <td>
                    <input type="url" name="bh_external_url" id="bh_external_url" value="<?php echo esc_attr( $external_url ); ?>" class="large-text" placeholder="https://">
                    <p class="description">Used by External Booking.</p>
                </td>
            </tr>
            <tr id="bh-reserve-row">
                <th>Reserve Spot</th>
                <td>
                    <label><input type="checkbox" name="bh_reserve_enabled" value="1" <?php checked( $reserve_enabled, true ); ?>> Allow users to reserve a place without immediate payment</label>
                    <p class="description">This remains available for compatibility with existing sessions. Choosing Reserve Spot above automatically enables it.</p>
                </td>
            </tr>
            <tr>
                <th><label for="bh_booking_method">Internal booking method</label></th>
                <td>
                    <select name="bh_booking_method" id="bh_booking_method">
                        <option value="form" <?php selected( $booking_method, 'form' ); ?>>Bubba Hub booking form</option>
                        <option value="external" <?php selected( $booking_method, 'external' ); ?>>External booking link</option>
                        <option value="none" <?php selected( $booking_method, 'none' ); ?>>No online booking</option>
                    </select>
                    <p class="description">Compatibility setting used by the existing Booking Engine.</p>
                </td>
            </tr>
            <tr>
                <th><label for="bh_session_status">Session status</label></th>
                <td>
                    <select name="bh_session_status" id="bh_session_status">
                        <option value="open" <?php selected( $session_status, 'open' ); ?>>Open</option>
                        <option value="closed" <?php selected( $session_status, 'closed' ); ?>>Closed</option>
                    </select>
                </td>
            </tr>
        </table>

        <hr>
        <h3>Ticket types</h3>
        <p class="description">Add different ticket options for this session, such as Adult, Child, Baby or Sibling. Ticket types are saved with the session and can have their own price and capacity.</p>
        <div id="bh-ticket-types">
            <?php foreach ( $ticket_types as $index => $ticket ) : ?>
                <?php $ticket = is_array( $ticket ) ? $ticket : array(); ?>
                <div class="bh-ticket-row" style="border:1px solid #dcdcde;padding:12px;margin:10px 0;background:#fff;">
                    <p>
                        <label><strong>Ticket name</strong><br><input type="text" name="bh_ticket_types[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( isset( $ticket['name'] ) ? $ticket['name'] : '' ); ?>" class="regular-text" placeholder="e.g. Child"></label>
                    </p>
                    <p>
                        <label><strong>Price</strong><br><input type="text" name="bh_ticket_types[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( isset( $ticket['price'] ) ? $ticket['price'] : '' ); ?>" class="small-text" placeholder="5"></label>
                        <label style="margin-left:15px;"><strong>Capacity</strong><br><input type="number" name="bh_ticket_types[<?php echo esc_attr( $index ); ?>][capacity]" value="<?php echo esc_attr( isset( $ticket['capacity'] ) ? $ticket['capacity'] : 0 ); ?>" min="0" step="1" class="small-text"></label>
                        <button type="button" class="button bh-remove-ticket" style="margin-left:15px;">Remove</button>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" id="bh-add-ticket">+ Add ticket type</button>
        <p class="description">Ticket capacity is optional. The existing session capacity remains the overall availability limit until ticket selection is connected to the customer booking form.</p>

        <p class="description" style="margin-top:20px;">A session must be Open, published, linked to a Group and have a valid date to appear in availability.</p>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var action = document.getElementById('bh_booking_action');
        var method = document.getElementById('bh_booking_method');
        var ninjaRow = document.getElementById('bh-ninja-form-row');
        var externalRow = document.getElementById('bh-external-url-row');
        var reserveRow = document.getElementById('bh-reserve-row');
        var reserveCheckbox = document.querySelector('input[name="bh_reserve_enabled"]');

        function syncBookingFields() {
            var value = action ? action.value : 'book_now';
            if ( ninjaRow ) ninjaRow.style.display = value === 'book_now' ? '' : 'none';
            if ( externalRow ) externalRow.style.display = value === 'external' ? '' : 'none';
            if ( reserveRow ) reserveRow.style.display = value === 'reserve_spot' ? '' : 'none';

            if ( method ) {
                if ( value === 'external' ) method.value = 'external';
                else if ( value === 'none' ) method.value = 'none';
                else method.value = 'form';
            }

            if ( reserveCheckbox && value === 'reserve_spot' ) reserveCheckbox.checked = true;
        }

        if ( action ) {
            action.addEventListener('change', syncBookingFields);
            syncBookingFields();
        }

        var container = document.getElementById('bh-ticket-types');
        var add = document.getElementById('bh-add-ticket');
        var index = <?php echo (int) count( $ticket_types ); ?>;

        if ( add && container ) {
            add.addEventListener('click', function () {
                var row = document.createElement('div');
                row.className = 'bh-ticket-row';
                row.style.cssText = 'border:1px solid #dcdcde;padding:12px;margin:10px 0;background:#fff;';
                row.innerHTML = '<p><label><strong>Ticket name</strong><br><input type="text" name="bh_ticket_types[' + index + '][name]" class="regular-text" placeholder="e.g. Child"></label></p>' +
                    '<p><label><strong>Price</strong><br><input type="text" name="bh_ticket_types[' + index + '][price]" class="small-text" placeholder="5"></label>' +
                    '<label style="margin-left:15px;"><strong>Capacity</strong><br><input type="number" name="bh_ticket_types[' + index + '][capacity]" value="0" min="0" step="1" class="small-text"></label>' +
                    '<button type="button" class="button bh-remove-ticket" style="margin-left:15px;">Remove</button></p>';
                container.appendChild(row);
                index++;
            });

            container.addEventListener('click', function (event) {
                if ( event.target.classList.contains('bh-remove-ticket') ) {
                    var ticketRow = event.target.closest('.bh-ticket-row');
                    if ( ticketRow ) ticketRow.remove();
                }
            });
        }

        var dateField = document.getElementById('bh_date');
        if ( dateField ) {
            dateField.addEventListener('blur', function () {
                var value = dateField.value.trim();
                var match = value.match(/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/);
                if ( match ) {
                    dateField.value = ('0' + match[1]).slice(-2) + '/' + ('0' + match[2]).slice(-2) + '/' + match[3];
                }
            });
        }
    });
    </script>
    <?php
}

add_action( 'save_post_bh_session', 'bubbahub_booking_save_session_meta', 10, 3 );

function bubbahub_booking_save_session_meta( $post_id, $post, $update ) {
    if ( ! isset( $_POST['bubbahub_booking_session_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bubbahub_booking_session_nonce'] ) ), 'bubbahub_booking_save_session' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) return;

    $group_id = isset( $_POST['bh_group_id'] ) ? absint( $_POST['bh_group_id'] ) : 0;
    if ( $group_id && get_post_type( $group_id ) !== 'group' ) $group_id = 0;

    $venue_id = isset( $_POST['bh_venue_id'] ) ? absint( $_POST['bh_venue_id'] ) : 0;
    if ( $venue_id && get_post_type( $venue_id ) !== 'venue' ) $venue_id = 0;

    $raw_date = isset( $_POST['bh_date'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_date'] ) ) : '';
    $date = '';

    if ( preg_match( '/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $raw_date, $m ) ) {
        $day   = (int) $m[1];
        $month = (int) $m[2];
        $year  = (int) $m[3];
        if ( checkdate( $month, $day, $year ) ) {
            $date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
        }
    } elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ) {
        $date = $raw_date;
    }

    $start_time = isset( $_POST['bh_start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_start_time'] ) ) : '';
    $end_time   = isset( $_POST['bh_end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_end_time'] ) ) : '';
    $price      = isset( $_POST['bh_price'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_price'] ) ) : '';
    $capacity   = isset( $_POST['bh_capacity'] ) ? absint( $_POST['bh_capacity'] ) : 0;

    $booking_action = isset( $_POST['bh_booking_action'] ) ? sanitize_key( $_POST['bh_booking_action'] ) : 'book_now';
    if ( ! in_array( $booking_action, array( 'book_now', 'reserve_spot', 'external', 'none' ), true ) ) {
        $booking_action = 'book_now';
    }

    $ninja_form_id = isset( $_POST['bh_ninja_form_id'] ) ? absint( $_POST['bh_ninja_form_id'] ) : 0;

    $booking_method = 'form';
    if ( 'external' === $booking_action ) {
        $booking_method = 'external';
    } elseif ( 'none' === $booking_action ) {
        $booking_method = 'none';
    }

    $external_url = isset( $_POST['bh_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['bh_external_url'] ) ) : '';
    if ( 'external' !== $booking_action ) $external_url = '';

    $reserve_enabled = 'reserve_spot' === $booking_action || isset( $_POST['bh_reserve_enabled'] );
    if ( 'book_now' === $booking_action ) {
        $reserve_enabled = isset( $_POST['bh_reserve_enabled'] );
    }
    if ( 'external' === $booking_action || 'none' === $booking_action ) {
        $reserve_enabled = false;
    }

    $session_status = isset( $_POST['bh_session_status'] ) ? sanitize_key( $_POST['bh_session_status'] ) : 'open';
    if ( ! in_array( $session_status, array( 'open', 'closed' ), true ) ) $session_status = 'open';

    $ticket_types = array();
    if ( isset( $_POST['bh_ticket_types'] ) && is_array( $_POST['bh_ticket_types'] ) ) {
        foreach ( wp_unslash( $_POST['bh_ticket_types'] ) as $ticket ) {
            if ( ! is_array( $ticket ) ) continue;

            $name            = isset( $ticket['name'] ) ? sanitize_text_field( $ticket['name'] ) : '';
            $ticket_price    = isset( $ticket['price'] ) ? sanitize_text_field( $ticket['price'] ) : '';
            $ticket_capacity = isset( $ticket['capacity'] ) ? absint( $ticket['capacity'] ) : 0;

            if ( '' === $name ) continue;

            $ticket_types[] = array(
                'name'     => $name,
                'price'    => $ticket_price,
                'capacity' => $ticket_capacity,
            );
        }
    }

    update_post_meta( $post_id, '_bh_group_id', $group_id );
    update_post_meta( $post_id, '_bh_venue_id', $venue_id );
    update_post_meta( $post_id, '_bh_date', $date );
    update_post_meta( $post_id, '_bh_start_time', $start_time );
    update_post_meta( $post_id, '_bh_end_time', $end_time );
    update_post_meta( $post_id, '_bh_price', $price );
    update_post_meta( $post_id, '_bh_capacity', $capacity );
    update_post_meta( $post_id, '_bh_booking_action', $booking_action );
    update_post_meta( $post_id, '_bh_ninja_form_id', $ninja_form_id );
    update_post_meta( $post_id, '_bh_booking_method', $booking_method );
    update_post_meta( $post_id, '_bh_external_url', $external_url );
    update_post_meta( $post_id, '_bh_reserve_enabled', $reserve_enabled ? 1 : 0 );
    update_post_meta( $post_id, '_bh_session_status', $session_status );
    update_post_meta( $post_id, '_bh_ticket_types', $ticket_types );
    update_post_meta( $post_id, '_bh_datetime_sort', $date ? $date . ' ' . ( $start_time ? $start_time : '00:00' ) : '' );
}
