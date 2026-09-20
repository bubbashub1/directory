<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_leader_owned_post( $post_id, $post_type ) {
    $post = get_post( absint( $post_id ) );
    return $post && $post->post_type === $post_type && (int) $post->post_author === get_current_user_id();
}

function bubbahub_leader_listing_form( $post_id = 0 ) {
    if ( ! function_exists( 'acf_form' ) ) {
        echo '<div class="bh-form-warning"><strong>ACF Pro is required.</strong><p>Please activate ACF Pro to manage listings from the front end.</p></div>';
        return;
    }

    if ( $post_id && ! bubbahub_leader_owned_post( $post_id, 'group' ) ) {
        echo '<div class="bh-form-warning">You cannot edit this listing.</div>';
        return;
    }

    $is_new = ! $post_id;
    $return_url = add_query_arg( 'listing_saved', '1', home_url( '/leader/' ) ) . '#listings';

    acf_form( array(
        'post_id'              => $is_new ? 'new_post' : $post_id,
        'new_post'             => array(
            'post_type'   => 'group',
            'post_status' => 'draft',
            'post_author' => get_current_user_id(),
        ),
        'post_title'           => true,
        'post_content'         => true,
        'post_content_label'   => 'Description',
        'post_title_label'     => 'Listing name',
        'field_groups'         => array(
            'group_business_hours_repeater',
            'group_6aa6749620635',
            'group_6aa6773f3badc',
            'group_6aa674da415e8',
            'group_bubbahub_group_accessibility_activity',
        ),
        'uploader'             => 'wp',
        'return'               => $return_url,
        'submit_value'         => $is_new ? 'Create listing' : 'Save listing',
        'updated_message'      => 'Listing saved successfully.',
        'html_before_fields'   => '<div class="bh-acf-fields bh-acf-listing-fields">',
        'html_after_fields'    => '</div>',
        'html_before_submit'   => '<div class="bh-acf-submit">',
        'html_after_submit'    => '</div>',
        'form_attributes'     => array( 'class' => 'bh-leader-form bh-acf-front-form' ),
    ) );
}
