<?php
/**
 * BubbaHub Happiness Green theme.
 * Calm, editorial styling inspired by modern coaching layouts and purpose-built
 * for the BubbaHub directory, My Hub and leader experience.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
define( 'BUBBAHUB_THEME_VERSION', '1.1.0' );
add_action( 'after_setup_theme', function(){
 add_theme_support('title-tag'); add_theme_support('post-thumbnails'); add_theme_support('html5',array('search-form','comment-form','comment-list','gallery','caption','style','script')); add_theme_support('custom-logo',array('height'=>80,'width'=>280,'flex-height'=>true,'flex-width'=>true)); add_theme_support('responsive-embeds'); add_theme_support('align-wide'); add_theme_support('editor-styles');
 register_nav_menus(array('primary'=>'Primary Menu','footer'=>'Footer Menu'));
});
add_action('wp_enqueue_scripts',function(){
 wp_enqueue_style('bubbahub-happiness-green',get_stylesheet_uri(),array(),BUBBAHUB_THEME_VERSION);
 wp_enqueue_style('bubbahub-fonts','https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&family=Work+Sans:wght@400;500;600;700&display=swap',array(),null);
});
add_filter('body_class',function($classes){$classes[]='bh-happiness-green';return $classes;});
function bubbahub_theme_brand(){ if(has_custom_logo()){the_custom_logo();return;} echo '<a class="bh-brand" href="'.esc_url(home_url('/')).'"><span class="bh-brand-mark" aria-hidden="true">B</span><span>Bubba Hub</span></a>'; }
function bubbahub_theme_menu(){
 if(has_nav_menu('primary')){wp_nav_menu(array('theme_location'=>'primary','container'=>false,'menu_id'=>'bh-primary-nav','menu_class'=>'bh-nav','fallback_cb'=>false));return;}
 echo '<nav class="bh-nav" id="bh-primary-nav" aria-label="Primary navigation"><a href="'.esc_url(home_url('/')).'">Home</a><a href="'.esc_url(home_url('/groups/')).'">Find Groups</a><a href="'.esc_url(home_url('/my-hub/')).'">My Hub</a><a href="'.esc_url(home_url('/list-your-group/')).'">List Your Group</a></nav>';
}
function bubbahub_theme_directory_shortcode(){return shortcode_exists('bubbahub_directory')?do_shortcode('[bubbahub_directory]'):'<div class="bh-content-card"><p>The BubbaHub Directory plugin is not currently active.</p></div>';}
add_action('wp_footer',function(){?><script>(function(){const t=document.querySelector('.bh-nav-toggle'),n=document.getElementById('bh-primary-nav');if(!t||!n)return;t.addEventListener('click',()=>{const o=n.classList.toggle('is-open');t.setAttribute('aria-expanded',o?'true':'false');});})();</script><?php});
