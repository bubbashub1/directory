<?php get_header(); ?>
<main id="main-content">
<?php while ( have_posts() ) : the_post();
  $image = get_the_post_thumbnail_url(get_the_ID(),'large');
  $age = function_exists('get_field') ? get_field('age_range') : get_post_meta(get_the_ID(),'age_range',true);
  $price = function_exists('get_field') ? get_field('price') : get_post_meta(get_the_ID(),'price',true);
  $address = function_exists('get_field') ? get_field('address') : get_post_meta(get_the_ID(),'address',true);
  $hours = function_exists('get_field') ? get_field('business_hours') : get_post_meta(get_the_ID(),'business_hours',true);
  $terms = get_the_terms(get_the_ID(),'region');
  $region = (!is_wp_error($terms) && !empty($terms)) ? implode(', ',wp_list_pluck($terms,'name')) : '';
?>
<section class="bh-page-header"><div class="bh-container"><span class="bh-eyebrow">Bubba Hub listing</span><h1 class="bh-page-title"><?php the_title(); ?></h1><p class="bh-page-intro"><?php echo esc_html($region); ?></p></div></section>
<section class="bh-entry"><div class="bh-container bh-entry-grid">
  <article class="bh-entry-main">
    <?php if($image): ?><img class="bh-featured" src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr(get_the_title()); ?>"><?php endif; ?>
    <?php the_content(); ?>
  </article>
  <aside class="bh-entry-sidebar">
    <h2>At a glance</h2>
    <ul class="bh-meta-list">
      <?php if($region): ?><li><span class="bh-meta-label">Location</span><?php echo esc_html($region); ?></li><?php endif; ?>
      <?php if($age): ?><li><span class="bh-meta-label">Age range</span><?php echo esc_html(is_array($age)?implode(', ',$age):$age); ?></li><?php endif; ?>
      <?php if($price): ?><li><span class="bh-meta-label">Price</span><?php echo esc_html(is_array($price)?implode(', ',$price):$price); ?></li><?php endif; ?>
      <?php if($address): ?><li><span class="bh-meta-label">Venue</span><?php echo esc_html(is_array($address)?implode(', ',$address):$address); ?></li><?php endif; ?>
      <?php if($hours): ?><li><span class="bh-meta-label">Schedule</span><?php echo esc_html(is_array($hours)?wp_json_encode($hours):$hours); ?></li><?php endif; ?>
    </ul>
    <?php if(shortcode_exists('bubbahub_booking')): ?><div style="margin-top:20px"><?php echo do_shortcode('[bubbahub_booking group_id="'.absint(get_the_ID()).'"]'); ?></div><?php endif; ?>
  </aside>
</div></section>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
