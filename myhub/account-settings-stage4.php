<?php
/**
 * Bubba Hub My Hub - Stage 4 personalisation bridge.
 *
 * Connects Account Settings preferences to the existing My Groups engine.
 * It deliberately uses the existing group CPT/taxonomies and bh_child CPT;
 * no second recommendation system or duplicate profile data is created.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'bubbahub_stage4_sync_preferences', 8 );
add_action( 'wp_ajax_bubbahub_myhub_selected_children', 'bubbahub_stage4_save_selected_children' );
add_filter( 'bubbahub_myhub_selected_children', 'bubbahub_stage4_get_selected_children', 10, 1 );

/** Return the taxonomy already used by Account Settings, when available. */
function bubbahub_stage4_interest_taxonomy() {
    if ( function_exists( 'bubbahub_stage2_taxonomy' ) ) {
        return bubbahub_stage2_taxonomy();
    }

    foreach ( array( 'interest', 'interests', 'group_tag', 'group_tags', 'post_tag' ) as $taxonomy ) {
        if ( taxonomy_exists( $taxonomy ) && is_object_in_taxonomy( 'group', $taxonomy ) ) return $taxonomy;
    }
    return '';
}

/**
 * Keep the recommendation engine's legacy user_interests value in sync with
 * the Stage 2 term-ID storage. Values are saved as slugs and names so the
 * existing matcher can work with either representation.
 */
function bubbahub_stage4_sync_preferences() {
    if ( ! is_user_logged_in() ) return;

    $uid      = get_current_user_id();
    $term_ids = get_user_meta( $uid, 'bubbahub_interest_term_ids', true );
    $taxonomy = get_user_meta( $uid, 'bubbahub_interest_taxonomy', true );

    if ( ! is_array( $term_ids ) ) $term_ids = array();
    if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) $taxonomy = bubbahub_stage4_interest_taxonomy();

    $interests = array();
    if ( $taxonomy && $term_ids ) {
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'include'    => array_map( 'absint', $term_ids ),
            'hide_empty' => false,
        ) );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $interests[] = $term->slug;
                $interests[] = $term->name;
            }
        }
    }

    $interests = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $interests ) ) ) );
    update_user_meta( $uid, 'user_interests', $interests );

    if ( ! get_user_meta( $uid, 'bubbahub_selected_children', true ) ) {
        update_user_meta( $uid, 'bubbahub_selected_children', array() );
    }
}

/** Save the selected child IDs from My Hub into the logged-in user's account. */
function bubbahub_stage4_save_selected_children() {
    if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Please log in.' ), 401 );
    check_ajax_referer( 'bubbahub_myhub_groups', 'nonce' );

    $ids = isset( $_POST['ids'] ) ? (array) wp_unslash( $_POST['ids'] ) : array();
    $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

    $owned = get_posts( array(
        'post_type'      => 'bh_child',
        'post_status'    => array( 'publish', 'private' ),
        'author'         => get_current_user_id(),
        'post__in'       => $ids,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ) );

    $owned = array_map( 'absint', $owned );
    update_user_meta( get_current_user_id(), 'bubbahub_selected_children', $owned );
    update_user_meta( get_current_user_id(), 'bh_myhub_selected_children', $owned );

    wp_send_json_success( array( 'selected_children' => $owned ) );
}

/** Public helper/filter for future My Hub components. */
function bubbahub_stage4_get_selected_children( $selected = array() ) {
    if ( ! is_user_logged_in() ) return array();
    $saved = get_user_meta( get_current_user_id(), 'bubbahub_selected_children', true );
    if ( ! is_array( $saved ) ) $saved = get_user_meta( get_current_user_id(), 'bh_myhub_selected_children', true );
    return array_values( array_unique( array_filter( array_map( 'absint', (array) $saved ) ) ) );
}

/**
 * Convenience helper for Stage 4+ dashboard components. Returns the group
 * IDs that currently match the user's saved interests and selected children.
 */
function bubbahub_stage4_suggested_group_ids( $limit = 12 ) {
    if ( ! is_user_logged_in() || ! function_exists( 'bubbahub_myhub_groups_suggested_ids' ) ) return array();
    $selected = apply_filters( 'bubbahub_myhub_selected_children', array() );
    $ids      = bubbahub_myhub_groups_suggested_ids( array(), $selected );
    return array_slice( array_values( array_unique( array_map( 'absint', $ids ) ) ), 0, max( 1, absint( $limit ) ) );
}
