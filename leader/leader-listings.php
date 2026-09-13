<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_leader_owned_post( $post_id, $post_type ) {
    $post = get_post( absint( $post_id ) );
    return $post && $post->post_type === $post_type && (int) $post->post_author === get_current_user_id();
}

function bubbahub_leader_listing_form( $post_id = 0 ) {
    $title = $post_id ? get_the_title( $post_id ) : '';
    $content = $post_id ? get_post_field( 'post_content', $post_id ) : '';
    $status = $post_id ? get_post_status( $post_id ) : 'draft';
    ?>
    <form class="bh-leader-form" method="post">
        <input type="hidden" name="bh_leader_action" value="save_listing">
        <input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
        <?php wp_nonce_field( 'bh_leader_save_listing', 'bh_leader_nonce' ); ?>
        <p><label>Listing name</label><input type="text" name="post_title" value="<?php echo esc_attr( $title ); ?>" required></p>
        <p><label>Description</label><textarea name="post_content" rows="7"><?php echo esc_textarea( $content ); ?></textarea></p>
        <?php if ( function_exists( 'acf_form' ) && $post_id ) : ?>
            <?php acf_form( array( 'post_id' => $post_id, 'form' => false, 'return' => false, 'html_before_fields' => '<div class="bh-acf-fields">', 'html_after_fields' => '</div>' ) ); ?>
        <?php endif; ?>
        <p><label>Status</label><select name="post_status"><option value="draft" <?php selected( $status, 'draft' ); ?>>Draft</option><option value="pending" <?php selected( $status, 'pending' ); ?>>Submit for review</option><?php if ( current_user_can( 'publish_posts' ) ) : ?><option value="publish" <?php selected( $status, 'publish' ); ?>>Published</option><?php endif; ?></select></p>
        <button class="bh-leader-button" type="submit"><?php echo $post_id ? 'Save listing' : 'Create listing'; ?></button>
    </form>
    <?php
}

function bubbahub_leader_handle_listing_save() {
    if ( empty( $_POST['bh_leader_action'] ) || 'save_listing' !== $_POST['bh_leader_action'] || ! is_user_logged_in() ) return;
    if ( empty( $_POST['bh_leader_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_leader_nonce'] ) ), 'bh_leader_save_listing' ) ) return;

    $id = absint( $_POST['post_id'] ?? 0 );
    if ( $id && ! bubbahub_leader_owned_post( $id, 'group' ) ) wp_die( 'You cannot edit this listing.' );

    $title  = sanitize_text_field( wp_unslash( $_POST['post_title'] ?? '' ) );
    $status = sanitize_key( $_POST['post_status'] ?? 'draft' );
    $allowed = array( 'draft', 'pending' );
    if ( current_user_can( 'publish_posts' ) ) $allowed[] = 'publish';
    if ( ! in_array( $status, $allowed, true ) ) $status = 'draft';

    $data = array(
        'post_type'    => 'group',
        'post_title'   => $title,
        'post_content' => wp_kses_post( wp_unslash( $_POST['post_content'] ?? '' ) ),
        'post_status'  => $status,
    );

    if ( $id ) {
        $data['ID'] = $id;
        $saved = wp_update_post( wp_slash( $data ), true );
    } else {
        $data['post_author'] = get_current_user_id();
        $saved = wp_insert_post( wp_slash( $data ), true );
    }

    if ( is_wp_error( $saved ) ) return;
    if ( function_exists( 'acf_save_post' ) ) acf_save_post( $saved );

    wp_safe_redirect( add_query_arg( 'listing_saved', '1', home_url( '/leader/#listings' ) ) );
    exit;
}
add_action( 'template_redirect', 'bubbahub_leader_handle_listing_save' );
