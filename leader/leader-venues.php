<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_leader_venue_form( $post_id = 0 ) {
	$title = $post_id ? get_the_title( $post_id ) : '';
	$content = $post_id ? get_post_field( 'post_content', $post_id ) : '';
	$status = $post_id ? get_post_status( $post_id ) : 'draft';
	?>
	<form class="bh-leader-form" method="post">
		<input type="hidden" name="bh_leader_action" value="save_venue"><input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
		<?php wp_nonce_field( 'bh_leader_save_venue', 'bh_leader_venue_nonce' ); ?>
		<p><label>Venue name</label><input type="text" name="post_title" value="<?php echo esc_attr( $title ); ?>" required></p>
		<p><label>Venue details</label><textarea name="post_content" rows="7"><?php echo esc_textarea( $content ); ?></textarea></p>
		<?php if ( function_exists( 'acf_form' ) ) : ?>
			<?php acf_form( array( 'post_id' => $post_id ? $post_id : 'new_post', 'form' => false, 'return' => false, 'html_before_fields' => '<div class="bh-acf-fields">', 'html_after_fields' => '</div>' ) ); ?>
		<?php endif; ?>
		<p><label>Status</label><select name="post_status"><option value="draft" <?php selected( $status, 'draft' ); ?>>Draft</option><option value="pending" <?php selected( $status, 'pending' ); ?>>Submit for review</option><?php if ( current_user_can( 'publish_posts' ) ) : ?><option value="publish" <?php selected( $status, 'publish' ); ?>>Published</option><?php endif; ?></select></p>
		<button class="bh-leader-button" type="submit"><?php echo $post_id ? 'Save venue' : 'Add venue'; ?></button>
	</form>
	<?php
}

function bubbahub_leader_handle_venue_save() {
	if ( empty( $_POST['bh_leader_action'] ) || $_POST['bh_leader_action'] !== 'save_venue' || ! is_user_logged_in() ) return;
	if ( empty( $_POST['bh_leader_venue_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bh_leader_venue_nonce'] ) ), 'bh_leader_save_venue' ) ) return;
	$id = absint( $_POST['post_id'] ?? 0 );
	if ( $id && ! bubbahub_leader_owned_post( $id, 'venue' ) ) wp_die( 'You cannot edit this venue.' );
	$status = sanitize_key( $_POST['post_status'] ?? 'draft' );
	$allowed = array( 'draft', 'pending' ); if ( current_user_can( 'publish_posts' ) ) $allowed[] = 'publish'; if ( ! in_array( $status, $allowed, true ) ) $status = 'draft';
	$data = array( 'post_type' => 'venue', 'post_title' => sanitize_text_field( wp_unslash( $_POST['post_title'] ?? '' ) ), 'post_content' => wp_kses_post( wp_unslash( $_POST['post_content'] ?? '' ) ), 'post_status' => $status );
	if ( $id ) { $data['ID'] = $id; $saved = wp_update_post( wp_slash( $data ), true ); } else { $data['post_author'] = get_current_user_id(); $saved = wp_insert_post( wp_slash( $data ), true ); }
	if ( ! is_wp_error( $saved ) && function_exists( 'acf_save_post' ) ) acf_save_post( $saved );
	if ( ! is_wp_error( $saved ) ) wp_safe_redirect( add_query_arg( 'venue_saved', '1', home_url( '/leader/#venues' ) ) );
	exit;
}
add_action( 'template_redirect', 'bubbahub_leader_handle_venue_save' );
