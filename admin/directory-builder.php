<?php
/**
 * BubbaHub Directory Builder
 * Provides an admin drag-and-drop layout builder for directory elements.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function bubbahub_directory_builder_elements() {
    return array(
        'search'       => array( 'label' => 'Search & Filters', 'icon' => '⌕' ),
        'image'        => array( 'label' => 'Image', 'icon' => '▧' ),
        'title'        => array( 'label' => 'Title', 'icon' => 'T' ),
        'description'  => array( 'label' => 'Description', 'icon' => '≡' ),
        'location'     => array( 'label' => 'Location', 'icon' => '⌖' ),
        'schedule'     => array( 'label' => 'Schedule / Business Hours', 'icon' => '◷' ),
        'price'        => array( 'label' => 'Price', 'icon' => '£' ),
        'booking'      => array( 'label' => 'Booking', 'icon' => '▣' ),
        'subscription' => array( 'label' => 'Subscription', 'icon' => '★' ),
        'other_classes'=> array( 'label' => 'Other Classes', 'icon' => '↔' ),
        'map'          => array( 'label' => 'Map', 'icon' => '⌁' ),
        'contact'      => array( 'label' => 'Contact', 'icon' => '@' ),
        'social'       => array( 'label' => 'Social Links', 'icon' => '●' ),
    );
}

function bubbahub_directory_builder_defaults() {
    return array(
        array( 'id' => 'title', 'type' => 'title', 'label' => 'Title' ),
        array( 'id' => 'image', 'type' => 'image', 'label' => 'Image' ),
        array( 'id' => 'description', 'type' => 'description', 'label' => 'Description' ),
        array( 'id' => 'location', 'type' => 'location', 'label' => 'Location' ),
        array( 'id' => 'schedule', 'type' => 'schedule', 'label' => 'Schedule / Business Hours' ),
        array( 'id' => 'price', 'type' => 'price', 'label' => 'Price' ),
        array( 'id' => 'booking', 'type' => 'booking', 'label' => 'Booking' ),
        array( 'id' => 'other_classes', 'type' => 'other_classes', 'label' => 'Other Classes' ),
        array( 'id' => 'map', 'type' => 'map', 'label' => 'Map' ),
    );
}

function bubbahub_directory_builder_get_layout() {
    $layout = get_option( 'bubbahub_directory_builder_layout', array() );
    if ( ! is_array( $layout ) || empty( $layout ) ) return bubbahub_directory_builder_defaults();
    return $layout;
}

function bubbahub_directory_builder_register_menu() {
    add_submenu_page(
        'edit.php?post_type=group',
        'Directory Builder',
        'Directory Builder',
        'manage_options',
        'bubbahub-directory-builder',
        'bubbahub_directory_builder_render'
    );
}
add_action( 'admin_menu', 'bubbahub_directory_builder_register_menu', 30 );

function bubbahub_directory_builder_assets( $hook ) {
    if ( 'group_page_bubbahub-directory-builder' !== $hook ) return;
    wp_enqueue_script( 'jquery-ui-sortable' );
    wp_enqueue_style( 'dashicons' );
    wp_enqueue_style( 'bubbahub-directory-builder', BUBBAHUB_DIRECTORY_URL . 'admin/directory-builder.css', array(), BUBBAHUB_DIRECTORY_VERSION );
    wp_enqueue_script( 'bubbahub-directory-builder', BUBBAHUB_DIRECTORY_URL . 'admin/directory-builder.js', array( 'jquery', 'jquery-ui-sortable' ), BUBBAHUB_DIRECTORY_VERSION, true );
    wp_localize_script( 'bubbahub-directory-builder', 'BubbaHubDirectoryBuilder', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'bubbahub_directory_builder' ),
        'layout'  => bubbahub_directory_builder_get_layout(),
        'elements'=> bubbahub_directory_builder_elements(),
    ) );
}
add_action( 'admin_enqueue_scripts', 'bubbahub_directory_builder_assets' );

function bubbahub_directory_builder_save() {
    check_ajax_referer( 'bubbahub_directory_builder', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
    $raw = isset( $_POST['layout'] ) ? wp_unslash( $_POST['layout'] ) : '[]';
    $layout = json_decode( $raw, true );
    if ( ! is_array( $layout ) ) wp_send_json_error( array( 'message' => 'Invalid layout.' ), 400 );
    $allowed = bubbahub_directory_builder_elements();
    $clean = array();
    foreach ( $layout as $item ) {
        if ( ! is_array( $item ) || empty( $item['type'] ) || ! isset( $allowed[ $item['type'] ] ) ) continue;
        $clean[] = array(
            'id'    => ! empty( $item['id'] ) ? sanitize_key( $item['id'] ) : wp_generate_uuid4(),
            'type'  => sanitize_key( $item['type'] ),
            'label' => $allowed[ $item['type'] ]['label'],
        );
    }
    update_option( 'bubbahub_directory_builder_layout', $clean, false );
    wp_send_json_success( array( 'layout' => $clean, 'message' => 'Builder layout saved.' ) );
}
add_action( 'wp_ajax_bubbahub_directory_builder_save', 'bubbahub_directory_builder_save' );

function bubbahub_directory_builder_render() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission to access this page.' ) );
    $elements = bubbahub_directory_builder_elements();
    ?>
    <div class="wrap bh-builder-wrap">
        <h1>Directory Builder</h1>
        <p class="description">Drag elements from the library into the layout. Reorder them to control the directory/listing display.</p>
        <div class="bh-builder-app">
            <aside class="bh-builder-library">
                <h2>Elements</h2>
                <p>Drag an element into the canvas.</p>
                <div class="bh-builder-palette">
                    <?php foreach ( $elements as $type => $element ) : ?>
                        <div class="bh-builder-palette-item" draggable="true" data-type="<?php echo esc_attr( $type ); ?>">
                            <span class="bh-builder-icon"><?php echo esc_html( $element['icon'] ); ?></span>
                            <span><?php echo esc_html( $element['label'] ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>
            <main class="bh-builder-main">
                <div class="bh-builder-toolbar">
                    <strong>Layout</strong>
                    <span>Drag to reorder</span>
                    <button type="button" class="button button-primary" id="bh-builder-save">Save Layout</button>
                </div>
                <div id="bh-builder-canvas" class="bh-builder-canvas" aria-label="Directory layout canvas"></div>
                <div id="bh-builder-message" class="bh-builder-message" role="status"></div>
            </main>
        </div>
    </div>
    <?php
}
