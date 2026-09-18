<?php
/**
 * BubbaHub Support Hub
 * Questions -> matched leaders, specialist articles, useful links, apps and community resource recommendations.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'BUBBAHUB_SUPPORT_VERSION', '1.3.0' );

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
    register_post_type( 'bh_support_resource', array(
        'labels' => array( 'name'=>'Recommended Resources', 'singular_name'=>'Recommended Resource', 'add_new_item'=>'Add Recommended Resource', 'edit_item'=>'Edit Recommended Resource' ),
        'public'=>false, 'show_ui'=>true, 'show_in_menu'=>false, 'supports'=>array('title','editor','author'),
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

function bubbahub_support_directory($topic='') {
    $s=bubbahub_support_defaults();
    $links=$s['links'];
    $html='<section class="bh-support-panel bh-support-directory" id="support-directory"><div class="bh-support-section-head bh-support-row-head"><div><span class="bh-support-kicker">LOCAL SUPPORT</span><h2>Local Support Services Directory</h2><p>Search nearby family centres, care networks, and advice hubs by postcode.</p></div><div class="bh-support-filters"><input aria-label="Postcode" placeholder="POSTCODE:  TQ9 5SX"><select aria-label="Support category"><option>All Categories</option>';
    foreach($s['keywords'] as $keyword) $html.='<option>'.esc_html(ucwords($keyword)).'</option>';
    $html.='</select></div></div><div class="bh-support-directory-grid">';
    $shown=0;
    foreach($links as $l){
        if(empty($l['url'])) continue;
        if($topic && !bubbahub_support_resource_matches($l['keywords']??'', $topic)) continue;
        $shown++;
        $html.='<article class="bh-support-directory-card"><span class="bh-support-tag">FAMILY SUPPORT</span><h3>'.esc_html($l['title']).'</h3><p>'.esc_html($l['description']).'</p><div class="bh-support-card-meta"><span>Location</span><strong>Devon &amp; Cornwall</strong></div><div class="bh-support-card-meta"><span>Online resource</span><a href="'.esc_url($l['url']).'" target="_blank" rel="noopener">Visit resource ↗</a></div></article>';
        if($shown>=6) break;
    }
    if(!$shown) $html.='<div class="bh-support-empty">No support services are currently listed for this topic.</div>';
    $html.='</div><div class="bh-support-pagination"><span>Showing page 1</span><span class="bh-support-page-buttons"><button type="button" disabled>Previous</button><button type="button">Next</button></span></div></section>';
    return $html;
}

function bubbahub_support_app_store($topic='') {
    $s=bubbahub_support_defaults();
    $apps=$s['apps'];
    if(!$apps){
        $apps=array(
            array('title'=>'Baby Connect','description'=>'Track schedules, feeds, milestones, and sleep patterns to share effortlessly with partners and paediatricians.','keywords'=>'baby,sleep,feeding','url'=>'https://apps.apple.com/gb/search?term=baby%20connect','category'=>'TOP TRACKER','rating'=>'4.3 / 5'),
            array('title'=>'Cozi Family Organizer','description'=>'A shared calendar, grocery list, and meal planner to help organise busy family schedules.','keywords'=>'parenting,family','url'=>'https://apps.apple.com/gb/search?term=cozi%20family%20organizer','category'=>'ESSENTIAL ORGANISER','rating'=>'4.7 / 5'),
            array('title'=>'The Wonder Weeks','description'=>'Personalised weekly guidance around developmental changes and common fussy phases in babies and toddlers.','keywords'=>'development,baby,toddler','url'=>'https://apps.apple.com/gb/search?term=the%20wonder%20weeks','category'=>'DEVELOPMENT','rating'=>'4.6 / 5')
        );
    }
    $html='<section class="bh-support-section" id="support-apps"><div class="bh-support-section-head bh-support-row-head"><div><span class="bh-support-kicker">FAMILY TOOLS</span><h2>Parent Support App Store</h2><p>Top-rated applications and useful tools for families.</p></div><div class="bh-support-filters"><input placeholder="Search apps..." aria-label="Search apps"><select aria-label="App category"><option>All Categories</option><option>Development</option><option>Wellbeing</option><option>Parenting</option><option>Sleep</option></select></div></div><div class="bh-support-app-grid">';
    foreach($apps as $a){
        if(empty($a['title'])||($topic&&!bubbahub_support_resource_matches($a['keywords']??'', $topic))) continue;
        $html.='<article class="bh-support-app-card"><div class="bh-support-card-top"><span class="bh-support-tag">'.esc_html($a['category']??'FAMILY APP').'</span><span class="bh-support-rating">★ '.esc_html($a['rating']??'4.5 / 5').'</span></div><h3>'.esc_html($a['title']).'</h3><p>'.esc_html($a['description']??'Helpful support for families.').'</p><div class="bh-support-app-footer"><span>iOS, Android</span><a href="'.esc_url($a['url']).'" target="_blank" rel="noopener" class="bh-support-small-button">Get App</a></div></article>';
    }
    return $html.'</div><div class="bh-support-pagination"><span>Showing page 1</span><span class="bh-support-page-buttons"><button type="button" disabled>Previous</button><button type="button">Next</button></span></div></section>';
}

function bubbahub_support_guidance() {
    $s=bubbahub_support_defaults();
    $guidance=array(
        array('tag'=>'PDF TOOLKIT','source'=>'UK Government Guidelines','title'=>'Best Start in Life: Preparing for School Guide','description'=>'Official developmental expectations, self-care benchmarks, and communication tips for children approaching school age.'),
        array('tag'=>'RESOURCE PACK','source'=>'NSPCC Learning','title'=>'Early Years Safety & Positive Handling','description'=>'Practical guidance for creating safe, secure environments and supporting positive relationships.'),
        array('tag'=>'PRACTITIONER & PARENT MANUAL','source'=>'Department for Education','title'=>'Meeting Early Language & Communication Needs','description'=>'Actionable strategies for encouraging early communication, vocabulary growth, and social play.')
    );
    $links=$s['links'];
    $html='<section class="bh-support-panel bh-support-guidance" id="support-guidance"><div class="bh-support-section-head"><span class="bh-support-kicker">TRUSTED GUIDANCE</span><h2>Guidance Documents &amp; Toolkits</h2><p>Official references, safeguarding protocols, and developmental guides for baby &amp; preschool aged children.</p></div><div class="bh-support-guidance-list">';
    foreach($guidance as $i=>$g){
        $url=!empty($links[$i]['url'])?$links[$i]['url']:'https://www.gov.uk/';
        $html.='<article class="bh-support-guidance-card"><div><div class="bh-support-guidance-meta"><span class="bh-support-tag">'.esc_html($g['tag']).'</span><span>'.esc_html($g['source']).' • '.esc_html(($i+1)*5+10).' min read</span></div><h3>'.esc_html($g['title']).'</h3><p>'.esc_html($g['description']).'</p></div><a href="'.esc_url($url).'" target="_blank" rel="noopener" class="bh-support-view-button">Download PDF / View</a></article>';
    }
    return $html.'</div></section>';
}

function bubbahub_support_public() {
    $s=bubbahub_support_defaults();
    $topic='';
    if(!empty($_GET['support_topic'])) $topic=sanitize_text_field(wp_unslash($_GET['support_topic']));
    ob_start(); ?>
    <div class="bh-support-hub">
      <section class="bh-support-hero">
        <h1>Family Hub &amp; Resource Portal</h1>
        <p>Your trusted central sanctuary for specialist insights, local community directories, recommended apps, and official early years guidance documents.</p>
      </section>
      <section class="bh-support-panel bh-support-question" id="ask-specialist">
        <div class="bh-support-section-head"><h2>Ask A Specialist</h2><p>Send your question directly to verified specialists offering support in your area based on your postcode.</p></div>
        <?php if(!empty($_GET['support_sent'])): ?><div class="bh-support-success">Thank you. Your question has been sent to relevant Bubba Hub leaders and specialists.</div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="bh_support_action" value="ask">
          <?php wp_nonce_field('bh_support_question','bh_support_question_nonce'); ?>
          <input type="hidden" name="support_name" value="<?php echo esc_attr(is_user_logged_in()?wp_get_current_user()->display_name:'Bubba Hub family'); ?>">
          <div class="bh-support-form-grid">
            <p><label>SELECT SUPPORT CATEGORY</label><select name="support_topic" required><option value="">Choose a support category</option><?php foreach($s['keywords'] as $keyword): ?><option value="<?php echo esc_attr($keyword); ?>" <?php selected($topic,$keyword); ?>><?php echo esc_html(ucwords($keyword)); ?></option><?php endforeach; ?></select></p>
            <p><label>YOUR POSTCODE</label><input name="support_region" placeholder="TQ9 5SX" required></p>
          </div>
          <p><label>YOUR EMAIL ADDRESS</label><input type="email" name="support_email" placeholder="Enter your email so specialists can reply to you..." required><small>We need your email to send the specialist's response back to you.</small></p>
          <p><label>YOUR QUESTION</label><textarea name="support_question" rows="5" placeholder="Describe what you're experiencing or ask your specific question..." required></textarea></p>
          <button class="bh-support-button" type="submit">Send to Local Specialists</button>
        </form>
      </section>
      <section class="bh-support-section">
        <div class="bh-support-section-head"><h2>From Our Specialists</h2><p>Direct wisdom, tips, and updates straight from our early years educators and specialists.</p></div>
        <div class="bh-support-article-grid">
        <?php $q=new WP_Query(array('post_type'=>'bh_support_article','post_status'=>'publish','posts_per_page'=>6)); while($q->have_posts()):$q->the_post(); ?>
          <article class="bh-support-article">
            <div class="bh-support-card-top"><span class="bh-support-tag">SPECIALIST ADVICE</span><span><?php echo esc_html(max(1,(int)ceil(str_word_count(wp_strip_all_tags(get_the_content()))/180))); ?> min read</span></div>
            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
            <p><?php echo esc_html(wp_trim_words(wp_strip_all_tags(get_the_content()),28)); ?></p>
            <div class="bh-support-article-footer"><span><?php echo esc_html(get_the_author()); ?></span><time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('M j, Y')); ?></time></div>
          </article>
        <?php endwhile; wp_reset_postdata(); ?>
        <?php if(!$q->post_count): ?><div class="bh-support-empty">Specialist articles will appear here once approved.</div><?php endif; ?>
        </div>
      </section>
      <?php echo bubbahub_support_directory($topic); ?>
      <?php echo bubbahub_support_app_store($topic); ?>
      <?php echo bubbahub_support_guidance(); ?>
      <?php echo bubbahub_support_recommended_resources($topic); ?>
      <?php echo bubbahub_support_resource_form(); ?>
    </div>
    <?php return ob_get_clean();
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


function bubbahub_support_resource_form() {
    $s=bubbahub_support_defaults();
    $sent=false;
    if(!empty($_POST['bh_support_resource_action']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['bh_support_resource_nonce']??'')),'bh_support_resource')){
        $title=sanitize_text_field(wp_unslash($_POST['resource_title']??''));
        $url=esc_url_raw(wp_unslash($_POST['resource_url']??''));
        $description=sanitize_textarea_field(wp_unslash($_POST['resource_description']??''));
        $topic=sanitize_text_field(wp_unslash($_POST['resource_topic']??''));
        if($title && $url && wp_http_validate_url($url)){
            $id=wp_insert_post(array('post_type'=>'bh_support_resource','post_status'=>'pending','post_title'=>$title,'post_content'=>$description,'post_author'=>get_current_user_id()),true);
            if(!is_wp_error($id)){
                update_post_meta($id,'_bh_support_resource_url',$url);
                update_post_meta($id,'_bh_support_resource_description',$description);
                update_post_meta($id,'_bh_support_resource_keywords',$topic);
                $sent=true;
            }
        }
    }
    ob_start(); ?>
    <div class="bh-support-card bh-support-resource-form">
      <span class="bh-support-kicker">Community powered</span>
      <h2>Recommend a useful resource</h2>
      <p>Know a website, service or resource that could help other families? Send it to BubbaHub for review.</p>
      <?php if($sent): ?><div class="bh-support-empty">Thank you — your recommendation has been sent for review.</div><?php endif; ?>
      <form method="post"><?php wp_nonce_field('bh_support_resource','bh_support_resource_nonce'); ?>
        <input type="hidden" name="bh_support_resource_action" value="1">
        <div class="bh-support-form-grid">
          <p><label>Resource name</label><input name="resource_title" required></p>
          <p><label>Website or resource link</label><input type="url" name="resource_url" placeholder="https://" required></p>
          <p><label>Topic</label><select name="resource_topic"><option value="">General family support</option><?php foreach($s['keywords'] as $keyword): ?><option value="<?php echo esc_attr($keyword); ?>"><?php echo esc_html(ucwords($keyword)); ?></option><?php endforeach; ?></select></p>
          <p><label>Why recommend it?</label><input name="resource_description" required></p>
        </div>
        <button class="bh-support-button">Submit recommendation</button>
      </form>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('bubbahub_support_resource_form','bubbahub_support_resource_form');

function bubbahub_support_recommended_resources($topic=''){
    if(!$topic && !empty($_GET['support_topic'])) $topic=sanitize_text_field(wp_unslash($_GET['support_topic']));
    $q=new WP_Query(array('post_type'=>'bh_support_resource','post_status'=>'publish','posts_per_page'=>6,'orderby'=>'date','order'=>'DESC'));
    $html='<section class="bh-support-section"><div class="bh-support-section-head"><span class="bh-support-kicker">Community recommendations</span><h2>Resources recommended by families</h2><p>Helpful resources shared by the BubbaHub community and reviewed before publication.</p></div><div class="bh-support-link-grid">';
    $shown=0;
    while($q->have_posts()){ $q->the_post(); $keywords=get_post_meta(get_the_ID(),'_bh_support_resource_keywords',true); if($topic&&!bubbahub_support_resource_matches($keywords,$topic)) continue; $url=get_post_meta(get_the_ID(),'_bh_support_resource_url',true); $description=get_post_meta(get_the_ID(),'_bh_support_resource_description',true); if(!$url) continue; $shown++; $html.='<a class="bh-support-link" href="'.esc_url($url).'" target="_blank" rel="noopener"><strong>'.esc_html(get_the_title()).'</strong><span>'.esc_html($description?:wp_trim_words(wp_strip_all_tags(get_the_content()),20)).' ↗</span></a>'; }
    wp_reset_postdata();
    if(!$shown) $html.='<div class="bh-support-empty">No community recommendations have been published for this topic yet.</div>';
    return $html.'</div></section>';
}
add_shortcode('bubbahub_support_recommended_resources','bubbahub_support_recommended_resources');

function bubbahub_support_admin_menu() {
    add_submenu_page('edit.php?post_type=group','Support Hub','Support Hub','manage_options','bubbahub-support','bubbahub_support_admin_page');
}
add_action('admin_menu','bubbahub_support_admin_menu');

function bubbahub_support_admin_page() {
    if(!current_user_can('manage_options')) return;
    $s=bubbahub_support_defaults();
    if(!empty($_POST['bh_support_admin_save'])&&check_admin_referer('bh_support_admin')){
        if (array_key_exists('keywords', $_POST)) {
            $s['keywords']=array_values(array_filter(array_map('sanitize_text_field',preg_split('/\r?\n/',wp_unslash($_POST['keywords'])))));
        }
        if (array_key_exists('link_title', $_POST)) {
            $s['links']=array();
        foreach((array)($_POST['link_title']??array()) as $i=>$title){$url=esc_url_raw($_POST['link_url'][$i]??'');if($title&&$url)$s['links'][]=array('title'=>sanitize_text_field($title),'url'=>$url,'description'=>sanitize_text_field($_POST['link_description'][$i]??''),'keywords'=>sanitize_text_field($_POST['link_keywords'][$i]??''));}
            }
        if (array_key_exists('app_title', $_POST)) {
            $s['apps']=array();
            foreach((array)$_POST['app_title'] as $i=>$title){$url=esc_url_raw($_POST['app_url'][$i]??'');if($title&&$url)$s['apps'][]=array('title'=>sanitize_text_field($title),'url'=>$url,'description'=>sanitize_text_field($_POST['app_description'][$i]??''),'keywords'=>sanitize_text_field($_POST['app_keywords'][$i]??''));}
        }
        update_option('bubbahub_support_settings',$s);
        if (array_key_exists('leader_specialisms', $_POST)) foreach((array)$_POST['leader_specialisms'] as $uid=>$specialisms) update_user_meta(absint($uid),'bh_support_specialisms',sanitize_text_field($specialisms));
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
      <a class="nav-tab" href="<?php echo esc_url(admin_url('edit.php?post_type=bh_support_resource')); ?>">Recommended resources</a>
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
    wp_register_style('bubbahub-support',false,array(),BUBBAHUB_SUPPORT_VERSION);
    wp_enqueue_style('bubbahub-support');
    wp_add_inline_style('bubbahub-support', <<<'CSS'
.bh-support-hub{max-width:1024px;margin:0 auto;padding:34px 18px 70px;box-sizing:border-box;color:#193e32;background:#f5f8f5;font-family:inherit}
.bh-support-hub:before{content:"";position:fixed;inset:0;background:#f5f8f5;z-index:-1}
.bh-support-hero{background:linear-gradient(135deg,#1d573f,#2e7655);color:#fff;border-radius:28px;padding:42px 40px;margin:0 0 30px;box-shadow:0 12px 28px rgba(27,76,54,.12)}
.bh-support-hero h1{font-size:clamp(34px,5vw,48px);line-height:1.05;margin:8px 0 16px;color:#fff;font-weight:800}
.bh-support-hero p{max-width:820px;margin:0;font-size:16px;line-height:1.7;color:#e7f3eb}
.bh-support-kicker{display:inline-flex;align-items:center;border-radius:999px;padding:6px 12px;background:#dff3df;color:#23583f;font-size:11px;line-height:1.2;text-transform:uppercase;letter-spacing:.07em;font-weight:800}
.bh-support-hero .bh-support-kicker{background:rgba(255,255,255,.15);color:#e9f7ec}
.bh-support-panel{background:#fff;border:1px solid #e2e8e3;border-radius:28px;padding:32px;margin:0 0 32px;box-shadow:0 8px 24px rgba(25,62,50,.06)}
.bh-support-section{margin:0 0 34px}
.bh-support-section-head{margin-bottom:20px}
.bh-support-section-head h2,.bh-support-panel h2{font-size:27px;line-height:1.2;margin:4px 0 6px;color:#173f32;font-weight:800}
.bh-support-section-head p,.bh-support-panel p{margin:0;color:#60716a;line-height:1.55}
.bh-support-row-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-end}
.bh-support-filters{display:flex;gap:10px;flex:0 0 auto}
.bh-support-filters input,.bh-support-filters select{height:42px;border:1px solid #d7dfda;border-radius:13px;background:#fff;padding:0 13px;color:#315147;min-width:150px;box-sizing:border-box}
.bh-support-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.bh-support-hub form p{margin:0 0 20px}
.bh-support-hub label{display:block;margin:0 0 7px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;font-weight:800;color:#234b3e}
.bh-support-hub input,.bh-support-hub textarea,.bh-support-hub select{width:100%;box-sizing:border-box;border:1px solid #d8dfda;border-radius:13px;background:#f7f9f7;padding:13px 15px;color:#274b40;font:inherit;outline:none}
.bh-support-hub textarea{resize:vertical;min-height:110px}
.bh-support-hub input:focus,.bh-support-hub textarea:focus,.bh-support-hub select:focus{border-color:#2d6d50;box-shadow:0 0 0 3px rgba(45,109,80,.1)}
.bh-support-hub small{display:block;margin-top:7px;color:#829089;font-size:12px}
.bh-support-button,.bh-support-small-button{display:inline-flex;align-items:center;justify-content:center;background:#1e513b;color:#fff!important;border:0;border-radius:12px;padding:12px 18px;font-weight:800;text-decoration:none;cursor:pointer}
.bh-support-button:hover,.bh-support-small-button:hover{background:#153c2d}
.bh-support-success{padding:14px 16px;border-radius:12px;background:#e8f7e9;color:#245d3f;margin:0 0 20px;font-weight:700}
.bh-support-article-grid,.bh-support-directory-grid,.bh-support-app-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
.bh-support-article,.bh-support-directory-card,.bh-support-app-card{background:#fff;border:1px solid #e1e7e2;border-radius:19px;padding:21px;box-shadow:0 4px 14px rgba(25,62,50,.045);display:flex;flex-direction:column;min-width:0}
.bh-support-card-top{display:flex;justify-content:space-between;gap:10px;align-items:center;color:#718078;font-size:12px}
.bh-support-tag{display:inline-flex;width:max-content;background:#dff3df;color:#245a40;border-radius:7px;padding:5px 9px;font-size:10px;font-weight:800;letter-spacing:.03em}
.bh-support-article h3,.bh-support-directory-card h3,.bh-support-app-card h3{font-size:17px;line-height:1.35;margin:14px 0 10px;color:#173f32}
.bh-support-article h3 a{color:inherit;text-decoration:none}
.bh-support-article p,.bh-support-directory-card p,.bh-support-app-card p{font-size:13px;line-height:1.55;color:#60716a;margin:0}
.bh-support-article-footer,.bh-support-app-footer{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-top:auto;padding-top:16px;border-top:1px solid #e8ece9;color:#587067;font-size:12px}
.bh-support-article-footer time{color:#819089}
.bh-support-directory,.bh-support-guidance{padding:30px}
.bh-support-directory-card{background:#f8faf8}
.bh-support-card-meta{display:flex;justify-content:space-between;gap:10px;padding-top:11px;margin-top:11px;border-top:1px solid #e1e7e2;font-size:12px;color:#687a71}
.bh-support-card-meta strong,.bh-support-card-meta a{color:#315d4c;text-align:right}
.bh-support-app-card{min-height:245px}
.bh-support-rating{color:#3c7258;font-weight:800;font-size:12px}
.bh-support-app-footer{margin-top:auto}
.bh-support-small-button{padding:8px 14px;font-size:12px}
.bh-support-pagination{display:flex;justify-content:space-between;align-items:center;margin-top:24px;color:#74837c;font-size:13px}
.bh-support-page-buttons{display:flex;gap:8px}
.bh-support-page-buttons button,.bh-support-view-button{border:1px solid #d7dfda;border-radius:10px;background:#fff;color:#456158;padding:8px 13px;text-decoration:none}
.bh-support-page-buttons button:disabled{opacity:.45}
.bh-support-guidance-list{display:flex;flex-direction:column;gap:12px}
.bh-support-guidance-card{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:18px 17px;background:#f8faf8;border:1px solid #e1e7e2;border-radius:18px}
.bh-support-guidance-meta{display:flex;align-items:center;gap:9px;color:#7a8982;font-size:12px}
.bh-support-guidance-card h3{margin:8px 0 5px;font-size:16px;color:#183f33}
.bh-support-guidance-card p{font-size:12px;margin:0;color:#63746d}
.bh-support-view-button{white-space:nowrap;background:#fff}
.bh-support-link-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.bh-support-link{display:flex;flex-direction:column;gap:6px;padding:18px;background:#fff;border:1px solid #e1e7e2;border-radius:16px;color:#23493d;text-decoration:none}
.bh-support-link strong{font-size:14px}.bh-support-link span{font-size:12px;color:#718078}
.bh-support-empty{padding:24px;border-radius:16px;background:#f4f7f4;color:#718078}
.bh-support-resource-form{margin-top:30px}
@media(max-width:850px){.bh-support-article-grid,.bh-support-directory-grid,.bh-support-app-grid{grid-template-columns:1fr 1fr}.bh-support-row-head{align-items:flex-start;flex-direction:column}.bh-support-filters{width:100%}.bh-support-filters input,.bh-support-filters select{flex:1;min-width:0}}
@media(max-width:620px){.bh-support-hub{padding:20px 12px 50px}.bh-support-hero{padding:30px 24px;border-radius:22px}.bh-support-panel,.bh-support-directory,.bh-support-guidance{padding:22px;border-radius:22px}.bh-support-form-grid,.bh-support-article-grid,.bh-support-directory-grid,.bh-support-app-grid,.bh-support-link-grid{grid-template-columns:1fr}.bh-support-filters{flex-direction:column}.bh-support-filters input,.bh-support-filters select{width:100%}.bh-support-guidance-card{align-items:flex-start;flex-direction:column}.bh-support-view-button{width:100%;text-align:center;box-sizing:border-box}}
CSS
    );
}

add_action( 'wp_enqueue_scripts', 'bubbahub_support_assets', 20 );
