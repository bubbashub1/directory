<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_leader_venue_form( $post_id = 0 ) {
    if ( ! function_exists( 'acf_form' ) ) {
        echo '<div class="bh-form-warning"><strong>ACF Pro is required.</strong><p>Please activate ACF Pro to manage venues from the front end.</p></div>';
        return;
    }

    if ( $post_id && ! bubbahub_leader_owned_post( $post_id, 'venue' ) ) {
        echo '<div class="bh-form-warning">You cannot edit this venue.</div>';
        return;
    }

    $is_new = ! $post_id;
    $return_url = add_query_arg( 'venue_saved', '1', home_url( '/leader/' ) ) . '#venues';

    acf_form( array(
        'post_id'            => $is_new ? 'new_post' : $post_id,
        'new_post'           => array(
            'post_type'   => 'venue',
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ),
        'post_title'         => true,
        'post_content'       => true,
        'post_title_label'   => 'Venue name',
        'post_content_label' => 'Venue details',
        'field_groups'       => array( 'group_6aa6749620635' ),
        'uploader'           => 'wp',
        'return'             => $return_url,
        'submit_value'       => $is_new ? 'Create venue' : 'Save venue',
        'updated_message'    => 'Venue saved successfully.',
        'html_before_fields' => '<div class="bh-acf-fields bh-acf-venue-fields">',
        'html_after_fields'  => '</div>',
        'html_before_submit' => '<div class="bh-acf-submit">',
        'html_after_submit'  => '</div>',
        'form_attributes'   => array( 'class' => 'bh-leader-form bh-acf-front-form' ),
    ) );
}
