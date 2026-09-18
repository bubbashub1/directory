<?php
/**
 * BubbaHub Support Hub
 * Questions -> matched leaders, specialist articles, useful links and app discovery.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BUBBAHUB_SUPPORT_VERSION', '1.1.0' );

function bubbahub_support_is_leader() {
    if ( ! is_user_logged_in() ) return false;
    if ( current_user_can( 'manage_options' ) ) return true;
    return (bool) array_intersect( array( 'leader', 'leaderpro' ), (array) wp_get_current_user()->roles );
}

function bubbahub_support_register_cpt() {
    register_post_type( 'bh_support_request', array(
        'labels' => array( 'name'=>'Support Questions', 'singular_name'=>'Support Question' ),
        'public'=>false, 'show_ui'=>true, 'show_in_menu'=>false, 'supports'=>array('title','editor','author'),
    ) );
    register_post_type( 'bh_support_article', array(
        'labels' => array( 'name'=>'Support Articles', 'singular_name'=>'Support Article', 'add_new_item'=>'Add Specialist Article', 'edit_item'=>'Edit Specialist Article' ),
        'public'=>true, 'show_in_rest'=>true, 'has_archive'=>true,
        'rewrite'=>array('slug'=>'support/articles'), 'supports'=>array('title','editor','excerpt','thumbnail','author'),
    ) );
}
add_action( 'init', 'bubbahub_support_register_cpt' );

function bubbahub_support_defaults() {
    $defaults = array(
        'keywords' => array( 'sleep','feeding','breastfeeding','weaning','speech','language','toilet training','potty training','behaviour','parenting','postnatal','pregnancy','baby massage','mental health','SEND','autism','ADHD','financial support','domestic abuse','bereavement' ),
        'links' => array(
            array('title'=>'GOV.UK Family Hubs','url'=>'https://www.gov.uk/find-family-hub-local-area','description'=>'Find local family hub support in England.','keywords'=>'family hub,parenting,baby,support'),
            array('title'=>'Devon Best Start Family Hubs','url'=>'https://www.devon.gov.uk/children-families-education/child-family-support/family-support/family-hubs/','description'=>'Family support, advice and services across Devon.','keywords'=>'devon,family hub,parenting,baby'),
            array('title'=>'Cornwall Family Hubs','url'=>'https://www.cornwall.gov.uk/health-and-social-care/childrens-services/family-hubs/','description'=>'Family hubs and support for families across Cornwall.','keywords'=>'cornwall,family hub,parenting,baby'),
        ),
        'apps' => array(),
    );
    $saved = get_option( 'bubbahub_support_settings', array() );
    return array(
        'keywords' => !empty($saved['keywords']) ? $saved['keywords'] : $defaults['keywords'],
        'links' => isset($saved['links']) ? $saved['links'] : $defaults['links'],
        'apps' => isset($saved['apps']) ? $saved['apps'] : $defaults['apps'],
    );
}

function bubbahub_support_match_leaders( $question, $topic, $region='' ) {
    $settings = bubbahub_support_defaults();
    $terms = array_filter( preg_split('/\s+/', strtolower( $topic . ' ' . $question ) ) );
    $leaders = get_users( array( 'role__in'=>array('leader','leaderpro'), 'fields'=>array('ID','user_email','display_name','first_name') ) );
    $matches = array();
    foreach ( $leaders as $leader ) {
        $leader_specialisms = strtolower( (string) get_user_meta( $leader->ID, 'bh_support_specialisms', true ) );
        $groups = get_posts( array( 'post_type'=>'group','post_status'=>array('publish','draft','pending','private'), 'author'=>$leader->ID, 'posts_per_page'=>-1, 'fields'=>'ids', 'no_found_rows'=>true ) );
        if ( ! $groups ) continue;
        $score=0; $matched=array();
        foreach ( $groups as $gid ) {
            $hay = strtolower( get_the_title($gid).' '.wp_strip_all_tags(get_post_field('post_content',$gid)).' '.implode(' ', wp_get_post_terms($gid,'region',array('fields'=>'names'))) );
            if ( $leader_specialisms ) {
                foreach ( $terms as $term ) { if ( strlen( $term ) >= 4 && strpos( $leader_specialisms, $term ) !== false ) $score += 4; }
            }
            foreach ( $settings['keywords'] as $keyword ) {
                $keyword=strtolower(trim($keyword)); if($keyword==='' ) continue;
                if ( strpos($hay,$keyword)!==false ) { $score+=2; $matched[]=$keyword; }
            }
            if ( $region && strpos($hay,strtolower($region))!==false ) $score+=3;
            foreach ( $terms as $term ) {
                if ( strlen($term)<4 ) continue;
                if ( strpos($hay,$term)!==false ) $score++;
            }
        }
        if ( $score>0 ) $matches[$leader->ID]=array('score'=>$score,'matched'=>array_values(array_unique($matched)));
    }
    uasort($matches,function($a,$b){return $b['score']<=>$a['score'];});
    return array_slice($matches,0,8,true);
}

function bubbahub_support_send_question( $data ) {
    $post_id = wp_insert_post(array(
        'post_type'=>'bh_support_request','post_status'=>'publish',
        'post_title'=>sprintf('Support question from %s – %s',$data['name'],$data['topic']),
        'post_content'=>$data['question'],'post_author'=>get_current_user_id(),
    ),true);
    if(is_wp_error($post_id)) return $post_id;
    foreach(array('name','email','topic','region') as $key) update_post_meta($post_id,'_bh_support_'.$key,$data[$key]);
    update_post_meta($post_id,'_bh_support_status','sent');
    $matches=bubbahub_support_match_leaders($data['question'],$data['topic'],$data['region']);
    $sent=0;
    foreach($matches as $leader_id=>$match){
        $email=get_userdata($leader_id); if(!$email||!is_email($email->user_email)) continue;
        $leader_page_id=absint(get_option('bubbahub_support_leader_page_id'));
        $reply_url=$leader_page_id ? get_permalink($leader_page_id) : home_url('/leader/');
        $body="A BubbaHub family has asked for support that may match your experience.\n\n";
        $body.="Topic: {$data['topic']}\nArea: {$data['region']}\n\nQuestion:\n{$data['question']}\n\n";
        $body.="Matched areas: ".implode(', ',$match['matched'])."\n\n";
        $body.="Log in to your Leader Portal to reply:\n".$reply_url."\n\nPlease only provide support within your professional/appropriate scope.";
        if(wp_mail($email->user_email,'BubbaHub support question – '.$data['topic'],$body)) $sent++;
    }
    update_post_meta($post_id,'_bh_support_matches',array_keys($matches));
    update_post_meta($post_id,'_bh_support_sent_count',$sent);
    wp_mail($data['email'],'We have sent your BubbaHub support question','Thank you for contacting BubbaHub. We have shared your question with relevant BubbaHub leaders. If a leader responds, their reply will be emailed to you.\n\nYour question:\n'.$data['question']);
    return array('id'=>$post_id,'matches'=>$sent);
}

function bubbahub_support_reply_handler() {
    if(empty($_POST['bh_support_reply_action'])||!is_user_logged_in()) return;
    if(!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bh_support_reply_nonce']??'')),'bh_support_reply')) return;
    if(!bubbahub_support_is_leader()) return;
    $id=absint($_POST['request_id']??0); $reply=trim(sanitize_textarea_field(wp_unslash($_POST['reply']??'')));
    if(!$id||$reply==='') return;
    $matches=(array)get_post_meta($id,'_bh_support_matches',true);
    if(!current_user_can('manage_options')&&!in_array(get_current_user_id(),array_map('intval',$matches),true)) return;
    $email=sanitize_email(get_post_meta($id,'_bh_support_email',true)); if(!is_email($email)) return;
    $leader=wp_get_current_user();
    $history=(array)get_post_meta($id,'_bh_support_replies',true);
    $history[]=array('leader'=>$leader->ID,'name'=>$leader->display_name,'reply'=>$reply,'time'=>current_time('mysql'));
    update_post_meta($id,'_bh_support_replies',$history);
    wp_mail($email,'BubbaHub support reply from '.$leader->display_name,"A BubbaHub specialist has replied to your question.\n\n".$reply."\n\nYou can reply to this email if you need to continue the conversation.");
    update_post_meta($id,'_bh_support_status','replied');
    $leader_page_id=absint(get_option('bubbahub_support_leader_page_id'));
    wp_safe_redirect(add_query_arg('bh_support_replied','1',$leader_page_id?get_permalink($leader_page_id):home_url('/leader/')));
    exit;
}
add_action('template_redirect','bubbahub_support_reply_handler');

function bubbahub_support_leader_inbox() {
    if(!bubbahub_support_is_leader()) return '<div class="bh-support-card"><h2>Leader access required</h2><p>Please log in as an approved BubbaHub leader.</p></div>';
    $ids=get_posts(array('post_type'=>'bh_support_request','post_status'=>'publish','posts_per_page'=>30,'meta_query'=>array(array('key'=>'_bh_support_matches','value'=>'"'.get_current_user_id().'"','compare'=>'LIKE')),'orderby'=>'date','order'=>'DESC','fields'=>'ids'));
    ob_start(); ?>
    <div class="bh-support-inbox"><h2>Support questions</h2>
    <?php if(!$ids): ?><div class="bh-support-empty">No matched support questions yet.</div><?php endif;
    foreach($ids as $id): $name=get_post_meta($id,'_bh_support_name',true); $topic=get_post_meta($id,'_bh_support_topic',true); $question=get_post_field('post_content',$id); ?>
      <article class="bh-support-request"><div><span class="bh-support-kicker"><?php echo esc_html($topic); ?></span><h3><?php echo esc_html($name); ?></h3><p><?php echo esc_html($question); ?></p></div>
      <form method="post"><input type="hidden" name="bh_support_reply_action" value="1"><input type="hidden" name="request_id" value="<?php echo esc_attr($id); ?>"><?php wp_nonce_field('bh_support_reply','bh_support_reply_nonce'); ?><textarea name="reply" rows="4" placeholder="Write a helpful reply..." required></textarea><button type="submit" class="bh-support-button">Email reply</button></form></article>
    <?php endforeach; ?></div><?php return ob_get_clean();
}
add_shortcode('bubbahub_support_leader_inbox','bubbahub_support_leader_inbox');

function bubbahub_support_article_form() {
    if(!bubbahub_support_is_leader()) return '<p>Specialist articles are available to approved BubbaHub specialists and leaders.</p>';
    if(!empty($_POST['bh_support_article_action'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bh_support_article_nonce']??'')),'bh_support_article')){
        $title=sanitize_text_field(wp_unslash($_POST['title']??'')); $body=wp_kses_post(wp_unslash($_POST['content']??''));
        if($title&&$body) wp_insert_post(array('post_type'=>'bh_support_article','post_status'=>'pending','post_title'=>$title,'post_content'=>$body,'post_author'=>get_current_user_id()));
    }
    ob_start(); ?><div class="bh-support-article-form"><h2>Share your expertise</h2><p>Publish a helpful family-support article. Articles are reviewed before going live.</p><form method="post"><?php wp_nonce_field('bh_support_article','bh_support_article_nonce'); ?><input type="hidden" name="bh_support_article_action" value="1"><p><label>Title</label><input name="title" required></p><p><label>Article</label><textarea name="content" rows="10" required></textarea></p><button class="bh-support-button">Submit for review</button></form></div><?php return ob_get_clean();
}

function bubbahub_support_public() {
    ob_start(); ?>
    <div class="bh-support-hub">
      <section class="bh-support-hero"><span class="bh-support-kicker">BubbaHub Support</span><h1>Find the right support for your family</h1><p>Ask a question, explore specialist advice, useful links and family-friendly apps.</p></section>
      <section class="bh-support-topics"><div class="bh-support-section-head"><span class="bh-support-kicker">Choose a topic</span><h2>What can we help with?</h2><p>Pick a topic to focus the support resources and app suggestions.</p></div><div class="bh-support-topic-grid"><?php foreach(array_slice(bubbahub_support_defaults()['keywords'],0,20) as $topic): ?><button type="button" class="bh-support-topic" data-topic="<?php echo esc_attr(strtolower($topic)); ?>"><?php echo esc_html(ucwords($topic)); ?></button><?php endforeach; ?></div><button type="button" class="bh-support-topic-clear">Show all support</button></section><section class="bh-support-question bh-support-card"><div><span class="bh-support-kicker">Ask a question</span><h2>Not sure who can help?</h2><p>Tell us what you need and we'll send your question to relevant BubbaHub leaders.</p></div>
      <form method="post"><input type="hidden" name="bh_support_action" value="ask"><?php wp_nonce_field('bh_support_question','bh_support_question_nonce'); ?><div class="bh-support-form-grid"><p><label>Your name</label><input name="support_name" required></p><p><label>Email</label><input type="email" name="support_email" required></p><p><label>What do you need help with?</label><input id="bh-support-topic-input" name="support_topic" placeholder="e.g. sleep, feeding, SEND" required></p><p><label>Your area</label><input name="support_region" placeholder="e.g. Torbay, Exeter, Cornwall"></p></div><p><label>Your question</label><textarea name="support_question" rows="6" required></textarea></p><button class="bh-support-button">Ask for support</button></form></section>
      <section class="bh-support-section"><div class="bh-support-section-head"><div><span class="bh-support-kicker">Specialist advice</span><h2>From our specialists</h2></div></div><div class="bh-support-article-grid"><?php $q=new WP_Query(array('post_type'=>'bh_support_article','post_status'=>'publish','posts_per_page'=>6)); while($q->have_posts()):$q->the_post(); ?><article class="bh-support-article"><span>Specialist advice</span><h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><p><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_content()),28)); ?></p><a href="<?php the_permalink(); ?>">Read article →</a></article><?php endwhile; wp_reset_postdata(); ?></div></section>
      <?php echo bubbahub_support_useful_links(); ?>
      <?php echo bubbahub_support_apps(); ?>
    </div><?php return ob_get_clean();
}
add_shortcode('bubbahub_support','bubbahub_support_public');
add_shortcode('bubbahub_support_article_form','bubbahub_support_article_form');

function bubbahub_support_resource_matches($item_keywords,$topic){
    if(!$topic) return true;
    $hay=strtolower((string)$item_keywords); $topic=strtolower(trim($topic));
    if(!$hay) return false;
    foreach(array_filter(preg_split('/[,\\s]+/',$topic)) as $term){ if(strlen($term)>=3 && strpos($hay,$term)!==false) return true; }
    return false;
}
function bubbahub_support_useful_links($topic='') {
    $s=bubbahub_support_defaults(); $keywords=$s['keywords']; $links=$s['links'];
    if(!$topic && !empty($_GET['support_topic'])) $topic=sanitize_text_field(wp_unslash($_GET['support_topic']));
    $html='<section class="bh-support-section" id="support-resources"><div class="bh-support-section-head"><span class="bh-support-kicker">Useful links</span><h2>'.($topic?'Recommended support for '.esc_html(ucwords($topic)):'Find trusted support').'</h2><p>Explore trusted resources or search locally on Google Maps.</p></div><div class="bh-support-link-grid">';
    foreach($links as $l){if(empty($l['url'])||($topic&&!bubbahub_support_resource_matches($l['keywords']??'', $topic)))continue;$html.='<a class="bh-support-link" href="'.esc_url($l['url']).'" target="_blank" rel="noopener"><strong>'.esc_html($l['title']).'</strong><span>'.esc_html($l['description']).' ↗</span></a>';}
    $show=$topic?array($topic):array_slice($keywords,0,6); foreach($show as $keyword){$url='https://www.google.com/maps/search/'.rawurlencode($keyword.' family support Devon Cornwall');$html.='<a class="bh-support-link" href="'.esc_url($url).'" target="_blank" rel="noopener"><strong>'.esc_html(ucwords($keyword)).'</strong><span>Search local support on Google Maps ↗</span></a>';}
    return $html.'</div></section>';
}
function bubbahub_support_apps($topic='') {
    $s=bubbahub_support_defaults(); $apps=$s['apps'];
    if(!$topic && !empty($_GET['support_topic'])) $topic=sanitize_text_field(wp_unslash($_GET['support_topic']));
    $html='<section class="bh-support-section" id="support-apps"><div class="bh-support-section-head"><span class="bh-support-kicker">Family apps</span><h2>'.($topic?'Apps for '.esc_html(ucwords($topic)):'Useful family apps').'</h2><p>Find curated apps or browse the Apple App Store and Google Play.</p></div><div class="bh-support-link-grid">';
    foreach($apps as $a){if(empty($a['url'])||($topic&&!bubbahub_support_resource_matches($a['keywords']??'', $topic)))continue;$html.='<a class="bh-support-link" href="'.esc_url($a['url']).'" target="_blank" rel="noopener"><strong>'.esc_html($a['title']).'</strong><span>'.esc_html($a['description']).' ↗</span></a>';}
    if($topic){$stores=array('App Store'=>'https://apps.apple.com/gb/search?term='.rawurlencode($topic),'Google Play'=>'https://play.google.com/store/search?q='.rawurlencode($topic).'&c=apps'); foreach($stores as $name=>$url)$html.='<a class="bh-support-link" href="'.esc_url($url).'" target="_blank" rel="noopener"><strong>'.esc_html(ucwords($topic)).'</strong><span>Find apps on '.esc_html($name).' ↗</span></a>';} else {foreach(array_slice($s['keywords'],0,6) as $keyword){$html.='<a class="bh-support-link" href="'.esc_url('https://apps.apple.com/gb/search?term='.rawurlencode($keyword)).'" target="_blank" rel="noopener"><strong>'.esc_html(ucwords($keyword)).'</strong><span>Search apps on the App Store ↗</span></a>';}}
    return $html.'</div></section>';
}

function bubbahub_support_admin_menu() {
    add_submenu_page('edit.php?post_type=group','Support Hub','Support Hub','manage_options','bubbahub-support','bubbahub_support_admin_page');
}
add_action('admin_menu','bubbahub_support_admin_menu');

function bubbahub_support_admin_page() {
    if(!current_user_can('manage_options')) return;
    $s=bubbahub_support_defaults();
    if(!empty($_POST['bh_support_admin_save'])&&check_admin_referer('bh_support_admin')){
        if(array_key_exists('keywords',$_POST)) $s['keywords']=array_values(array_filter(array_map('sanitize_text_field',preg_split('/\r?\n/',wp_unslash($_POST['keywords'])))));
        if(array_key_exists('link_title',$_POST)){ $s['links']=array(); foreach((array)$_POST['link_title'] as $i=>$title){$url=esc_url_raw($_POST['link_url'][$i]??'');if($title&&$url)$s['links'][]=array('title'=>sanitize_text_field($title),'url'=>$url,'description'=>sanitize_text_field($_POST['link_description'][$i]??''),'keywords'=>sanitize_text_field($_POST['link_keywords'][$i]??''));} }
        if(array_key_exists('app_title',$_POST)){ $s['apps']=array(); foreach((array)$_POST['app_title'] as $i=>$title){$url=esc_url_raw($_POST['app_url'][$i]??'');if($title&&$url)$s['apps'][]=array('title'=>sanitize_text_field($title),'url'=>$url,'description'=>sanitize_text_field($_POST['app_description'][$i]??''),'keywords'=>sanitize_text_field($_POST['app_keywords'][$i]??''));} }
        update_option('bubbahub_support_settings',$s);
        if(array_key_exists('leader_specialisms',$_POST)) foreach((array)$_POST['leader_specialisms'] as $uid=>$specialisms) update_user_meta(absint($uid),'bh_support_specialisms',sanitize_text_field($specialisms));
        echo '<div class="notice notice-success"><p>Support Hub settings saved.</p></div>';
    }
    $tab=sanitize_key($_GET['tab']??'topics');
    ?><div class="wrap"><h1>BubbaHub Support Hub</h1>
    <nav class="nav-tab-wrapper">
      <a class="nav-tab <?php echo $tab==='topics'?'nav-tab-active':''; ?>" href="<?php echo esc_url(admin_url('edit.php?post_type=group&page=bubbahub-support&tab=topics')); ?>">Topics &amp; matching</a>
      <a class="nav-tab <?php echo $tab==='links'?'nav-tab-active':''; ?>" href="<?php echo esc_url(admin_url('edit.php?post_type=group&page=bubbahub-support&tab=links')); ?>">Useful links</a>
      <a class="nav-tab <?php echo $tab==='apps'?'nav-tab-active':''; ?>" href="<?php echo esc_url(admin_url('edit.php?post_type=group&page=bubbahub-support&tab=apps')); ?>">Apps</a>
      <a class="nav-tab <?php echo $tab==='leaders'?'nav-tab-active':''; ?>" href="<?php echo esc_url(admin_url('edit.php?post_type=group&page=bubbahub-support&tab=leaders')); ?>">Leader specialisms</a>
      <a class="nav-tab" href="<?php echo esc_url(admin_url('edit.php?post_type=bh_support_article')); ?>">Specialist articles</a>
      <a class="nav-tab" href="<?php echo esc_url(admin_url('edit.php?post_type=bh_support_request')); ?>">Questions</a>
    </nav>
    <form method="post"><?php wp_nonce_field('bh_support_admin'); ?>
    <?php if($tab==='topics'): ?>
      <h2>Support keywords</h2><p>One topic per line. These power leader matching, local Google Maps discovery and app searches.</p>
      <textarea name="keywords" style="width:100%;max-width:900px;height:220px"><?php echo esc_textarea(implode("\n",$s['keywords'])); ?></textarea>
    <?php elseif($tab==='links'): ?>
      <h2>Useful links</h2><p>Add trusted resources. Keywords are retained so we can later filter links by the family's question.</p>
      <table class="widefat"><thead><tr><th>Title</th><th>URL</th><th>Description</th><th>Keywords</th></tr></thead><tbody><?php for($i=0;$i<max(5,count($s['links']));$i++):$l=$s['links'][$i]??array(); ?><tr><td><input name="link_title[]" value="<?php echo esc_attr($l['title']??''); ?>"></td><td><input class="widefat" name="link_url[]" value="<?php echo esc_attr($l['url']??''); ?>"></td><td><input class="widefat" name="link_description[]" value="<?php echo esc_attr($l['description']??''); ?>"></td><td><input name="link_keywords[]" value="<?php echo esc_attr($l['keywords']??''); ?>"></td></tr><?php endfor; ?></tbody></table>
    <?php elseif($tab==='apps'): ?>
      <h2>Curated apps</h2><p>Add recommended apps alongside the automatic App Store and Google Play searches.</p>
      <table class="widefat"><thead><tr><th>App</th><th>URL</th><th>Description</th><th>Keywords</th></tr></thead><tbody><?php for($i=0;$i<max(5,count($s['apps']));$i++):$a=$s['apps'][$i]??array(); ?><tr><td><input name="app_title[]" value="<?php echo esc_attr($a['title']??''); ?>"></td><td><input class="widefat" name="app_url[]" value="<?php echo esc_attr($a['url']??''); ?>"></td><td><input class="widefat" name="app_description[]" value="<?php echo esc_attr($a['description']??''); ?>"></td><td><input name="app_keywords[]" value="<?php echo esc_attr($a['keywords']??''); ?>"></td></tr><?php endfor; ?></tbody></table>
    <?php elseif($tab==='leaders'): ?>
      <h2>Leader specialisms</h2><p>Optional extra matching terms for each approved leader. Example: <code>sleep, breastfeeding, baby massage</code>.</p>
      <table class="widefat"><thead><tr><th>Leader</th><th>Email</th><th>Specialisms</th></tr></thead><tbody><?php foreach(get_users(array('role__in'=>array('leader','leaderpro'))) as $leader): ?><tr><td><?php echo esc_html($leader->display_name); ?></td><td><?php echo esc_html($leader->user_email); ?></td><td><input class="widefat" name="leader_specialisms[<?php echo esc_attr($leader->ID); ?>]" value="<?php echo esc_attr(get_user_meta($leader->ID,'bh_support_specialisms',true)); ?>"></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?>
    <p><button class="button button-primary" name="bh_support_admin_save" value="1">Save Support Hub</button></p></form></div><?php
}

function bubbahub_support_process_question() {
    if(empty($_POST['bh_support_action'])||$_POST['bh_support_action']!=='ask') return;
    if(empty($_POST['bh_support_question_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bh_support_question_nonce'])),'bh_support_question')) return;
    $data=array(
        'name'=>sanitize_text_field(wp_unslash($_POST['support_name']??'')),
        'email'=>sanitize_email(wp_unslash($_POST['support_email']??'')),
        'topic'=>sanitize_text_field(wp_unslash($_POST['support_topic']??'')),
        'region'=>sanitize_text_field(wp_unslash($_POST['support_region']??'')),
        'question'=>sanitize_textarea_field(wp_unslash($_POST['support_question']??'')),
    );
    if(!$data['name']||!is_email($data['email'])||!$data['topic']||!$data['question']) return;
    $result=bubbahub_support_send_question($data);
    if(!is_wp_error($result)){ wp_safe_redirect(add_query_arg('support_sent','1',wp_get_referer()?:home_url('/support/'))); exit; }
}
add_action('template_redirect','bubbahub_support_process_question');

function bubbahub_support_assets() {
    if(!is_singular() && !is_page('support') && !is_page('leader')) return;
    wp_register_style('bubbahub-support',false,array(),BUBBAHUB_SUPPORT_VERSION); wp_enqueue_style('bubbahub-support');
    wp_add_inline_script('bubbahub-support','document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".bh-support-topic,.bh-support-topic-clear").forEach(function(b){b.addEventListener("click",function(){var t=b.dataset.topic||"";var i=document.getElementById("bh-support-topic-input");if(i)i.value=t;var u=new URL(location.href);if(t)u.searchParams.set("support_topic",t);else u.searchParams.delete("support_topic");u.hash="support-resources";location.href=u.toString();});});});');
    wp_add_inline_style('bubbahub-support','.bh-support-hub{max-width:1180px;margin:0 auto;padding:30px 18px;color:#263238}.bh-support-hero{padding:45px 30px;border-radius:24px;background:linear-gradient(135deg,#fff4e8,#eef8ff);margin-bottom:25px}.bh-support-hero h1{font-size:clamp(32px,5vw,52px);margin:8px 0}.bh-support-card,.bh-support-section,.bh-support-topics{margin:25px 0}.bh-support-card{padding:28px;border:1px solid #e5e7eb;border-radius:20px;background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.05)}.bh-support-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.bh-support-hub label,.bh-support-article-form label{display:block;font-weight:700;margin-bottom:6px}.bh-support-hub input,.bh-support-hub textarea{width:100%;padding:12px 14px;border:1px solid #d9dde3;border-radius:12px;box-sizing:border-box}.bh-support-button{border:0;border-radius:12px;padding:13px 20px;background:#333;color:#fff;font-weight:700;cursor:pointer}.bh-support-section-head{margin-bottom:16px}.bh-support-kicker{font-size:12px;text-transform:uppercase;letter-spacing:.08em;font-weight:800;opacity:.7}.bh-support-topic-grid{display:flex;flex-wrap:wrap;gap:10px}.bh-support-topic,.bh-support-topic-clear{border:1px solid #d9dde3;border-radius:999px;padding:10px 15px;background:#fff;cursor:pointer;font-weight:700}.bh-support-topic-grid{margin-bottom:12px}.bh-support-article-grid,.bh-support-link-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.bh-support-article,.bh-support-link{padding:20px;border:1px solid #e5e7eb;border-radius:16px;background:#fff;text-decoration:none;color:inherit}.bh-support-article h3{margin:8px 0}.bh-support-link{display:flex;flex-direction:column;gap:7px}.bh-support-link span{font-size:14px;opacity:.75}.bh-support-request{padding:20px 0;border-bottom:1px solid #ddd}.bh-support-request textarea{width:100%;box-sizing:border-box}.bh-support-inbox{max-width:900px;margin:30px auto}.bh-support-empty{padding:30px;background:#f7f7f7;border-radius:16px}@media(max-width:800px){.bh-support-form-grid,.bh-support-article-grid,.bh-support-link-grid{grid-template-columns:1fr}}');
}
